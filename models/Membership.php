<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
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

    /*
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
}
