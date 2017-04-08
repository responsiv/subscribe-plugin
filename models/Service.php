<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use RainLab\User\Models\User;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Subscribe\Classes\ServiceManager;
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
        'service_period_start',
        'service_period_end',
        'current_period_start',
        'current_period_end',
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
        'user'          => User::class,
    ];

    public $morphMany = [
        'invoices' => [Invoice::class, 'name' => 'related'],
        'invoice_items' => [InvoiceItem::class, 'name' => 'related'],
    ];

    public $morphTo = [
        'related' => []
    ];

    public static function createForMembership(Membership $membership, Plan $plan)
    {
        $service = static::firstOrCreate([
            'plan_id' => $plan->id,
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'is_throwaway' => $membership->is_throwaway,
        ]);

        $service->setRelation('user', $membership->user);
        $service->setRelation('plan', $plan);
        $service->setRelation('membership', $membership);

        ServiceManager::instance()->initService($service, [
            'membership' => $membership,
            'plan' => $plan,
        ]);

        return $service;
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

    public function hasGracePeriod()
    {
        return !!$this->grace_days;
    }

    /**
     * Check if this service has unpaid invoices
     */
    public function hasUnpaidInvoices()
    {
        return Invoice::applyUnpaid()->applyRelated($this)->count() > 0;
    }

    public function hasPeriodEnded()
    {
        return $this->current_period_end &&
            $this->current_period_end <= ServiceManager::instance()->now;
    }

    public function hasServicePeriodEnded()
    {
        return $this->service_period_end &&
            $this->service_period_end <= ServiceManager::instance()->now;
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
        if (!$this->service_period_end) {
            return false;
        }

        /*
         * Service cancelled
         */
        if ($this->cancelled_at) {
            return false;
        }

        /*
         * Service must be activated
         */
        $statusCode = $this->status ? $this->status->code : null;
        if ($statusCode == Status::STATUS_NEW || $statusCode == Status::STATUS_TRIAL) {
            return false;
        }

        /*
         * Membership has another billing period
         */
        $endDate = $this->plan->getPeriodEndDate($this->service_period_end);
        if (!$endDate) {
            return false;
        }

        return true;
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
