<?php namespace Responsiv\Subscribe\Classes;

use Carbon\Carbon;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceStatusLog;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Subscribe\Models\Schedule as ScheduleModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use ApplicationException;
use Exception;

/**
 * Worker class, engaged by the automated worker
 */
class SubscriptionWorker
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Carbon\Carbon
     */
    public $now;

    /**
     * @var bool There should be only one task performed per execution.
     */
    protected $isReady;

    /**
     * @var string Processing message
     */
    protected $logMessage;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->now = Carbon::now();
    }

    /*
     * Process all tasks
     */
    public function process($method = null)
    {
        $this->isReady = true;
        $this->logMessage = 'There are no outstanding activities to perform';

        $methods = (array) $method ?: [
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
    protected function processMemberships()
    {
        $hours = 4;  // Every 4 hours
        $loop = 100; // Process 100 at a time

        $start = clone $this->now;
        $start = $start->subHours($hours);

        $count = 0;
        for ($i = 0; $i < $loop; $i++) {
            $membership = MembershipModel::make()
                ->where(function($q) use ($start) {
                    $q->where('last_process_at', '<', $start);
                    $q->orWhereNull('last_process_at');
                })
                ->first()
            ;

            if (!$membership) {
                break;
            }

            $this->processMembership($membership);
            $count++;

            $membership->last_process_at = $this->now;
            $membership->timestamps = false;
            $membership->forceSave();
        }

        if ($count > 0) {
            $this->logActivity(sprintf(
                'Processed services for %s membership(s)',
                $count
            ));
        }
    }

    protected function processMembership($membership)
    {
        $manager = ServiceManager::instance();

        foreach ($membership->services as $service) {

            if ($manager->checkServiceCancelled($service)) {
                continue;
            }

            if ($manager->checkServiceDelayed($service)) {
                continue;
            }

            if ($manager->checkRenewalPeriod($service)) {
                continue;
            }

            if ($manager->checkPeriodEnded($service)) {
                continue;
            }

            if ($manager->checkAdvanceInvoice($service)) {
                continue;
            }

            // Service is A-OK
        }
    }

    /**
     * Attempt to pay invoices using payment profile
     */
    protected function processAutoBilling()
    {
        $loop = 10; // Process 10 at a time
        $status = StatusModel::getStatusActive();
        $engine = SubscriptionEngine::instance();

        $count = 0;
        for ($i = 0; $i < $loop; $i++) {
            $service = ServiceModel::make()
                ->where('status_id', $status->id)
                ->where('service_period_end', '<=', $this->now)
                ->first();

            if (!$service) {
                break;
            }

            if ($service->hasUnpaidInvoices()) {
                $engine->attemptRenewService($service);
                $count++;
            }
        }

        if ($count > 0) {
            $this->logActivity(sprintf(
                'Processed billing for %s membership(s)',
                $count
            ));
        }
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
