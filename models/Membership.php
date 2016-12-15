<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
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
        'user' => 'RainLab\User\Models\User',
    ];

    public $hasMany = [
        'services' => 'Responsiv\Subscribe\Models\Service',
    ];

}
