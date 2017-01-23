<?php namespace Responsiv\Subscribe\Classes;

use Carbon\Carbon;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;

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

    public function renewServiceWithInvoice($service)
    {
        $membership = $service->membership;

        $service->renewService();

        $invoice = $this->getInvoiceFromMembership($membership);

        $service->raiseInvoiceItem($invoice);

        $invoice = $invoice->reload();

        /*
         * If invoice is for $0, process a fake payment
         */
        if ($invoice->total <= 0) {
            $invoice->submitManualPayment('Invoice total is zero');
        }
    }
}
