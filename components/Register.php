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
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Pay\Models\PaymentMethod;
use ValidationException;
use Exception;

class Register extends ComponentBase
{

    public function componentDetails()
    {
        return [
            'name'        => 'Register Component',
            'description' => 'Allows a user to sign up with an accompanying subscription'
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

    //
    // Plan selection
    //

    public function onGetPlan()
    {
        $this->page['plan'] = $this->getPlan();
    }

    protected function getPlan($planId = null)
    {
        if (!$planId) {
            $planId = post('selected_plan');
        }

        if (!$planId) {
            return;
        }

        if ($plan = PlanModel::find($planId)) {
            $this->setLocationInfoOnPlan($plan);
        }

        return $plan;
    }

    protected function setLocationInfoOnPlan($plan)
    {
        if (!$countryId = post('country')) {
            return;
        }

        $location = new TaxLocation;

        $location->countryId = $countryId;

        if ($taxClass = $plan->getTaxClass()) {
            $taxClass->setLocationInfo($location);
        }
    }

    //
    // Registration
    //

    public function onRegister()
    {
        $this->validateRegisterFields();

        try {
            Db::beginTransaction();

            $user = $this->registerGuestUser();

            $membership = $this->createMembership($user);

            $invoice = $this->populateInvoice($membership);

            Db::commit();

            return Redirect::to($this->pageUrl(
                $this->property('paymentPage'),
                ['hash' => $invoice->hash]
            ));
        }
        catch (Exception $ex) {
            Db::rollBack();
            throw $ex;
        }
    }

    protected function createMembership($user)
    {
        if (!$plan = $this->getPlan()) {
            throw new ValidationException(['selected_plan' => 'Plan missing!']);
        }

        $membership = MembershipModel::createForGuest($user, $plan);

        return $membership;
    }

    protected function populateInvoice($membership)
    {
        $user = $membership->user;
        $invoice = $membership->invoice;

        $invoice->first_name = $user->name;
        $invoice->last_name = $user->surname;
        $invoice->company = post('company');
        $invoice->street_addr = post('street_addr');
        $invoice->city = post('city');
        $invoice->zip = post('zip');
        $invoice->country_id = post('country_id');
        $invoice->state_id = post('state_id');
        $invoice->due_at = $invoice->freshTimestamp();
        $invoice->return_page = $this->property('paymentPage');

        $invoice->save();
        $invoice->touchTotals();

        return $invoice;
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

}
