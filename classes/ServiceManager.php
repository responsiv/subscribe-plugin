<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Event;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\StatusLog as StatusLogModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Subscribe\Models\Setting as SettingModel;
use Responsiv\Pay\Models\InvoiceStatus as InvoiceStatusModel;
use ApplicationException;
use Exception;

/**
 * Service engine
 */
class ServiceManager
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Responsiv\Campaign\Classes\InvoiceManager
     */
    protected $invoiceManager;

    /**
     * @var Carbon\Carbon
     */
    public $now;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->invoiceManager = InvoiceManager::instance();
        $this->now = Carbon::now();
    }

    public function initService(ServiceModel $service, $options = [])
    {
        extract(array_merge([
            'membership' => null,
            'invoice' => null,
            'plan' => null,
        ], $options));

        if (!$plan = $plan ?: $service->plan) {
            throw new ApplicationException('Service is missing a plan!');
        }

        if (!$membership = $membership ?: $service->membership) {
            throw new ApplicationException('Service is missing a membership!');
        }

        if (!$invoice) {
            $invoice = $this->invoiceManager->raiseServiceInvoice($service);
            $invoice->items()->delete();
        }

        if ($plan->hasSetupPrice()) {
            $this->invoiceManager->raiseServiceSetupFee(
                $invoice,
                $service,
                $plan->getSetupPrice()
            );
        }

        if ($plan->hasMembershipPrice()) {
            $service->membership_price = $plan->getMembershipPrice();
        }

        if ($plan->hasTrialPeriod()) {
            $service->trial_days = $plan->getTrialPeriod();
        }

        if ($plan->hasGracePeriod()) {
            $service->grace_days = $plan->getGracePeriod();
        }

        $service->name = $plan->name;
        $service->price = $plan->price;
        $service->setup_price = $plan->setup_price;
        $service->renewal_period = $plan->renewal_period;
        $service->first_invoice = $invoice;
        $service->first_invoice_item = $this->invoiceManager->raiseServiceInvoiceItem($invoice, $service);

        /*
         * Trial period
         */
        if ($membership->isTrialActive()) {
            $this->startTrialPeriod($service);
        }
        else {
            $service->status = StatusModel::getFromCode(StatusModel::STATUS_NEW);
            $service->save();
        }
    }

    //
    // Workflow
    //

    public function activateOrDelayService(ServiceModel $service, $comment = null)
    {
        $plan = $service->plan;
        $now = clone $this->now;
        $activateAt = $service->delay_activated_at ?: $now;

        $currentBillingDate = $plan->getPeriodStartDate($activateAt);

        /*
         * Check if this is a not future activation date
         */
        if ($currentBillingDate <= $now) {
            $this->activateService($service, $comment);
        }
        else {
            $service->delay_activated_at = $currentBillingDate;
            $service->is_active = false;

            $status = StatusModel::getFromCode(StatusModel::STATUS_PENDING);
            StatusLogModel::createRecord($status->id, $service, $comment);

            Event::fire('responsiv.subscribe.serviceActivatedLater', $service);

            $service->save();
        }
    }

    public function activateService(ServiceModel $service, $comment = null)
    {
        $plan = $service->plan;
        $now = clone $this->now;
        $activateAt = $service->delay_activated_at ?: $now;

        $currentBillingDate = $plan->getPeriodStartDate($activateAt);
        $nextBillingDate = $plan->getPeriodEndDate($currentBillingDate);

        $service->current_period_start = $service->service_period_start = $currentBillingDate;
        $service->current_period_end = $service->service_period_end = $nextBillingDate;
        $service->activated_at = $now;
        $service->delay_activated_at = null;
        $service->is_active = true;
        $service->count_renewal = 1;

        $status = StatusModel::getFromCode(StatusModel::STATUS_ACTIVE);
        StatusLogModel::createRecord($status->id, $service, $comment);

        Event::fire('responsiv.subscribe.serviceActivated', $service);

        $service->save();
    }

    /**
     * Trial membership
     */
    public function startTrialPeriod(ServiceModel $service, $comment = null)
    {
        if (!$membership = $service->membership) {
            throw new ApplicationException('Service is missing a membership!');
        }

        /*
         * Status log
         */
        $status = StatusModel::getFromCode(StatusModel::STATUS_TRIAL);
        StatusLogModel::createRecord($status->id, $service, $comment);

        /*
         * Current start and end times
         */
        $service->is_active = true;
        $service->current_period_start = $membership->trial_period_start;
        $service->current_period_end = $membership->trial_period_end;
        $service->save();

        Event::fire('responsiv.subscribe.membershipTrialStarted', $service);
    }

    /**
     * Grace membership
     */
    public function startGracePeriod(ServiceModel $service, $comment = null)
    {
        $status = StatusModel::getFromCode(StatusModel::STATUS_GRACE);
        StatusLogModel::createRecord($status->id, $service, $comment);

        $graceStart = clone $service->service_period_end;
        $graceEnd = $graceStart->addDays($service->grace_days);

        /*
         * Current start and end times
         */
        $service->current_period_start = $graceStart;
        $service->current_period_end = $graceEnd;
        $service->save();

        Event::fire('responsiv.subscribe.membershipGraceStarted', $service);

        return true;
    }

    /**
     * Renew membership
     */
    public function renewService(ServiceModel $service, $comment = null)
    {
        if (!$service->canRenewService()) {
            return false;
        }

        $now = clone $this->now;
        $startDate = $service->service_period_end;
        $endDate = $service->plan->getPeriodEndDate($startDate);

        /*
         * New start and end dates
         */
        $service->current_period_start = $service->service_period_start = $startDate;
        $service->current_period_end = $service->service_period_end = $endDate;

        /*
         * Add the renewal
         */
        $service->count_renewal++;
        $service->save();

        /*
         * Renewal is within the active period
         */
        if ($endDate > $now) {
            $status = StatusModel::getFromCode(StatusModel::STATUS_ACTIVE);
            StatusLogModel::createRecord($status->id, $service, $comment);
        }

        return true;
    }

    //
    // Checking
    //

    /*
     * Service cancelled
     */
    public function checkServiceCancelled($service)
    {
        if ($service->cancelled_at) {
            return true;
        }

        if ($service->delay_cancelled_at && $service->delay_cancelled_at <= $this->now) {
            $this->cancelServiceNow($service, 'Cancelled on delayed cancellation date');
            return true;
        }

        return false;
    }

    /*
     * Service delayed
     */
    public function checkServiceDelayed($service)
    {
        if ($service->delay_activated_at && $service->delay_activated_at > $this->now) {
            return true;
        }

        if (
            $service->delay_activated_at &&
            $service->delay_activated_at <= $this->now
        ) {
            $service->count_renewal = 1;
            $this->activateOrDelayService($service);
            return true;
        }

        return false;
    }

    /*
     * Renewal period reached
     */
    public function checkRenewalPeriod($service)
    {
        if ($service->renewal_period && $service->renewal_period >= $service->count_renewal) {
            $this->completeService($service, 'Renewal period reached');
            return true;
        }

        return false;
    }

    /*
     * Service up for renewal
     */
    public function checkPeriodEnded($service)
    {
        if (!$service->hasPeriodEnded()) {
            return false;
        }

        $statusCode = $service->status ? $service->status->code : null;

        /*
         * Grace ended
         */
        if ($statusCode == StatusModel::STATUS_GRACE) {
            $this->pastDueService($service, 'Grace ended');
        }
        /*
         * Trial ended
         */
        elseif ($statusCode == StatusModel::STATUS_TRIAL) {
            $this->pastDueService($service, 'Trial ended');
        }
        /*
         * Service complete
         */
        elseif ($statusCode == StatusModel::STATUS_COMPLETE) {
            // Do nothing
        }
        /*
         * Invoice check
         */
        elseif ($statusCode == StatusModel::STATUS_ACTIVE) {
            $allPaid = !$service->hasUnpaidInvoices();
            $canRenew = $service->canRenewService();

            if ($allPaid && $canRenew) {
                $this->invoiceManager->raiseServiceRenewalInvoice($service);
            }
            elseif ($allPaid) {
                $this->completeService($service, 'Does not renew');
            }
        }

        return true;
    }

    public function checkAdvanceInvoice($service)
    {
        $statusCode = $service->status ? $service->status->code : null;

        if (
            $statusCode != StatusModel::STATUS_ACTIVE ||
            $service->hasPeriodEnded() ||
            !$service->canRenewService()
        ) {
            return false;
        }

        $advanceDays = (int) SettingModel::get('invoice_advance_days', 0);

        if (!$advanceDays || $service->hasUnpaidInvoices()) {
            return false;
        }

        $fromDate = clone $service->service_period_end;
        $fromDate->subDays($advanceDays);

        /*
         * Catch instances where the advance days exceed the subscription period
         */
        if ($fromDate < $service->service_period_start) {
            $fromDate = $service->service_period_start;
        }

        /*
         * Raise the invoice early
         */
        if ($this->now > $fromDate) {
            $this->invoiceManager->raiseServiceRenewalInvoice($service);
        }
    }

    //
    // Utils
    //

    /*
     * Cancels the service, due to no pamynet.
     */
    public function pastDueService(ServiceModel $service, $comment = null)
    {
        $status = StatusModel::getFromCode(StatusModel::STATUS_PASTDUE);
        StatusLogModel::createRecord($status->id, $service, $comment);

        $service->cancelled_at = $this->now;
        $service->delay_cancelled_at = null;
        $service->is_active = false;

        Event::fire('responsiv.subscribe.servicePastDue', $service);

        $this->invoiceManager->voidUnpaidService($service);

        $service->save();
    }

    /**
     * Cancels this service at the end of the current term.
     *
     * Available options:
     *
     * - atDate: Specify an exact date when the service is to be cancelled.
     * - atTermEnd: Defer cancellation to the end of the current period, otherwise immediately.
     */
    public function cancelService(ServiceModel $service, $comment = null, $options = [])
    {
        extract(array_merge([
            'atTermEnd' => true,
            'atDate' => null,
        ], $options));

        if ($atTermEnd && $service->service_period_end) {
            $atDate = $service->service_period_end;
        }

        $isFuture = $atDate ? $atDate > $this->now : false;

        /*
         * Not a future cancellation, cancel it now
         */
        if (!$isFuture) {
            $status = StatusModel::getFromCode(StatusModel::STATUS_CANCELLED);
            StatusLogModel::createRecord($status->id, $service, $comment);

            $service->cancelled_at = $this->now;
            $service->delay_cancelled_at = null;
            $service->is_active = false;

            /*
             * Cancel any trial agreement prematurely
             */
            if (($membership = $service->membership) && $membership->isTrialActive()) {
                $membership->trial_period_end = $this->now;
                $membership->save();
            }

            Event::fire('responsiv.subscribe.serviceCancelled', $service);

            $this->invoiceManager->voidUnpaidService($service);
        }
        /*
         * Cancel at a future date
         */
        else {
            $service->delay_cancelled_at = $atDate ?: $this->now;
        }

        $service->save();
    }

    /**
     * Helper to cancel the service immediately.
     * @see cancelService()
     */
    public function cancelServiceNow(ServiceModel $service, $comment = null)
    {
        $this->cancelService($service, $comment, ['atTermEnd' => false]);
    }

    /**
     * Complete service
     */
    public function completeService(ServiceModel $service, $comment = null)
    {
        $status = StatusModel::getFromCode(StatusModel::STATUS_COMPLETE);
        StatusLogModel::createRecord($status->id, $service, $comment);

        /*
         * Completed date
         */
        $service->expired_at = $this->now;
        $service->current_period_start = null;
        $service->current_period_end = null;
        $service->is_active = false;
        $service->save();

        Event::fire('responsiv.subscribe.serviceCompleted', $service);

        return true;
    }
}
