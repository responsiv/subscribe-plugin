<?php namespace Responsiv\Subscribe\Models;

use Model;

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
    protected $guarded = ['*'];

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
        'invoice'        => 'Responsiv\Pay\Models\Invoice',
        'invoice_item'   => 'Responsiv\Pay\Models\InvoiceItem',
        'plan'           => 'Responsiv\Subscribe\Models\Plan',
        'status'         => 'Responsiv\Subscribe\Models\Status',
    ];

    public $morphMany = [
        'invoices' => ['Responsiv\Pay\Models\Invoice', 'name' => 'related'],
    ];

    public $morphTo = [
        'related' => []
    ];
}