<?php namespace Responsiv\Subscribe\Classes;

use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;

/**
 * Subscription engine
 */
class SubscriptionManager
{
    use \October\Rain\Support\Traits\Singleton;

    public function invoiceAfterCreated($invoice)
    {
        if (!$membership = $this->getMembershipFromInvoice($invoice)) {
            return;
        }

        $this->configureMembership($membership, $invoice);
    }

    public function invoiceAfterPayment($invoice)
    {
        if (!$membership = $this->getMembershipFromInvoice($invoice)) {
            return;
        }

        $membership->receivePayment($invoice);

        $this->configureMembership($membership, $invoice);
    }

    public function configureMembership($membership, $invoice)
    {
        if ($invoice->isPaid()) {
            $membership->activateMembership();
            $membership->renewal_period = 1;
        }
        else {
            $membership->status = StatusModel::getStatusPending();
            $membership->next_assessment = $membership->freshTimestamp();
        }

        $membership->save();
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
