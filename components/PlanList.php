<?php namespace Responsiv\Subscribe\Components;

use Cms\Classes\ComponentBase;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Subscribe\Models\Plan as PlanModel;

class PlanList extends ComponentBase
{

    protected $plans;

    public function componentDetails()
    {
        return [
            'name'        => 'Plan List Component',
            'description' => 'List all available subscription plans'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function plans()
    {
        if ($this->plans !== null) {
            return $this->plans;
        }

        return $this->plans = PlanModel::all();
    }

    public function hasPaidPlans()
    {
        foreach ($this->plans() as $plan) {
            if (!$plan->isFree()) {
                return true;
            }
        }

        return false;
    }

    public function dailyPlans()
    {
        return $this->plans()->where('plan_type', PlanModel::TYPE_DAILY);
    }

    public function monthlyPlans()
    {
        return $this->plans()->where('plan_type', PlanModel::TYPE_MONTHLY);
    }

    public function yearlyPlans()
    {
        return $this->plans()->where('plan_type', PlanModel::TYPE_YEARLY);
    }

    public function lifetimePlans()
    {
        return $this->plans()->where('plan_type', PlanModel::TYPE_LIFETIME);
    }


    //
    // Plan selection
    //

    public function onGetPlanDetails()
    {
        $this->page['plan'] = $this->getPlan();
    }

    protected function getPlan($planId = null)
    {
        if (!$planId) {
            $planId = post('selected_plan');
        }

        if (!$planId) {
            return;
        }

        if ($plan = PlanModel::find($planId)) {
            $this->setLocationInfoOnPlan($plan);
        }

        return $plan;
    }

    protected function setLocationInfoOnPlan($plan)
    {
        if (!$countryId = post('country')) {
            return;
        }

        $location = new TaxLocation;

        $location->countryId = $countryId;

        if ($taxClass = $plan->getTaxClass()) {
            $taxClass->setLocationInfo($location);
        }
    }

}
