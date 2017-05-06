<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Setting;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class ChangePlanTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\WorkflowHelper;

    /**
     * When a user changes their plan, whilst active on another, the old
     * service should be cancelled and a new service created on the new
     * plan. The remaining credit should be pro-rated from the previous
     * service and added to the first invoice of the new service.
     *
     * If a user is downgrading plans... raise a credit note??
     *
     * @todo Right now, the user simply loses money when switching plans.
     */
    public function testWorkflow_Active_NewPlan()
    {
        /*
         * New plan
         */
        $newPlan = new Plan;
        $newPlan->name = 'Second plan';
        $newPlan->code = 'testing-2';
        $newPlan->price = 1000;
        $newPlan->trial_days = 0;
        $newPlan->grace_days = 0;
        $newPlan->plan_type = Plan::TYPE_MONTHLY;
        $newPlan->plan_month_interval = 1;
        $newPlan->plan_monthly_behavior = 'monthly_signup';
        $newPlan->save();

        /*
         * Start with basic plan
         */
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->isActive());

        /*
         * Change to the new plan
         */
        $newService = $this->engine->switchPlan($membership, $newPlan);

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertNotNull($newService);
        $this->assertEquals(Status::STATUS_NEW, $newService->status->code);

        $this->assertEquals(Status::STATUS_CANCELLED, $service->status->code);
        $this->assertEquals(2, $membership->services()->count());
        $this->assertEquals(0, $service->isActive());
    }
}
