<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * Schedule Model
 */
class Schedule extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_schedules';

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
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
