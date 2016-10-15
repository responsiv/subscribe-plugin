<?php namespace Responsiv\Subscribe\Models;

use Model;
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
}
