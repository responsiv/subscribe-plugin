<?php namespace Responsiv\Subscribe\Components;

use Db;
use Auth;
use Redirect;
use Validator;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Tax as TaxModel;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\InvoiceItem as InvoiceItemModel;
use Responsiv\Pay\Models\InvoiceStatus as InvoiceStatusModel;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Subscribe\Models\Membership as MembershipModel;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Pay\Models\PaymentMethod;
use ApplicationException;
use ValidationException;
use Exception;

class Subscribe extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Subscribe Component',
            'description' => 'Creates a new membership with an accompanying subscription'
        ];
    }

    public function defineProperties()
    {
        return [
            'paymentPage' => [
                'title'       => 'Payment page',
                'description' => 'This page is used for providing the membership payment.',
                'type'        => 'dropdown',
                'default'     => 'register/pay',
            ],
        ];
    }

    public function getPaymentPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function paymentPage()
    {
        return $this->property('paymentPage');
    }

    //
    // Registration (for guests)
    //

    public function onRegister()
    {
        $this->validateRegisterFields();

        try {
            Db::beginTransaction();

            $user = $this->registerGuestUser();

            $membership = $this->createMembership($user, true);

            $invoice = $this->populateInvoice($membership);

            Db::commit();

            return Redirect::to($this->pageUrl(
                $this->paymentPage(),
                ['hash' => $invoice->hash]
            ));
        }
        catch (Exception $ex) {
            Db::rollBack();
            throw $ex;
        }
    }

    protected function validateRegisterFields()
    {
        $data = post();
        $rules = [
            'selected_plan' => 'required',
            'name' => 'required',
            'email' => 'required|between:6,255|email',
            'password' => 'required|between:4,255|confirmed',
            'password_confirmation' => 'required|between:4,255',
            'street_addr' => 'required',
            'city' => 'required',
            'state_name' => 'required',
            'zip' => 'required',
            'country' => 'required',
        ];

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }
    }

    protected function registerGuestUser()
    {
        $formData = array_only(post(), [
            'name',
            'email',
            'password',
            'password_confirmation',
            'street_addr',
            'city',
            'state_name',
            'zip',
            'country',
        ]);

        return Auth::registerGuest($formData);
    }

    //
    // Subscription (for logged in users)
    //

    public function onSubscribe()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to subscribe.');
        }

        $this->validateSubscribeFields();

        try {
            Db::beginTransaction();

            $this->updateUserBilling($user);

            $membership = $this->createMembership($user);

            $invoice = $this->populateInvoice($membership);

            Db::commit();

            return Redirect::to($this->pageUrl(
                $this->paymentPage(),
                ['hash' => $invoice->hash]
            ));
        }
        catch (Exception $ex) {
            Db::rollBack();
            throw $ex;
        }
    }

    public function onLoadUpdateConfirmForm()
    {
        $this->page['newPlan'] = PlanModel::find(post('selected_plan'));
    }

    public function onUpdateConfirm()
    {
        if (!$user = $this->user()) {
            throw new ApplicationException('Please log in to subscribe.');
        }

        if (!$membership = $user->membership) {
            throw new ApplicationException('Cannot update a user when they have no membership!');
        }

        if (!$plan = PlanModel::find(post('selected_plan'))) {
            throw new ApplicationException('Unable to locate the selected plan!');
        }

        try {
            Db::beginTransaction();

            $service = SubscriptionEngine::instance()->switchPlan($membership, $plan);

            if (!$invoice = $service->first_invoice) {
                throw new ApplicationException('New service is without an invoice!');
            }

            $invoice->return_page = $this->paymentPage();
            $invoice->save();

            Db::commit();
        }
        catch (Exception $ex) {
            Db::rollBack();
            throw $ex;
        }

        return Redirect::to($this->pageUrl(
            $this->paymentPage(),
            ['hash' => $invoice->hash]
        ));
    }

    protected function validateSubscribeFields()
    {
        $data = post();
        $rules = [
            'selected_plan' => 'required',
            'street_addr' => 'required',
            'city' => 'required',
            'state_name' => 'required',
            'zip' => 'required',
            'country' => 'required',
        ];

        $validation = Validator::make($data, $rules);
        if ($validation->fails()) {
            throw new ValidationException($validation);
        }
    }

    protected function updateUserBilling($user)
    {
        $formData = array_only(post(), [
            'street_addr',
            'city',
            'state_name',
            'zip',
            'country',
        ]);

        $user->fill($formData);
        $user->save();
    }

    //
    // Common
    //

    protected function createMembership($user, $isGuest = false)
    {
        if (!$planId = post('selected_plan')) {
            throw new ValidationException(['selected_plan' => 'Plan missing!']);
        }

        if (!$plan = PlanModel::find($planId)) {
            throw new ValidationException(['selected_plan' => 'Plan missing!']);
        }

        $membership = MembershipModel::createForUser($user, $plan);

        return $membership;
    }

    protected function populateInvoice($membership)
    {
        if (!$user = $membership->user) {
            throw new ApplicationException('Membership is missing a user.');
        }

        if (!$service = $membership->active_service) {
            throw new ApplicationException('Membership is missing a service.');
        }

        if (!$invoice = $service->first_invoice) {
            throw new ApplicationException('Membership is missing an invoice.');
        }

        $invoice->first_name = $user->name;
        $invoice->last_name = $user->surname;
        $invoice->company = post('company');
        $invoice->street_addr = post('street_addr');
        $invoice->city = post('city');
        $invoice->zip = post('zip');
        $invoice->country_id = post('country_id');
        $invoice->state_id = post('state_id');
        $invoice->return_page = $this->paymentPage();

        $invoice->save();
        $invoice->touchTotals();

        return $invoice;
    }

    /**
     * Returns the logged in user, if available, and touches
     * the last seen timestamp.
     * @return RainLab\User\Models\User
     */
    public function user()
    {
        if (!$user = Auth::getUser()) {
            return null;
        }

        return $user;
    }
}
