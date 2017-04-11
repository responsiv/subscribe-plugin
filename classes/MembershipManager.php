<?php namespace Responsiv\Subscribe\Classes;

use Db;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Subscribe\Models\Status as StatusModel;
use Responsiv\Subscribe\Models\StatusLog as StatusLogModel;
use Responsiv\Subscribe\Models\Service as ServiceModel;
use Responsiv\Subscribe\Models\Setting as SettingModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Pay\Models\InvoiceStatus as InvoiceStatusModel;
use ApplicationException;
use Exception;

/**
 * Membership engine
 */
class MembershipManager
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

    public function initMembership(MembershipModel $membership, $options = [])
    {
        extract(array_merge([
            'plan' => null,
            'guest' => false
        ], $options));

        if (!$plan) {
            throw new ApplicationException('Membership is missing a plan!');
        }

        if (!$membership->isTrialUsed() && $plan->hasTrialPeriod()) {
            $this->setTrialPeriodFromPlan($membership, $plan);
        }

        $service = ServiceModel::createForMembership($membership, $plan);

        $invoice = $service->invoice;

        if ($plan->hasMembershipPrice()) {
            $this->invoiceManager->raiseMembershipFee(
                $invoice,
                $membership,
                $plan->getMembershipPrice()
            );
        }

        $invoice->updateInvoiceStatus(InvoiceStatusModel::STATUS_APPROVED);

        $invoice->touchTotals();

        $membership->save();
    }

    //
    // Trial period
    //

    protected function setTrialPeriodFromPlan(MembershipModel $membership, PlanModel $plan)
    {
        $current = clone $this->now;
        $trialDays = $plan->getTrialPeriod();

        $membership->is_trial_used = true;
        $membership->trial_period_start = $current;
        $membership->trial_period_end = $current->addDays($trialDays);
    }
}
