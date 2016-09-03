<?php namespace Responsiv\Subscribe\Components;

use Cms\Classes\ComponentBase;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Pay\Models\Tax as TaxModel;
use Responsiv\Pay\Classes\TaxLocation;

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

        return $this->plans = PlanModel::with('policy')->get();
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

    public function onGetPlan()
    {
        if (!$planId = post('id')) {
            return;
        }

        $this->page['plan'] = PlanModel::find($planId);
    }

    public function getLocationInfo()
    {
        $location = new TaxLocation;

        $location->countryId = post('country_id');

        return $location;
    }

}
