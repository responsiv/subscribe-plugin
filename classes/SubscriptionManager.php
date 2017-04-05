<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Pay\Models\InvoiceStatus;
use Exception;

/**
 * Subscription engine
 */
class SubscriptionManager
{
    use \October\Rain\Support\Traits\Singleton;

    protected $serviceInvoiceCache = [];

    public function invoiceAfterPayment($invoice)
    {
        if (!$service = $this->getServiceFromInvoice($invoice)) {
            return;
        }

        $service->receivePayment($invoice);
    }

    public function attemptRenewService($service)
    {
        /*
         * Raise a new invoice
         */
        $invoice = $this->getInvoiceFromService($service);

        $service->raiseInvoiceItem($invoice);

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);

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
                    $service->startGracePeriod('Automatic payment failed');
                }
                else {
                    $service->pastDueService('Automatic payment failed');
                }
            }
        }
    }

    //
    // Internals
    //

    protected function getServiceFromInvoice($invoice)
    {
        if (
            $invoice &&
            $invoice->related &&
            $invoice->related instanceof ServiceModel
        ) {
            return $invoice->related;
        }

        return null;
    }

    protected function getInvoiceFromService($service)
    {
        $id = $service->id;

        if (isset($this->serviceInvoiceCache[$id])) {
            return $this->serviceInvoiceCache[$id];
        }

        $invoice = $service->raiseInvoice();

        return $this->serviceInvoiceCache[$id] = $invoice;
    }

    public function clearCache()
    {
        $this->serviceInvoiceCache = [];
    }
}
