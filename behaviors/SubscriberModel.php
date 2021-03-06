<?php namespace Responsiv\Subscribe\Behaviors;

use Db;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use System\Classes\ModelBehavior;
use ApplicationException;
use Exception;

/**
 * Subscriber model extension
 *
 * Adds features for checking user subscriptions.
 *
 * Usage:
 *
 * In the model class definition:
 *
 *   public $implement = ['Responsiv.Subscribe.Behaviors.SubscriberModel'];
 *
 */
class SubscriberModel extends ModelBehavior
{
    /**
     * Constructor
     */
    public function __construct($model)
    {
        parent::__construct($model);

        $model->hasOne['membership'] = MembershipModel::class;
    }

    /**
     * Determines if this user has a membership, has ever subscribed to something.
     * @return bool
     */
    public function hasMembership()
    {
        return !!$this->model->membership;
    }

    public function activeSubscription()
    {
        if ($this->hasMembership()) {
            return $this->model->membership->active_service;
        }
    }

    public function subscriptionTrialEnd()
    {
        if ($this->hasMembership()) {
            return $this->model->membership->trial_period_end;
        }
    }

    public function subscriptionIsTrial()
    {
        if ($this->hasMembership()) {
            return $this->model->membership->isTrialActive();
        }
    }

    public function subscriptionIsCancelled()
    {
        if ($service = $this->activeSubscription()) {
            return $service->isCancelled();
        }
    }

    public function subscriptionIsActive()
    {
        if ($service = $this->activeSubscription()) {
            return $service->isActive();
        }
    }
}
