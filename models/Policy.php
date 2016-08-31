<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * Policy Model
 */
class Policy extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_policies';

    public $rules = [
        'name' => 'required',
        'trial_period' => 'numeric',
        'grace_period' => 'numeric',
        'invoice_advance_days' => 'numeric',
        'invoice_advance_days_interval' => 'numeric',
    ];

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
    public $hasMany = [
        'paths' => ['Responsiv\Subscribe\Models\DunningPath', 'delete' => true],
    ];
}
