<?php namespace Responsiv\Subscribe\Components;

use Auth;
use Validator;
use Cms\Classes\ComponentBase;
use Responsiv\Subscribe\Models\Plan as PlanModel;
use Responsiv\Pay\Models\Tax as TaxModel;
use Responsiv\Pay\Classes\TaxLocation;
use Responsiv\Pay\Models\PaymentMethod;
use ValidationException;

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
        return [];
    }

    //
    // Plan selection
    //

    public function onGetPlan()
    {
        if (!$planId = post('selected_plan')) {
            return;
        }

        if ($plan = PlanModel::find($planId)) {
            $this->setLocationInfoOnPlan($plan);
        }

        $this->page['plan'] = $plan;
    }

    protected function setLocationInfoOnPlan($plan)
    {
        if (!$countryId = post('country_id')) {
            return;
        }

        $location = new TaxLocation;

        $location->countryId = $countryId;

        $plan->getTaxClass()->setLocationInfo($location);
    }

    //
    // Registration
    //

    public function onRegister()
    {
        $this->validateRegisterFields();

        $user = $this->registerGuestUser();

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
