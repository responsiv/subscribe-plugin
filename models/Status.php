<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * Status Model
 */
class Status extends Model
{
    const STATUS_TRIAL = 'trial';
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

    //
    // Status helpers
    //

    public static function getStatusGrace()
    {
        return static::getByCode(static::STATUS_GRACE);
    }

    public static function getStatusTrial()
    {
        return static::getByCode(static::STATUS_TRIAL);
    }

    public static function getStatusActive()
    {
        return static::getByCode(static::STATUS_ACTIVE);
    }

    public static function getStatusComplete()
    {
        return static::getByCode(static::STATUS_COMPLETE);
    }

    public static function getStatusCancelled()
    {
        return static::getByCode(static::STATUS_CANCELLED);
    }

    public static function getStatusPastDue()
    {
        return static::getByCode(static::STATUS_PASTDUE);
    }

    public static function getStatusPending()
    {
        return static::getByCode(static::STATUS_PENDING);
    }

    public static function getByCode($code)
    {
        if (array_key_exists($code, static::$codeCache))
            return static::$codeCache[$code];

        $status = static::whereCode($code)->first();

        return static::$codeCache[$code] = $status;
    }

}
