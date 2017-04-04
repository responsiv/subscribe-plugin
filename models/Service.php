<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;
use ApplicationException;

/**
 * Service Model
 */
class Service extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_services';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Rules
     */
    public $rules = [
        'membership' => 'required',
        'plan' => 'required',
    ];

    /**
     * @var array The attributes that should be mutated to dates.
     */
    protected $dates = [
        'original_period_start',
        'original_period_end',
        'current_period_start',
        'current_period_end',
        'next_assessment_at',
        'status_updated_at',
        'activated_at',
        'cancelled_at',
        'expired_at',
        'delay_activated_at',
        'delay_cancelled_at',
        'notification_sent_at'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'invoice'       => Invoice::class,
        'invoice_item'  => InvoiceItem::class,
        'membership'    => Membership::class,
        'plan'          => Plan::class,
        'status'        => Status::class,
    ];

    public $morphMany = [
        'invoice_items' => [InvoiceItem::class, 'name' => 'related'],
    ];

    public $morphTo = [
        'related' => []
    ];

    public static function createForMembership(Membership $membership, Plan $plan, Invoice $invoice = null)
    {
        $service = static::firstOrCreate([
            'plan_id' => $plan->id,
            'membership_id' => $membership->id
        ]);

        $service->setRelation('plan', $plan);
        $service->setRelation('membership', $membership);

        $service->initService([
            'membership' => $membership,
            'invoice' => $invoice,
            'plan' => $plan,
        ]);

        return $service;
    }

    public function initService($options = [])
    {
        extract(array_merge([
            'membership' => null,
            'invoice' => null,
            'plan' => null,
        ], $options));

        if (!$plan = $plan ?: $this->plan) {
            throw new ApplicationException('Service is missing a plan!');
        }

        if (!$membership = $membership ?: $this->membership) {
            throw new ApplicationException('Service is missing a membership!');
        }

        if (!$invoice) {
            $invoice = $membership->raiseInvoice();
        }

        if ($plan->hasSetupPrice()) {
            $this->raiseInvoiceSetupFee($invoice, $plan->getSetupPrice());
        }

        if ($plan->hasMembershipPrice()) {
            $this->membership_price = $plan->getMembershipPrice();
        }

        if ($plan->hasTrialPeriod()) {
            $this->trial_days = $plan->getTrialPeriod();
        }

        if ($plan->hasGracePeriod()) {
            $this->grace_days = $plan->getGracePeriod();
        }

        $this->invoice = $invoice;
        $this->invoice_item = $this->raiseInvoiceItem($invoice);
        $this->name = $plan->name;
        $this->price = $plan->price;
        $this->setup_price = $plan->setup_price;
        $this->renewal_period = $plan->renewal_period;

        /*
         * Trial period
         */
        if ($membership->isTrialActive()) {
            $this->startTrialPeriod();
        }
        else {
            $this->status = Status::getStatusNew();
            $this->next_assessment_at = $this->freshTimestamp();
            $this->save();
        }
    }

    public function activateService($comment = null)
    {
        $plan = $this->plan;
        $now = $this->freshTimestamp();
        $activateAt = $this->delay_activated_at ?: $now;

        $currentBillingDate = $plan->getPeriodStartDate($activateAt);
        $nextBillingDate = $plan->getPeriodEndDate($currentBillingDate);

        /*
         * Check if this is a not future activation date
         */
        if ($currentBillingDate <= $now) {
            $this->current_period_start = $this->original_period_start = $currentBillingDate;
            $this->current_period_end = $this->original_period_end = $nextBillingDate;
            $this->activated_at = $now;
            $this->next_assessment_at = $nextBillingDate;
            $this->delay_activated_at = null;
            $this->is_active = true;

            $status = Status::getStatusActive();
            StatusLog::createRecord($status->id, $this, $comment);

            Event::fire('responsiv.subscribe.serviceActivated', $this);
        }
        else {
            $this->delay_activated_at = $currentBillingDate;

            $status = Status::getStatusPending();
            StatusLog::createRecord($status->id, $this, $comment);

            Event::fire('responsiv.subscribe.serviceActivatedLater', $this);
        }

        $this->save();
    }

    /**
     * Check if membership is active
     * @return bool
     */
    public function isActive()
    {
        if (!$this->status) {
            return false;
        }

        if (
            $this->status->code != Status::STATUS_ACTIVE &&
            $this->status->code != Status::STATUS_TRIAL &&
            $this->status->code != Status::STATUS_GRACE
        ) {
            return false;
        }

        return true;
    }

    //
    // Trial period
    //

    /**
     * Trial membership
     */
    public function startTrialPeriod($comment = null)
    {
        if (!$membership = $this->membership) {
            throw new ApplicationException('Service is missing a membership!');
        }

        /*
         * Status log
         */
        $status = Status::getStatusTrial();
        StatusLog::createRecord($status->id, $this, $comment);

        /*
         * Current start and end times
         */
        $this->is_active = true;
        $this->current_period_start = $membership->trial_period_start;
        $this->current_period_end = $membership->trial_period_end;
        $this->next_assessment_at = $membership->trial_period_end;
        $this->save();

        Event::fire('responsiv.subscribe.membershipTrialStarted', $this);
    }

    public function hasGracePeriod()
    {
        return !!$this->grace_days;
    }

    /**
     * Grace membership
     */
    public function startGracePeriod($comment = null)
    {
        $now = $this->freshTimestamp();

        $status = Status::getStatusGrace();
        StatusLog::createRecord($status->id, $this, $comment);

        $graceEnd = $now->addDays($this->grace_days);

        /*
         * Current start and end times
         */
        $this->current_period_start = $now;
        $this->current_period_end = $graceEnd;
        $this->next_assessment_at = $graceEnd;
        $this->save();

        Event::fire('responsiv.subscribe.membershipGraceStarted', $this);

        return true;
    }

    //
    // Renew membership
    //

    /**
     * Renew membership
     */
    public function renewService()
    {
        if (!$this->canRenewService()) {
            return false;
        }

        $now = $this->freshTimestamp();
        $startDate = $this->original_period_end;
        $endDate = $this->plan->getPeriodEndDate($startDate);

        /*
         * New start and end dates
         */
        $this->current_period_start = $this->original_period_start = $startDate;
        $this->current_period_end = $this->original_period_end = $endDate;

        /*
         * Add the renewal
         */
        $this->count_renewal++;

        /*
         * Next assessment to today to capture billing
         */
        $this->next_assessment_at = $now;
        $this->save();

        return true;
    }

    public function hasPeriodEnded()
    {
        return $this->current_period_end &&
            $this->current_period_end <= $this->freshTimestamp();
    }

    /**
     * Can the membership be renewed
     */
    public function canRenewService()
    {
        /*
         * Does this membership renew
         */
        if (!$this->plan->isRenewable()) {
            return false;
        }

        /*
         * Missing end date
         */
        if (!$this->current_period_end) {
            return false;
        }

        /*
         * Order cancelled
         */
        if ($this->cancelled_at) {
            return false;
        }

        /*
         * No renew when on trial
         */
        $trialStatus = Status::getStatusTrial();
        if ($this->status_id == $trialStatus->id) {
            return false;
        }

        /*
         * Membership has another billing period
         */
        $endDate = $this->plan->getPeriodEndDate($this->current_period_end);
        if (!$endDate) {
            return false;
        }

        return true;
    }

    //
    // Utils
    //

    /*
     * No payment - default dunning strategy
     */
    public function noPayment($comment = null)
    {
        $status = Status::getStatusPastDue();
        StatusLog::createRecord($status->id, $this, $comment);
    }

    /**
     * Cancels this service, either from a specified date or immediately.
     */
    public function cancelService($fromDate = null, $atTermEnd = null, $comment = null)
    {
        $now = $this->freshTimestamp();
        $cancelDay = null;

        if ($fromDate) {
            $cancelDay = $fromDate;
        }
        elseif ($atTermEnd && $this->current_period_end) {
            $cancelDay = $this->current_period_end;
        }

        $isFuture = $cancelDay ? $cancelday > $current : false;

        /*
         * Not a future cancellation, cancel it now
         */
        if (!$isFuture) {

            $status = Status::getStatusCancelled();
            StatusLog::createRecord($status->id, $this, $comment);

            $this->cancelled_at = $cancelDay ?: $now;
            $this->delay_cancelled_at = null;
            $this->next_assessment_at = null;

            Event::fire('responsiv.subscribe.serviceCancelled', $this);
        }
        /*
         * Cancel at a future date
         */
        else {
            $this->delay_cancelled_at = $cancelDay ?: $now;
        }

        $this->save();
    }

    /**
     * Complete service
     */
    public function completeService($comment = null)
    {
        $now = $this->freshTimestamp();

        $status = Status::getStatusComplete();
        StatusLog::createRecord($status->id, $this, $comment);

        /*
         * Completed date
         */
        $this->expired_at = $now;
        $this->current_period_start = null;
        $this->current_period_end = null;
        $this->trial_period_start = null;
        $this->trial_period_end = null;

        /*
         * No longer collect payments
         */
        $this->next_assessment_at = null;
        $this->save();

        Event::fire('responsiv.subscribe.serviceCompleted', $this);

        return true;
    }

    //
    // Invoicing
    //

    public function hasUnpaidInvoices()
    {
        return InvoiceItem::applyRelated($this)->whereHas('invoice', function($q) {
            $q->applyUnpaid();
        })->count() > 0;
    }

    /**
     * Populates an invoices items, returns the primary item.
     */
    public function raiseInvoiceItem(Invoice $invoice)
    {
        if (!$plan = $this->plan) {
            throw new ApplicationException('Membership is missing a plan!');
        }

        $item = InvoiceItem::applyRelated($this)
            ->applyInvoice($invoice)
            ->first()
        ;

        if ($item) {
            return $item;
        }

        $item = new InvoiceItem;
        $item->invoice = $invoice;
        $item->quantity = 1;
        $item->tax_class_id = $plan->tax_class_id;
        $item->price = $plan->price;
        $item->description = $plan->name;
        $item->related = $this;
        $item->save();

        return $item;
    }

    public function raiseInvoiceSetupFee(Invoice $invoice, $price)
    {
        $item = new InvoiceItem;
        $item->invoice = $invoice;
        $item->quantity = 1;
        $item->price = $price;
        $item->description = 'Set up fee';
        $item->save();

        return $item;
    }

    /**
     * Receive a payment
     */
    public function receivePayment($invoice, $item, $comment = null)
    {
        $statusCode = $this->status ? $this->status->code : null;

        // Non strict @todo
        // if ($statusCode == Status::STATUS_TRIAL) {
        //     $this->delay_activated_at = trial_ends_at
        // }

        // Strict trial policy
        if ($statusCode == Status::STATUS_NEW || $statusCode == Status::STATUS_TRIAL) {
            $this->count_renewal = 1;
            $this->activateService();
        }
        elseif ($statusCode == Status::STATUS_GRACE) {
            $this->renewService();

            /*
             * Active status
             */
            $status = Status::getStatusActive();
            $this->setRelation('status', $status);
            StatusLog::createRecord($status->id, $this, $comment);
        }
        elseif ($statusCode == Status::STATUS_ACTIVE && $this->hasPeriodEnded()) {
            $this->renewService();
        }
        elseif (
            $statusCode == Status::STATUS_PASTDUE &&
            $this->count_fail &&
            $this->count_fail > 0
        ) {
            /*
             * Make service active
             */
            $status = Status::getStatusActive();
            $this->setRelation('status', $status);
            StatusLog::createRecord($status->id, $this, $comment);

            /*
             * Change next assessment to period end date if available
             */
            $this->is_active = true;
            $this->next_assessment_at = $this->current_period_end
                ? $this->current_period_end
                : null;

            $this->save();
        }
    }

    //
    // Schedule
    //

    /**
     * Gets upcoming schedule
     */
    public function getSchedule()
    {
        $schedules = [];

        $graceStatus = Status::getStatusGrace();

        $currentStart = $this->current_period_start;

        if ($this->status->id == $graceStatus->id) {
            $currentEnd = $this->current_period_start;
        }
        else {
            $currentEnd = $this->current_period_end;
        }

        $start = $this->count_renewal ? $this->count_renewal + 1 : 1;

        if ($this->plan->plan_type == Plan::TYPE_LIFETIME) {
            return $schedules;
        }

        if ($this->plan->plan_type == Plan::TYPE_YEARLY) {
            $visible = 5;
        }
        elseif ($this->plan->plan_type == Plan::TYPE_MONTHLY) {
            $visible = 14;
        }
        elseif ($this->plan->plan_type == Plan::TYPE_DAILY) {
            $visible = $this->plan->plan_day_interval <= 15 ? 24 : 18;
        }

        $adjustments = Schedule::where('membership_id', $this->id)
            ->where('billing_period', '>=', $start)
            ->get()
            ->lists(null, 'billing_period')
        ;

        for ($i = $start; $i <= ($start + $visible); $i++) {

            $schedule = new \stdClass;
            $currentStart = $currentEnd;
            $currentEnd = $this->plan->getPeriodEndDate($currentEnd);

            if (!$currentEnd) {
                break;
            }

            if ($this->delay_cancelled_at && $currentStart >= $this->delay_cancelled_at) {
                break;
            }

            if ($this->plan->renewal_period && $i > $this->plan->renewal_period) {
                break;
            }

            $comment = '';
            $adjusted = false;
            $total = $this->plan ? $this->plan->price : 0;

            if (isset($adjustments[$i])) {
                $comment = $adjustments[$i]->comment;
                $adjusted = true;
                $total = $adjustments[$i]->price;
            }

            $schedule->period = $i;
            $schedule->period_start = $currentStart;
            $schedule->period_end = $currentEnd;
            $schedule->total = $total;
            $schedule->comment = $comment;
            $schedule->adjusted = $adjusted;

            $schedules[] = $schedule;
        }

        return $schedules;
    }

    //
    // Scopes
    //

    public function scopeApplyActive($query)
    {
        return $query->where('is_active', true);
    }
}
