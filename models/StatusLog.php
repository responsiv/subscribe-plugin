<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;
use Carbon\Carbon;

/**
 * StatusLog Model
 */
class StatusLog extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_status_logs';

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

    public static function createRecord($statusId, $membership, $comment = null)
    {
        if ($statusId instanceof Model) {
            $statusId = $statusId->getKey();
        }

        if ($membership->status_id == $statusId) {
            return false;
        }

        $previousStatus = $membership->status_id;

        /*
         * Create record
         */
        $record = new static;
        $record->status_id = $statusId;
        $record->membership_id = $membership->id;
        $record->comment = $comment;

        /*
         * Extensibility
         */
        if (Event::fire('responsiv.subscribe.beforeUpdateMembershipStatus', [$record, $membership, $statusId, $previousStatus], true) === false) {
            return false;
        }

        if ($record->fireEvent('subscribe.beforeUpdateMembershipStatus', [$record, $membership, $statusId, $previousStatus], true) === false) {
            return false;
        }

        $record->save();

        /*
         * Update membership status
         */
        $membership->newQuery()->where('id', $membership->id)->update([
            'status_id' => $statusId,
            'status_updated_at' => Carbon::now()
        ]);

        // @todo Send email notifications
        // $status = Status::find($statusId);
        // if ($status && $sendNotifications) {
        //     $status->sendNotification($membership, $comment);
        // }

        return true;
    }

}
