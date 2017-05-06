<?php namespace Responsiv\Subscribe\Components;

use Flash;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Responsiv\Pay\Models\Invoice as InvoiceModel;
use Responsiv\Pay\Models\PaymentMethod as TypeModel;
use Responsiv\Pay\Models\UserProfile as UserProfileModel;
use Responsiv\Subscribe\Classes\InvoiceManager;
use Illuminate\Http\RedirectResponse;
use ApplicationException;

/**
 * This component looks up a user from its invoice (must be unpaid),
 * then creates a payment profile for that user. At this point the user
 * should also be converted from a guest to a real user, signed in, etc.
 */
class Payment extends ComponentBase
{
    /**
     * @var Responsiv\Pay\Models\Invoice Cached object
     */
    protected $invoice;

    public function componentDetails()
    {
        return [
            'name'        => 'Subscription Payment',
            'description' => 'Creates a payment profile for the user'
        ];
    }

    public function defineProperties()
    {
        return [
            'hash' => [
                'title'       => 'Invoice Hash',
                'description' => 'The URL route parameter used for looking up the invoice by its hash.',
                'default'     => '{{ :hash }}',
                'type'        => 'string'
            ],
        ];
    }

    public function onRun()
    {
        $this->page['invoice'] = $invoice = $this->invoice();
        $this->page['paymentMethods'] = $this->paymentMethods();
        $this->page['paymentMethod'] = $this->paymentMethod();
        $this->page['hasProfile'] = $hasProfile = $this->hasProfile();

        if ($hasProfile) {
            $this->checkFirstPayment($invoice);
        }
    }

    public function invoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice;
        }

        if (!$hash = $this->property('hash')) {
            return null;
        }

        $invoice = InvoiceModel::whereHash($hash)->first();

        return $this->invoice = $invoice;
    }

    public function paymentMethod()
    {
        return ($invoice = $this->invoice()) ? $invoice->payment_method : null;
    }

    public function paymentMethods()
    {
        $countryId = ($invoice = $this->invoice()) ? $invoice->country_id : null;

        $methods = TypeModel::listApplicable($countryId);

        $methods = $methods->filter(function($method) {
            return $method->supportsPaymentProfiles();
        });

        return $methods;
    }

    public function hasProfile()
    {
        if (!$invoice = $this->invoice()) {
            return false;
        }

        if (!$invoice->user) {
            return false;
        }

        return UserProfileModel::userHasProfile($invoice->user);
    }

    protected function checkFirstPayment($invoice)
    {
        if ($invoice->isPaymentProcessed()) {
            return;
        }

        InvoiceManager::instance()->attemptFirstPayment($invoice);
    }

    //
    // AJAX
    //

    public function onUpdatePaymentType()
    {
        if (!$invoice = $this->invoice()) {
            throw new ApplicationException('Invoice not found!');
        }

        if (!$methodId = post('payment_method')) {
            throw new ApplicationException('Payment type not specified!');
        }

        if (!$method = TypeModel::find($methodId)) {
            throw new ApplicationException('Payment type not found!');
        }

        $invoice->payment_method = $method;
        $invoice->save();

        $this->page['invoice'] = $invoice;
        $this->page['paymentMethod'] = $method;
    }

    public function onUpdatePaymentProfile()
    {
        if (!$invoice = $this->invoice()) {
            throw new ApplicationException('Invoice not found!');
        }

        if (!$user = $invoice->user) {
            throw new ApplicationException('Invoice is missing a user!');
        }

        if (!$paymentMethod = $this->paymentMethod()) {
            throw new ApplicationException('Payment method not found.');
        }

        $result = $paymentMethod->updateUserProfile($user, post());

        if (!post('no_flash')) {
            Flash::success(post('message', 'The payment profile has been successfully updated.'));
        }

        /*
         * Custom response
         */
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return Redirect::refresh();
    }

    /**
     * Returns a profile page URL for a payment method
     */
    // public function returnPageUrl()
    // {
    //     if ($redirect = post('redirect')) {
    //         return $redirect;
    //     }

    //     return $this->pageUrl($this->returnPage());
    // }
}
