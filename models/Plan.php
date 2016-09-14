<?php namespace Responsiv\Subscribe\Models;

use Str;
use Model;
use Responsiv\Pay\Models\Tax as TaxModel;

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
        'renewal_period' => 'numeric',
        'plan_day_interval' => 'numeric',
        'plan_month_day' => 'numeric',
        'plan_month_interval' => 'numeric',
        'plan_year_interval' => 'numeric',
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
     * @var array List of attribute names which are json encoded and decoded from the database.
     */
    protected $jsonable = ['features'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'policy' => 'Responsiv\Subscribe\Models\Policy',
        'tax_class' => 'Responsiv\Pay\Models\Tax',
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

    //
    // Options
    //

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

    //
    // Utils
    //

    public function isFree()
    {
        return $this->price == 0;
    }

    public function hasTrial()
    {
        return $this->policy && $this->policy->trial_period > 0;
    }

    public function hasGracePeriod()
    {
        return $this->policy && $this->policy->grace_period > 0;
    }

    public function getTaxClass()
    {
        if (!$this->tax_class) {
            $this->setRelation('tax_class', TaxModel::getDefault());
        }

        return $this->tax_class;
    }

    //
    // Attributes
    //

    public function getPriceWithTaxAttribute()
    {
        return $this->getTaxAmountAttribute() + $this->price;
    }

    public function getTaxAmountAttribute()
    {
        return ($taxClass = $this->getTaxClass())
            ? $taxClass->getTotalTax($this->price)
            : 0;
    }

    public function getPlanTypeNameAttribute()
    {
        $message = '';

        if ($this->hasTrial()) {
            $message .= sprintf(
                'Trial period for %s %s then ',
                $this->policy->trial_period,
                Str::plural('day', $this->policy->trial_period)
            );
        }

        if ($this->plan_type == self::TYPE_DAILY) {
            if ($this->plan_day_interval > 1) {
                $message .= sprintf('Renew every %s days', $this->plan_day_interval);
            }
            else {
                $message .= 'Renew every day';
            }
        }
        elseif ($this->plan_type == self::TYPE_MONTHLY) {
            if ($this->plan_monthly_behavior == 'monthly_signup') {
                $message .= sprintf('Renew every %s %s based on the signup date',
                    $this->plan_month_interval,
                    Str::plural('month', $this->plan_month_interval)
                );
            }
            elseif ($this->plan_monthly_behavior == 'monthly_prorate') {
                $message .= sprintf('Renew on the %s of the month and pro-rate billing for used time', Str::ordinal($this->plan_month_day));
            }
            elseif ($this->plan_monthly_behavior == 'monthly_free') {
                $message .= sprintf('Renew on the %s of the month and do not bill until the renewal date', Str::ordinal($this->plan_month_day));
            }
            elseif ($this->plan_monthly_behavior == 'monthly_none') {
                $message .= sprintf('Renew on the %s of the month and do not start the subscription until the renewal date', Str::ordinal($this->plan_month_day));
            }
        }
        elseif ($this->plan_type == self::TYPE_YEARLY) {
            if ($this->plan_year_interval > 1) {
                $message .= sprintf('Renew every %s years', $this->plan_year_interval);
            }
            else {
                $message .= 'Renew every year';
            }
        }
        elseif ($this->plan_type == self::TYPE_LIFETIME) {
            $message .= 'Never renew (lifetime membership)';
        }

        if ($this->plan_type != self::TYPE_LIFETIME && $this->renewal_period > 0) {
            $message .= sprintf(' for %s renewal periods', $this->renewal_period);
        }

        if ($this->hasGracePeriod()) {
            $message .= sprintf(
                ' and Grace period for %s %s',
                $this->policy->grace_period,
                Str::plural('day', $this->policy->grace_period)
            );
        }

        return $message;
    }

}