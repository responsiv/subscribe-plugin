<?php namespace Responsiv\Subscribe\Models;

use Str;
use Carbon\Carbon;
use Model;
use Responsiv\Pay\Models\Tax;
use Responsiv\Pay\Models\InvoiceItem;

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
        return $this->is_trial;
    }

    public function hasGracePeriod()
    {
        // @todo Pull from settings or custom membership
        return true;
    }

    public function getGracePeriod()
    {
        // @todo Pull from settings or custom membership
        return 0;
    }

    public function getTaxClass()
    {
        if (!$this->tax_class) {
            $this->setRelation('tax_class', Tax::getDefault());
        }

        return $this->tax_class;
    }

    /**
     * Whether or not this plan can renew
     */
    public function isRenewable()
    {
        return $this->plan_type != self::TYPE_LIFETIME;
    }

    //
    // Attributes
    //

    public function getTotalAttribute()
    {
        $setup = $this->setup_price ?: 0;

        return $this->price + $setup;
    }

    public function getTotalWithTaxAttribute()
    {
        return $this->total + $this->total_tax;
    }

    public function getTotalTaxAttribute()
    {
        return ($taxClass = $this->getTaxClass())
            ? $taxClass->getTotalTax($this->total)
            : 0;
    }

    public function getSetupPriceWithTaxAttribute()
    {
        return $this->setup_price + $this->setup_tax;
    }

    public function getSetupTaxAttribute()
    {
        return ($taxClass = $this->getTaxClass())
            ? $taxClass->getTotalTax($this->setup_price)
            : 0;
    }

    public function getPriceWithTaxAttribute()
    {
        return $this->getTaxAmountAttribute() + $this->price;
    }

    public function getPriceTaxAttribute()
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
                $this->trial_days,
                Str::plural('day', $this->trial_days)
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
                $this->getGracePeriod(),
                Str::plural('day', $this->getGracePeriod())
            );
        }

        return $message;
    }

    //
    // Date calculations
    //

    /*
     * Get start date for selected plan
     */
    public function getPeriodStartDate($current = null)
    {
        if (!$current) {
            $current = $this->freshTimestamp();
        }

        $result = clone $current;

        if ($this->hasTrial()) {
            $result = $result->addDays($this->trial_days);
        }

        if ($this->plan_type == self::TYPE_MONTHLY) {
            /*
             * Do not start the subscription until the start renewal period
             */
            if ($this->plan_monthly_behavior == 'monthly_none') {

                $checkEndDay = $this->checkDate($this->plan_month_day, $current->month, $current->year);

                if ($current->day <= $checkEndDay) {
                    $result->day = $checkEndDay;
                }
                else {
                    $result = $result->addMonth();
                    $result->day = $this->checkDate($this->plan_month_day, $result->month, $result->year);
                }
            }
        }

        return $result;
    }

    /*
     * Get end date for selected plan
     */
    public function getPeriodEndDate($start = null)
    {
        if (!$start) {
            $start = $this->freshTimestamp();
        }

        $date = clone $start;
        $result = clone $start;

        switch ($this->plan_type) {
            case self::TYPE_DAILY:
                $result = $date->addDays($this->plan_day_interval);
                break;

            case self::TYPE_MONTHLY:
                $next = clone $date;
                $next->addMonth();

                if ($this->plan_monthly_behavior == 'monthly_signup') {
                    $result->year = $next->year;
                    $result->month = $next->month;
                    $result->day = $this->checkDate($date->day, $next->month, $next->year);
                }
                elseif ($this->plan_monthly_behavior == 'monthly_prorate') {
                    // Get plan end day of this month
                    $checkEndDay = $this->checkDate($this->plan_month_day, $date->month, $date->year);

                    // On the same day
                    if ($day == $checkEndDay) {
                        $result->year = $next->year;
                        $result->month = $next->month;
                        $result->day = $this->checkDate($this->plan_month_day, $next->month, $next->year);
                    }
                    // Is it going to renew this month
                    elseif ($day < $checkEndDay) {
                        $result->year = $date->year;
                        $result->month = $date->month;
                        $result->day = $checkEndDay;
                    }
                    // Passed the renewal, set for next month
                    else {
                        $result->year = $next->year;
                        $result->month = $next->month;
                        $result->day = $this->checkDate($this->plan_month_day, $next->month, $next->year);
                    }
                }
                elseif ($this->plan_monthly_behavior == 'monthly_free' || $this->plan_monthly_behavior == 'monthly_none') {
                    $result->year = $next->year;
                    $result->month = $next->month;
                    $result->day = $this->checkDate($this->plan_month_day, $next->month, $next->year);
                }
                else {
                    throw new ApplicationException('Unknown monthly behavior: '.$this->plan_monthly_behavior);
                }
                break;

            case self::type_yearly:
                $result = $date->addYears($this->plan_year_interval);
                break;

            case self::type_lifetime:
                $result = null;
                break;

            default:
                throw new ApplicationException('Unknown membership plan: '.$this->plan_type);
                break;
        }

        return $result;
    }

    /*
     * Returns first valid day from date
     */
    protected function checkDate($day, $month, $year)
    {
        //all months have less than 28 days
        if ($day <= 28) {
            return $day;
        }

        //check if month has a valid day
        for ($checkDay = $day; $checkDay > 28; $checkDay--) {
            if (checkdate($month, $checkDay, $year)) {
                return $checkDay;
            }
        }
    }

}
