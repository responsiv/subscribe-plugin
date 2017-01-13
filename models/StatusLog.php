<?php namespace Responsiv\Subscribe\Models;

use Model;
use Event;

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
    public $belongsTo = [
        'membership' => Membership::class,
        'service'    => Service::class,
    ];

    public static function createRecord($statusId, Service $service, $comment = null)
    {
        if ($statusId instanceof Model) {
            $statusId = $statusId->getKey();
        }

        if ($service->status_id == $statusId) {
            return false;
        }

        $previousStatus = $service->status_id;

        /*
         * Create record
         */
        $record = new static;
        $record->status_id = $statusId;
        $record->membership_id = $service->membership_id;
        $record->service_id = $service->id;
        $record->comment = $comment;

        /*
         * Extensibility
         */
        if (Event::fire('responsiv.subscribe.beforeUpdateMembershipStatus', [$record, $service, $statusId, $previousStatus], true) === false) {
            return false;
        }

        if ($record->fireEvent('subscribe.beforeUpdateMembershipStatus', [$record, $service, $statusId, $previousStatus], true) === false) {
            return false;
        }

        $record->save();

        /*
         * Update membership status
         */
        $service->newQuery()->where('id', $service->id)->update([
            'status_id' => $statusId,
            'status_updated_at' => $this->freshTimestamp()
        ]);

        // @todo Send email notifications
        // $status = Status::find($statusId);
        // if ($status && $sendNotifications) {
        //     $status->sendNotification($service, $comment);
        // }

        return true;
    }

}
