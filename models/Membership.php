<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use RainLab\User\Models\User;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Subscribe\Classes\MembershipManager;
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
        'first_service' => Service::class,
    ];

    public $hasMany = [
        'services' => [Service::class, 'delete' => true],
        'notifications' => [NotificationLog::class, 'delete' => true],
    ];

    //
    // Creation
    //

    public static function createForUser(User $user, Plan $plan, $options = [])
    {
        extract(array_merge([
            'isGuest' => false,
        ], $options));

        $membership = static::firstOrCreate([
            'user_id' => $user->id,
            'is_throwaway' => $isGuest ? 1 : 0
        ]);

        $membership->setRelation('user', $user);

        MembershipManager::instance()->initMembership($membership, [
            'guest' => $isGuest,
            'plan' => $plan
        ]);

        return $membership;
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
    // Getters
    //

    public function isTrialUsed()
    {
        return !!$this->is_trial_used;
    }

    public function isTrialActive()
    {
        if (!$this->isTrialUsed()) {
            return false;
        }

        return $this->trial_period_end > MembershipManager::instance()->now;
    }

    public function getActivePlan()
    {
        return $this->services()->applyActive()->orderBy('activated_at', 'desc')->first();
    }

    //
    // Scopes
    //

    public function scopeApplyUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
