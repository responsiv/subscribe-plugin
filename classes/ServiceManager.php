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

        $service->invoice = $invoice;
        $service->invoice_item = $this->invoiceManager->raiseServiceInvoiceItem($invoice, $service);
        $service->name = $plan->name;
        $service->price = $plan->price;
        $service->setup_price = $plan->setup_price;
        $service->renewal_period = $plan->renewal_period;

        /*
         * Trial period
         */
        if ($membership->isTrialActive()) {
            $this->startTrialPeriod($service);
        }
        else {
            $service->status = StatusModel::getStatusNew();
            $service->save();
        }
    }

    //
    // Workflow
    //

    public function attemptRenewService(ServiceModel $service)
    {
        /*
         * Raise a new invoice
         */
        $invoice = $this->invoiceManager->raiseServiceInvoice($service);

        $this->invoiceManager->raiseServiceInvoiceItem($invoice, $service);

        $invoice->updateInvoiceStatus(InvoiceStatusModel::STATUS_APPROVED);

        $invoice->touchTotals();

        $invoice->reload();

        /*
         * If invoice is for $0, process a fake payment
         */
        if ($invoice->total <= 0) {
            $invoice->submitManualPayment('Invoice total is zero');
        }
        else {
            /*
             * Pay from profile
             */
            try {
                throw new Exception('Not implemented yet!');
            }
            /*
             * Payment failed
             */
            catch (Exception $ex) {
                /*
                 * Grace period
                 */
                if ($service->hasGracePeriod()) {
                    $this->startGracePeriod($service, 'Automatic payment failed');
                }
                else {
                    $this->pastDueService($service, 'Automatic payment failed');
                }
            }
        }
    }

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

            $status = StatusModel::getStatusPending();
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

        $status = StatusModel::getStatusActive();
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
        $status = StatusModel::getStatusTrial();
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
        $status = StatusModel::getStatusGrace();
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
            $status = StatusModel::getStatusActive();
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
            $this->cancelService($service, null, null, 'Cancelled on delayed cancellation date');
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
    public function checkServiceRenew($service)
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
                $this->attemptRenewService($service);
            }
            elseif ($allPaid) {
                $this->completeService($service, 'Does not renew');
            }
            else {
                // Not sure how it would end up here
                return false;
            }
        }

        return true;
    }

    //
    // Utils
    //

    /*
     * Cancels the service, due to no pamynet.
     */
    public function pastDueService(ServiceModel $service, $comment = null)
    {
        $status = StatusModel::getStatusPastDue();
        StatusLogModel::createRecord($status->id, $service, $comment);

        $service->cancelled_at = $this->now;
        $service->delay_cancelled_at = null;
        $service->is_active = false;

        Event::fire('responsiv.subscribe.servicePastDue', $service);

        $this->invoiceManager->voidUnpaidService($service);

        $service->save();
    }

    /**
     * Cancels this service, either from a specified date or immediately.
     */
    public function cancelService(
        ServiceModel $service,
        $fromDate = null,
        $atTermEnd = null,
        $comment = null
    ) {
        $cancelDay = null;

        if ($fromDate) {
            $cancelDay = $fromDate;
        }
        elseif ($atTermEnd && $service->current_period_end) {
            $cancelDay = $service->current_period_end;
        }

        $isFuture = $cancelDay ? $cancelday > $current : false;

        /*
         * Not a future cancellation, cancel it now
         */
        if (!$isFuture) {

            $status = StatusModel::getStatusCancelled();
            StatusLogModel::createRecord($status->id, $service, $comment);

            $service->cancelled_at = $cancelDay ?: $this->now;
            $service->delay_cancelled_at = null;
            $service->is_active = false;

            Event::fire('responsiv.subscribe.serviceCancelled', $service);

            $this->invoiceManager->voidUnpaidService($service);
        }
        /*
         * Cancel at a future date
         */
        else {
            $service->delay_cancelled_at = $cancelDay ?: $this->now;
        }

        $service->save();
    }

    /**
     * Complete service
     */
    public function completeService(ServiceModel $service, $comment = null)
    {
        $status = StatusModel::getStatusComplete();
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
