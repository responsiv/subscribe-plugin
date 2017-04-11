<?php namespace Responsiv\Subscribe\Tests\Traits;

use Model;
use Carbon\Carbon;
use RainLab\User\Classes\AuthManager;
use RainLab\User\Models\User;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\InvoiceManager;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;

trait WorkflowHelper
{
    protected $worker;

    protected $manager;

    public function setUp()
    {
        parent::setUp();

        $plugin = $this->getPluginObject();
        $plugin->registerSubscriptionEvents();

        $this->engine = SubscriptionEngine::instance();
        $this->worker = SubscriptionWorker::instance();

        $this->engine->reset();
        $this->worker->now = Carbon::now();
    }

    protected function setUpPlanDefault()
    {
        $plan = new Plan;
        $plan->name = 'Testing';
        $plan->code = 'testing';
        $plan->price = 10;
        $plan->trial_days = 0;
        $plan->grace_days = 14;
        $plan->plan_type = Plan::TYPE_MONTHLY;
        $plan->plan_month_interval = 1;
        $plan->plan_monthly_behavior = 'monthly_signup';
        $plan->is_custom_membership = true;
        $plan->save();

        return $plan;
    }

    //
    // Helpers
    //

    protected function reloadMembership(array $payload)
    {
        list($user, $plan, $membership, $service, $invoice) = $payload;

        $plan = Plan::find($plan->id);
        $membership = Membership::find($membership->id);
        $service = Service::find($service->id);
        $invoice = Invoice::find($invoice->id);

        return [$user, $plan, $membership, $service, $invoice];
    }

    protected function generatePaidMembership($plan = null)
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);

        $invoice->submitManualPayment('Testing');

        return $this->reloadMembership($payload);
    }

    protected function generateMembership($plan = null)
    {
        $user = AuthManager::instance()->register([
            'email' => 'user1@example.com',
            'password_confirmation' => 'Password1',
            'password' => 'Password1'
        ]);

        if ($plan === null) {
            $plan = method_exists($this, 'setUpPlan')
                ? $this->setUpPlan()
                : $this->setUpPlanDefault();
        }

        $membership = Membership::createForUser($user, $plan);
        $service = $membership->services->first();
        $invoice = $service->invoices->first();

        return [$user, $plan, $membership, $service, $invoice];
    }

    protected function generateInvoice($service)
    {
        return InvoiceManager::instance()->raiseServiceInvoice($service);
    }

    protected function timeTravelMonth($months = 1)
    {
        return $this->timeTravel('addMonth', $months);
    }

    protected function timeTravelDay($days = 1)
    {
        return $this->timeTravel('addDays', $days);
    }

    protected function timeTravel($method, $units)
    {
        $now = clone $this->engine->now();

        $now->$method($units);

        $this->engine->now($now);
        $this->worker->now = $now;

        return clone $now;
    }

    protected function workerProcessBilling()
    {
        $this->assertEquals(
            'Processed billing for 1 membership(s)',
            $this->worker->process('processAutoBilling')
        );
    }

    protected function workerProcess()
    {
        $this->assertEquals(
            'Processed services for 1 membership(s)',
            $this->worker->process('processMemberships')
        );
    }
}
