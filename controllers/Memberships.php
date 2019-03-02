<?php namespace Responsiv\Subscribe\Controllers;

use Db;
use Flash;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use ValidationException;
use ApplicationException;
use RainLab\User\Models\User as UserModel;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Subscribe\Models\Schedule as ScheduleModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Exception;

/**
 * Memberships Back-end Controller
 */
class Memberships extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\RelationController::class,
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

        $this->asExtension('ListController')->index();
    }

    public function preview($recordId = null, $context = null)
    {
        $this->addJs('/plugins/responsiv/subscribe/assets/js/membership.js');

        $this->asExtension('FormController')->preview($recordId, $context);

        if (!$model = $this->formGetModel()) {
            return;
        }

        $this->vars['activeService'] = $model->active_service;
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

    public function onLoadSchedulePriceForm($id)
    {
        try {
            $membership = MembershipModel::find($id);

            if (!$membership) {
                throw new ApplicationException('Membership not found');
            }

            if (!$service = $membership->active_service) {
                throw new ApplicationException('Service not found');
            }

            $ids = post('list_ids', []);
            $id = post('list_id', null);

            if ($id) {
                $ids[] = $id;
            }

            if (!count($ids)) {
                throw new ApplicationException('Please select schedule(s) to edit.');
            }

            $schedule = new ScheduleModel;
            $schedule->service_id = $membership->id;

            // $parent_order = $membership->order_item->parent_order;

            // if ($parent_order && $parent_order->x_membership_use_order_price) {
            //     $total = $parent_order->total;
            // }
            // else {
            //     $membership->order_item->product->x_membership_ignore_trial = true; // don't use trial price for schedules
            //     $total = $membership->order_item->product->price();
            //     $membership->order_item->product->x_membership_ignore_trial = false;
            // }

            // $schedule->price = $total;

            $this->viewData['schedule'] = $schedule;
            $this->viewData['checked'] = $ids;
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        $this->renderPartial('price_schedule_form');
    }

    public function onSaveSchedulePrice()
    {

    }

    protected function preview_onSaveSchedulePriceXX()
    {
        try
        {
            $id = Phpr::$router->param('param1');
            $membership = LDMembership_Membership::create()->find_by_id($id);
            if (!$membership)
                throw new Phpr_ApplicationException('Membership not found');

            $ids = post('list_ids', null);
            if($ids)
                $ids = explode(',', $ids);

            if (!count($ids))
                throw new Phpr_ApplicationException('Please select schedule(s) to edit.');

            $parent_order = $membership->order_item->parent_order;

            if($parent_order && $parent_order->x_membership_use_order_price) {
                $default_price = $parent_order->total;
            }
            else {
                $membership->order_item->product->x_membership_ignore_trial = true; // don't use trial price for schedules
                $default_price = $membership->order_item->product->price();
                $membership->order_item->product->x_membership_ignore_trial = false;
            }
            
            $data = post('LDMembership_Schedule', array());

            if(!strlen($data['price']))
                throw new Phpr_ApplicationException('Please enter a price.');

            //remove old to prepare room for new
            Db_DbHelper::query('delete from ldmembership_schedule where membership_id = :id and billing_period in (:period)', array('id'=>$membership->id, 'period'=>$ids));

            //if the price is not the same, add some scheduled payments
            if ($data['price'] != $default_price)
            {
                $include_tax = Shop_CheckoutData::display_prices_incl_tax();

                if ($include_tax && (float)$data['price']) {
                    $original_price = $data['price'];
                    $original_tax = Shop_TaxClass::get_total_tax($membership->order_item->product->tax_class_id, $original_price);
                    $original_rate = $original_tax / $original_price;

                    $price = $original_price / (1 + $original_rate);
                    $tax = Shop_TaxClass::get_total_tax($membership->order_item->product->tax_class_id, $price);
                }
                else {
                    $price = $data['price'];
                }

                foreach($ids as $id)
                {
                    $schedule = LDMembership_Schedule::create();
                    $schedule->membership_id = $membership->id;
                    $schedule->billing_period = (int)$id;
                    $schedule->price = $price;
                    $schedule->created_user_id = $this->currentUser->id;
                    $schedule->comment = strlen($data['comment']) ? $data['comment'] : null;
                    $schedule->save();
                }
            }

            $this->viewData['form_model'] = $membership;
            $this->renderPartial('form_area_preview_schedule');
        }
        catch (Exception $ex)
        {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

}
