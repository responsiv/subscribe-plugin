<?php namespace Responsiv\Subscribe\Models;

use Model;
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
        'invoice'        => Invoice::class,
        'invoice_item'   => InvoiceItem::class,
        'membership'     => Membership::class,
        'plan'           => Plan::class,
        'status'         => Status::class,
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

        /*
         * Trial period
         */
        if ($membership->isTrialActive()) {
            $this->startTrialPeriod();
        }
        else {
            $this->save();
        }
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
    // Invoicing
    //

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
        $this->current_period_start = $membership->trial_period_start;
        $this->current_period_end = $membership->trial_period_end;
        $this->next_assessment = $membership->trial_period_end;
        $this->save();

        Event::fire('responsiv.subscribe.membershipTrialStarted', $this);

        return true;
    }

    //
    // Scopes
    //

    public function scopeApplyActive($query)
    {
        return $query->where('is_active', true);
    }
}
