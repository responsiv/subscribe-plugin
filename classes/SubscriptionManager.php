<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Pay\Models\InvoiceStatus;
use Exception;

/**
 * Subscription engine
 */
class SubscriptionManager
{
    use \October\Rain\Support\Traits\Singleton;

    protected $membershipInvoiceCache = [];

    public function invoiceAfterPayment($invoice)
    {
        if (!$membership = $this->getMembershipFromInvoice($invoice)) {
            return;
        }

        $membership->receivePayment($invoice);
    }

    public function attemptRenewService($service)
    {
        /*
         * Raise a new invoice
         */
        $membership = $service->membership;

        $invoice = $this->getInvoiceFromMembership($membership);

        $service->raiseInvoiceItem($invoice);

        $invoice = $invoice->reload();
        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->touchTotals();

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
                    $service->noPayment('Automatic payment failed');
                }
            }
        }
    }

    //
    // Internals
    //

    protected function getMembershipFromInvoice($invoice)
    {
        if (
            $invoice &&
            $invoice->related &&
            $invoice->related instanceof MembershipModel
        ) {
            return $invoice->related;
        }

        return null;
    }

    protected function getInvoiceFromMembership($membership)
    {
        $id = $membership->id;

        if (isset($this->membershipInvoiceCache[$id])) {
            return $this->membershipInvoiceCache[$id];
        }

        $invoice = $membership->raiseInvoice();

        return $this->membershipInvoiceCache[$id] = $invoice;
    }

}
