<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Subscribe\Models\Setting as SettingModel;
use Responsiv\Subscribe\Models\StatusLog as StatusLogModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceStatus as InvoiceStatusModel;
use Exception;

/**
 * Subscription engine
 */
class SubscriptionEngine
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Responsiv\Campaign\Classes\ServiceManager
     */
    protected $serviceManager;

    /**
     * @var Responsiv\Campaign\Classes\MembershipManager
     */
    protected $membershipManager;

    /**
     * @var Responsiv\Campaign\Classes\InvoiceManager
     */
    protected $invoiceManager;

    /**
     * @var Carbon\Carbon
     */
    protected $now;

    protected $serviceInvoiceCache = [];

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->serviceManager = ServiceManager::instance();
        $this->membershipManager = MembershipManager::instance();
        $this->invoiceManager = InvoiceManager::instance();
        $this->now = Carbon::now();
    }

    public function reset()
    {
        $this->serviceInvoiceCache = [];
        $this->now(Carbon::now());
    }

    public function now($value = null)
    {
        if ($value) {
            $this->serviceManager->now = $value;
            $this->membershipManager->now = $value;
            $this->invoiceManager->now = $value;
            $this->now = $value;
        }

        return $this->now;
    }

    //
    // Hooks
    //

    public function invoiceAfterPayment(InvoiceModel $invoice)
    {
        if (
            $invoice &&
            $invoice->related &&
            $invoice->related instanceof ServiceModel
        ) {
            $this->receivePayment($invoice->related, $invoice);
        }
    }

    //
    // Services
    //

    /**
     * Receive a payment
     */
    public function receivePayment(ServiceModel $service, InvoiceModel $invoice, $comment = null)
    {
        $statusCode = $service->status ? $service->status->code : null;

        // Never allow a paid service to be thrown away
        if ($service->is_throwaway) {
            $service->is_throwaway = false;
            $service->save();
        }

        // Include trial as part of the first period
        if ($statusCode == StatusModel::STATUS_TRIAL) {
            $isTrialInclusive = $service->plan ? $service->plan->isTrialInclusive() : false;
            if ($isTrialInclusive) {
                $service->delay_activated_at = $service->current_period_end;
            }

            $this->serviceManager->activateService($service);
        }
        elseif ($statusCode == StatusModel::STATUS_NEW) {
            $this->serviceManager->activateOrDelayService($service);
        }
        elseif ($statusCode == StatusModel::STATUS_GRACE) {
            $this->serviceManager->renewService($service);

            if ($service->hasServicePeriodEnded()) {
                $this->attemptRenewService($service);
            }
        }
        elseif ($statusCode == StatusModel::STATUS_ACTIVE) {
            $this->serviceManager->renewService($service);
        }
    }

    /**
     * Called at the end of a service period, this will raise an invoice, if not
     * existing already, and try to pay it.
     */
    public function attemptRenewService(ServiceModel $service)
    {
        /*
         * Raise a new invoice
         */
        $invoice = $this->invoiceManager->raiseServiceRenewalInvoice($service);

        /*
         * Attempt to pay the invoice automatically, otherwise the service
         * enters grace period or is cancelled via past due.
         */
        if (!$this->invoiceManager->attemptAutomaticPayment($invoice)) {
            /*
             * Grace period
             */
            if ($service->hasGracePeriod()) {
                $this->serviceManager->startGracePeriod($service, 'Automatic payment failed');
            }
            /*
             * Past due / Cancelled
             */
            else {
                $this->serviceManager->pastDueService($service, 'Automatic payment failed');
            }
        }
    }

    //
    // Plan hopping
    //

    public function switchPlan(MembershipModel $membership, PlanModel $plan)
    {
        /*
         * Found active service, cancel it
         */
        if ($activeService = $membership->getActivePlan()) {
            $this->serviceManager->cancelServiceNow($activeService);
        }

        /*
         * Subscribe to new service.
         */
        $service = ServiceModel::createForMembership($membership, $plan);

        return $service;
    }
}
