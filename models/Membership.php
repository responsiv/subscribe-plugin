<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use RainLab\User\Models\User;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;
use Responsiv\Pay\Models\InvoiceStatus;
use ApplicationException;

/**
 * Membership Model
 */
class Membership extends Model
{
    use \October\Rain\Database\Traits\Validation;

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
     * @var array Rules
     */
    public $rules = [
        'user' => 'required',
    ];

    /**
     * @var array The attributes that should be mutated to dates.
     */
    protected $dates = [
        'trial_period_start',
        'trial_period_end',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user' => User::class,
    ];

    public $hasMany = [
        'services' => [Service::class, 'delete' => true],
    ];

    //
    // Creation
    //

    public static function createForUser(User $user, Plan $plan, $isGuest = false)
    {
        $membership = static::firstOrCreate([
            'user_id' => $user->id,
            'is_throwaway' => $isGuest ? 1 : 0
        ]);

        $membership->setRelation('user', $user);

        $membership->initMembership([
            'guest' => $isGuest,
            'plan' => $plan
        ]);

        return $membership;
    }

    public function initMembership($options = [])
    {
        extract(array_merge([
            'plan' => null,
            'guest' => false
        ], $options));

        if (!$plan) {
            throw new ApplicationException('Membership is missing a plan!');
        }

        if ($plan->hasTrialPeriod()) {
            $this->setTrialPeriodFromPlan($plan);
        }

        $service = Service::createForMembership($this, $plan);

        $invoice = $service->invoice;

        if ($plan->hasMembershipPrice()) {
            $this->raiseInvoiceMembershipFee($invoice, $plan->getMembershipPrice());
        }

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);

        $invoice->touchTotals();

        $this->save();
    }

    //
    // Trial period
    //

    public function setTrialPeriodFromPlan(Plan $plan)
    {
        $current = $this->freshTimestamp();
        $trialDays = $plan->getTrialPeriod();

        $this->is_trial_used = true;
        $this->trial_period_start = $current;
        $this->trial_period_end = $current->addDays($trialDays);
    }

    public function isTrialActive()
    {
        if (!$this->is_trial_used) {
            return false;
        }

        return $this->trial_period_end->isFuture();
    }

    //
    // Invoicing
    //

    public function raiseInvoiceMembershipFee(Invoice $invoice, $price)
    {
        $item = InvoiceItem::applyRelated($this)
            ->applyInvoice($invoice)
            ->first()
        ;

        if (!$item) {
            $item = new InvoiceItem;
            $item->invoice = $invoice;
            $item->related = $this;
            $item->quantity = 1;
            $item->price = $price;
            $item->description = 'Membership fee';
            $item->save();
        }

        return $item;
    }

    //
    // Options
    //

    public function getSelectedPlanOptions()
    {
        $options = [];

        $plans = Plan::all();
        foreach ($plans as $plan) {
            $options[$plan->id] = [$plan->name, $plan->plan_type_name];
        }

        return $options;
    }

    //
    // Scopes
    //

    public function scopeApplyUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
