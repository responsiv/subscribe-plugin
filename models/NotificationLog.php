<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * NotificationLog Model
 */
class NotificationLog extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_notification_logs';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'membership' => Membership::class,
        'service' => Service::class,
    ];
}
