<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;

/**
 * Membership Model
 */
class Membership extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_memberships';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array The attributes that should be mutated to dates.
     */
    protected $dates = [
        'original_period_start',
        'original_period_end',
        'current_period_start',
        'current_period_end',
        'next_assessment',
        'trial_period_start',
        'trial_period_end',
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
        'user'           => ['RainLab\User\Models\User'],
        'invoice'        => ['Responsiv\Pay\Models\Invoice'],
        'invoice_item'   => ['Responsiv\Pay\Models\InvoiceItem'],
        'plan'           => ['Responsiv\Subscribe\Models\Plan'],
        'status'         => ['Responsiv\Subscribe\Models\Status'],
    ];

    public $morphTo = [
        'related' => []
    ];

    public static function createForGuest($user, $plan)
    {
        return static::firstOrCreate([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'is_guest' => 1
        ]);
    }

    /**
     * Receive a payment
     */
    public function receivePayment($invoice, $comment = null)
    {
        if (
            $this->status->code == 'pastdue' &&
            $this->billing_failed &&
            $this->billing_failed >= 1
        ) {
            $outstandingInvoices = Invoice::applyUnpaid()
                ->applyRelated($this)
                ->where('id', '!=', $invoice->id)
                ->count()
            ;

            if (!$outstandingInvoices) {
                $status = Status::getStatusActive();
                StatusLog::createRecord($status->id, $this, $comment);

                $this->status = $status;

                /*
                 * Change next assessment to period end date if available
                 */
                $this->next_assessment = $this->current_period_end
                    ? $this->current_period_end
                    : null;

                $this->save();
            }
        }

        return true;
    }

    /**
     * Activate or reactive a membership
     */
    public function activateMembership($useActivatedAt = true, $comment = null)
    {
        $plan = $this->plan;
        $policy = $plan->getPolicy();

        $current = $this->freshTimestamp();

        if ($policy->trial_period) {
            $currentBillingDate = $plan->getPeriodStartDate($current);
            $nextBillingDate = $currentBillingDate->addDays($policy->trial_period);
        }
        elseif ($useActivatedAt && $this->delay_activated_at) {
            $currentBillingDate = $plan->getPeriodStartDate($this->delay_activated_at);
            $nextBillingDate = $plan->getPeriodEndDate($currentBillingDate);
        }
        else {
            $currentBillingDate = $plan->getPeriodStartDate($current);
            $nextBillingDate = $plan->getPeriodEndDate($currentBillingDate);
        }

        /*
         * Check if this is a not future activation date
         */
        if ($currentBillingDate <= $current) {
            $this->original_period_start = $currentBillingDate;
            $this->original_period_end = $nextBillingDate;
            $this->current_period_start = $currentBillingDate;
            $this->current_period_end = $nextBillingDate;
            $this->activated_at = $current;
            $this->next_assessment = $nextBillingDate;
            $this->delay_activated_at = null;

            $status = Status::getStatusActive();
            StatusLog::createRecord($status->id, $this, $comment);

            Event::fire('responsiv.subscribe.membershipActivated', $this);
        }
        else {
            $this->delay_activated_at = $currentBillingDate;

            $status = Status::getStatusPending();
            $comment = 'Does not start yet';
            StatusLog::createRecord($status->id, $this, $comment);

            Event::fire('responsiv.subscribe.membershipActivatedLater', $this);
        }

        $this->trial_period_start = null;
        $this->trial_period_end = null;

        $this->save();
        return true;
    }

    /**
     * Grace membership
     */
    public function startGracePeriod($comment = null)
    {
        $current = $this->freshTimestamp();
        $status = Status::getStatusGrace();
        $policy = $this->plan->getPolicy();

        StatusLog::createRecord($status->id, $this, $comment);

        $graceEnd = $current->addDays($policy->grace_period);

        /*
         * Current start and end times
         */
        $this->current_period_start = $current;
        $this->current_period_end = $graceEnd;
        $this->next_assessment = $graceEnd;
        $this->save();

        Event::fire('responsiv.subscribe.onMembershipGraceStarted', $this);

        return true;
    }

    /**
     * Trial membership
     */
    public function startTrialPeriod($comment = null)
    {
        $current = $this->freshTimestamp();
        $status = Status::getStatusTrial();
        $policy = $this->plan->getPolicy();

        StatusLog::createRecord($status->id, $this, $comment);

        /*
         * Trial dates
         */
        $this->trial_period_start = $current;
        $this->trial_period_end = $current->addDays($policy->trial_period);

        /*
         * Current start and end times
         */
        $this->current_period_start = $this->trial_period_start;
        $this->current_period_end = $this->trial_period_end;
        $this->next_assessment = $this->trial_period_end;
        $this->save();

        Event::fire('responsiv.subscribe.onMembershipTrialStarted', $this);

        return true;
    }

    /**
     * Complete membership
     */
    public function completeMembership($comment = null)
    {
        $current = $this->freshTimestamp();
        $status = Status::getStatusComplete();

        StatusLog::createRecord($status->id, $this, $comment);

        /*
         * Completed date
         */
        $this->expired_at = $current;
        $this->current_period_start = null;
        $this->current_period_end = null;
        $this->trial_period_start = null;
        $this->trial_period_end = null;

        /*
         * No longer collect payments
         */
        $this->next_assessment = null;
        $this->save();

        Event::fire('responsiv.subscribe.onMembershipCompleted', $this);

        return true;
    }

    /**
     * Renew membership
     */
    public function renewMembership()
    {
        if (!$this->canRenewMembership()) {
            return false;
        }

        $current = $this->freshTimestamp();
        $endDate = $this->plan->getPeriodEndDate($this->current_period_end);

        /*
         * New start and end dates
         */
        $this->current_period_start = $this->current_period_end;
        $this->current_period_end = $endDate;

        /*
         * Add the renewal
         */
        $this->renewal_period++;

        /*
         * Next assessment to today to capture billing
         */
        $this->next_assessment = $current;
        $this->save();

        return true;
    }

    /**
     * Can the membership be renewed
     */
    public function canRenewMembership()
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
         * No renew when on grace
         */
        $graceStatus = Status::getStatusGrace();
        if ($this->status_id == $graceStatus->id) {
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

}
