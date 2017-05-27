<?php namespace Responsiv\Subscribe\Controllers;

use Db;
use Flash;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Backend\Behaviors\FormController;
use Backend\Behaviors\ListController;
use Backend\Behaviors\RelationController;
use ValidationException;
use RainLab\User\Models\User as UserModel;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;


/**
 * Memberships Back-end Controller
 */
class Memberships extends Controller
{
    public $implement = [
        FormController::class,
        ListController::class,
        RelationController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Responsiv.Subscribe', 'subscribe', 'memberships');
    }

    public function index()
    {
        $this->vars['totalMemberships'] = MembershipModel::count();

        $this->asExtension(ListController::class)->index();
    }

    public function onCreateMembership()
    {
        $data = post('Membership');

        if (
            (!$userId = array_get($data, 'user')) ||
            (!$user = UserModel::find($userId))
        ) {
            throw new ValidationException(['user' => 'Please select a user for the membership.']);
        }

        if (
            (!$planId = array_get($data, 'selected_plan')) ||
            (!$plan = PlanModel::find($planId))
        ) {
            throw new ValidationException(['selected_plan' => 'Please select a membership plan.']);
        }

        if (MembershipModel::applyUser($user)->first()) {
            throw new ValidationException(['user' => 'User already has a membership!']);
        }

        $membership = Db::transaction(function() use ($user, $plan) {
            return MembershipModel::createForUser($user, $plan);
        });

        Flash::success('Membership created!');

        return Backend::redirect('responsiv/subscribe/memberships/preview/'.$membership->id);
    }
}
