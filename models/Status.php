<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * Status Model
 */
class Status extends Model
{
    const STATUS_TRIAL = 'trial';
    const STATUS_NEW = 'new';
    const STATUS_GRACE = 'grace';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETE = 'complete';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PASTDUE = 'pastdue';
    const STATUS_PENDING = 'pending';

    protected static $codeCache = [];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_statuses';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * Returns a code, cached.
     */
    public static function findByCode($code)
    {
        if (array_key_exists($code, static::$codeCache)) {
            return static::$codeCache[$code];
        }

        $status = static::whereCode($code)->first();

        return static::$codeCache[$code] = $status;
    }
}
