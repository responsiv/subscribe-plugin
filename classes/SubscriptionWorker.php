<?php namespace Responsiv\Subscribe\Classes;

use Carbon\Carbon;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Subscribe\Models\Schedule as ScheduleModel;
use ApplicationException;
use Exception;

/**
 * Worker class, engaged by the automated worker
 */
class SubscriptionWorker
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Responsiv\Campaign\Classes\SubscriptionManager
     */
    protected $subscriptionManager;

    /**
     * @var bool There should be only one task performed per execution.
     */
    protected $isReady = true;

    /**
     * @var string Processing message
     */
    protected $logMessage = 'There are no outstanding activities to perform.';

    /**
     * @var Carbon\Carbon
     */
    protected $now;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->subscriptionManager = SubscriptionManager::instance();
        $this->now = Carbon::now();
    }

    /*
     * Process all tasks
     */
    public function process()
    {
        $this->isReady = true;

        $methods = [
            'processMemberships',
            'processAutoBilling'
        ];

        shuffle($methods);

        foreach ($methods as $method) {
            $this->isReady && $this->$method();
        }

        return $this->logMessage;
    }

    /**
     * Generate invoices
     * This will list all memberships, sorted by the last process date,
     * where the last process date exceed the specified frequency,
     * finds memberships that require servicing and generates invoices.
     *
     * Default frequency: 12 hours
     */
    public function processMemberships()
    {
        $hours = 4;  // Every 4 hours
        $loop = 100; // Process 100 at a time

        $start = Carbon::now()->subHours($hours);

        $count = 0;
        for ($i = 0; $i < $loop; $i++) {
            $membership = MembershipModel::make()
                ->where(function($q) use ($start) {
                    $q->where('last_process_at', '<', $start);
                    $q->orWhereNull('last_process_at');
                })
                ->first()
            ;

            if ($membership) {
                $this->processMembership($membership);
                $count++;

                $membership->last_process_at = $this->now;
                $membership->timestamps = false;
                $membership->forceSave();
            }
        }

        if ($count > 0) {
            $this->logActivity(sprintf(
                'Processed %s membership(s).',
                $count
            ));
        }
    }

    protected function processMembership($membership)
    {
        foreach ($membership->services as $service) {

            if ($this->checkServiceCancelled($service)) {
                continue;
            }

            if ($this->checkServiceDelayed($service)) {
                continue;
            }

            if ($this->checkRenewalPeriod($service)) {
                continue;
            }

            if ($this->checkServiceRenew($service)) {
                continue;
            }

            // Service is A-OK
        }
    }

    /*
     * Service cancelled
     */
    protected function checkServiceCancelled($service)
    {
        if ($service->cancelled_at) {
            return true;
        }

        if ($service->delay_cancelled_at && $service->delay_cancelled_at <= $this->now) {
            $service->cancelService(null, null, 'Cancelled on delayed cancellation date');
            return true;
        }

        return false;
    }

    /*
     * Service delayed
     */
    protected function checkServiceDelayed($service)
    {
        if ($service->delay_activated_at && $service->delay_activated_at > $this->now) {
            return true;
        }

        if (
            $service->delay_activated_at &&
            $service->delay_activated_at <= $this->now
        ) {
            $service->count_renewal = 1;
            $service->activateService();
            return true;
        }

        return false;
    }

    /*
     * Renewal period reached
     */
    protected function checkRenewalPeriod($service)
    {
        if ($service->renewal_period && $service->renewal_period >= $service->count_renewal) {
            $service->completeService('Renewal period reached');
            return true;
        }

        return false;
    }

    /*
     * Service up for renewal
     */
    protected function checkServiceRenew($service)
    {
        if (!$service->hasPeriodEnded()) {
            return false;
        }

        $statusCode = $service->status ? $service->status->code : null;

        /*
         * Grace ended
         */
        if ($statusCode == StatusModel::STATUS_GRACE) {
            $service->pastDueService('Grace ended');
        }
        /*
         * Trial ended
         */
        elseif ($statusCode == StatusModel::STATUS_TRIAL) {
            $service->pastDueService('Trial ended');
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
                $this->subscriptionManager->attemptRenewService($service);
            }
            elseif ($allPaid) {
                $service->completeService('Does not renew');
            }
            else {
                // Not sure how it would end up here
                return false;
            }
        }

        return true;
    }

    /**
     * Attempt to pay invoices using payment profile
     */
    public function processAutoBilling()
    {

    }

    /**
     * Called when activity has been performed.
     */
    protected function logActivity($message)
    {
        $this->logMessage = $message;
        $this->isReady = false;
    }

}
