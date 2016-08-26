<?php namespace Responsiv\Subscribe\Models;

use Model;

/**
 * DunningPlan Model
 */
class DunningPlan extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_dunning_plans';

    public $rules = [
        'name' => 'required',
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
