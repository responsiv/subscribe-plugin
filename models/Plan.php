<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * Plan Model
 */
class Plan extends Model
{
    use \October\Rain\Database\Traits\Validation;

    const TYPE_DAILY = 'daily';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_YEARLY = 'yearly';
    const TYPE_LIFETIME = 'lifetime';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_plans';

    public $rules = [
        'name' => 'required',
        'price' => 'required|numeric',
        'plan_day_interval' => 'numeric',
        'plan_month_day' => 'numeric',
        'plan_month_interval' => 'numeric',
        'plan_year_interval' => 'numeric',
        'renewal_period' => 'numeric',
        'grace_period' => 'numeric',
        'trial_period' => 'numeric',
        'invoice_advance_days' => 'numeric',
        'invoice_advance_days_interval' => 'numeric',
    ];

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'dunning_plan' => 'Responsiv\Subscribe\Models\DunningPlan'
    ];

    public function filterFields($fields, $context = null)
    {
        $planType = $this->plan_type ?: self::TYPE_MONTHLY;

        if ($planType != self::TYPE_MONTHLY) {
            $fields->plan_month_interval->hidden = true;
            $fields->plan_monthly_behavior->hidden = true;
            $fields->plan_month_day->hidden = true;
        }
        
        if ($planType != self::TYPE_DAILY) {
            $fields->plan_day_interval->hidden = true;
        }

        if ($planType != self::TYPE_YEARLY) {
            $fields->plan_year_interval->hidden = true;
        }

        if ($planType != self::TYPE_LIFETIME) {
            $fields->renewal_period->hidden = true;
        }

        if ($this->plan_monthly_behavior == 'monthly_signup') {
            $fields->plan_month_day->hidden = true;
        }
    }

    public function getPlanTypeOptions()
    {
        return [
            self::TYPE_DAILY    => 'Daily',
            self::TYPE_MONTHLY  => 'Monthly',
            self::TYPE_YEARLY   => 'Yearly',
            self::TYPE_LIFETIME => 'Lifetime'
        ];
    }

    public function getPlanMonthlyBehaviorOptions()
    {
        return [
            'monthly_signup'  => ['Signup Date', 'Renew subscription every X months based on the signup date. For example if someone signs up on the 14th, the subscription will renew every month on the 14th.'],
            'monthly_prorate' => ['Pro-Rated', 'Renew subscription the same day every X months and pro-rate billing for used time. For example if someone signs up on the 14th and your subscription renewal is on the 1st, they will be billed for 16 days at the start of the subscription.'],
            'monthly_free'    => ['Free Days', 'Renew subscription the same day every X months and do not bill until the renewal date. For example if someone signs up on the 14th and your subscription renewal is on the 1st, they will have free access for 16 days until renewal starts on the 1st.'],
            'monthly_none'    => ['Dont Start', 'Renew subscription the same day every X months and do not start the subscription until renewal date. For example if someone signs up on the 14th and your subscription renewal is on the 1st, do not start the subscription until 1st.'],
        ];
    }

    public function getPlanMonthDayOptions()
    {
        $result = [];

        for ($i = 1; $i <= 31; $i++) {
            $result[$i] = $i;
        }

        return $result;
    }

}