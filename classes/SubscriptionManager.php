<?php namespace Responsiv\Subscribe\Classes;

use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;

/**
 * Subscription engine
 */
class SubscriptionManager
{
    use \October\Rain\Support\Traits\Singleton;

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
}
