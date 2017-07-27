<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Setting;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\MembershipManager;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class ChangePlanTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\WorkflowHelper;

    protected $newPlan;

    public function setUpPlans()
    {
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

        $this->newPlan = $newPlan;
    }

    /**
     * When a user changes their plan, whilst active on another, the old
     * service should be cancelled and a new service created on the new
     * plan. The remaining credit from the old plan is forfeited.
     */
    public function testWorkflow_Active_NewPlan_Now()
    {
        $this->setUpPlans();

        /*
         * Start with basic plan
         */
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->isActive());
        $this->assertEquals($membership->active_service_id, $service->id);

        /*
         * Emulate in the wild
         */
        $now = $this->timeTravelDay(1);
        $this->workerProcess();

        /*
         * Change to the new plan
         */
        $newService = MembershipManager::instance()->switchPlanNow($membership, $this->newPlan);
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        /*
         * Old plan should remain active until payment
         */
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(Status::STATUS_NEW, $newService->status->code);
        $this->assertEquals(1, $newService->is_throwaway);

        /*
         * Pay the new plan
         */
        $invoice = $newService->first_invoice;
        $invoice->submitManualPayment('Testing');

        /*
         * Emulate in the wild
         */
        $now = $this->timeTravelDay(1);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);
        $newService = Service::find($newService->id);

        /*
         * Membership active
         */
        $this->assertEquals($membership->active_service_id, $newService->id);

        /*
         * Old service now cancelled
         */
        $this->assertEquals(Status::STATUS_ACTIVE, $newService->status->code);
        $this->assertEquals(Status::STATUS_CANCELLED, $service->status->code);
        $this->assertEquals(0, $service->isActive());
        $this->assertEquals(1, $newService->isActive());
    }

    /**
     * A user can opt to wait until the current service has completed before
     * changing to the new plan.
     */
    public function testWorkflow_Active_NewPlan_AtTermEnd()
    {
        $this->setUpPlans();

        /*
         * Start with basic plan
         */
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->isActive());

        /*
         * Emulate in the wild
         */
        $now = $this->timeTravelDay(1);
        $this->workerProcess();

        /*
         * Change to the new plan
         */
        $newService = MembershipManager::instance()->switchPlan($membership, $this->newPlan);
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        /*
         * Old plan should remain active until payment
         */
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(Status::STATUS_NEW, $newService->status->code);
        $this->assertEquals(1, $newService->is_throwaway);

        /*
         * Pay the new plan
         */
        $invoice = $newService->first_invoice;
        $invoice->submitManualPayment('Testing');

        /*
         * Emulate in the wild
         */
        $now = $this->timeTravelDay(1);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);
        $newService = Service::find($newService->id);

        /*
         * Old service not cancelled until next month
         */
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(Status::STATUS_PENDING, $newService->status->code);
        $this->assertEquals(1, $service->isActive());
        $this->assertEquals(0, $newService->isActive());

        /*
         * Emulate in the wild
         */
        $now = $this->timeTravelMonth(1);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);
        $newService = Service::find($newService->id);

        /*
         * Old service now cancelled
         */
        $this->assertEquals(Status::STATUS_ACTIVE, $newService->status->code);
        $this->assertEquals(Status::STATUS_CANCELLED, $service->status->code);
        $this->assertEquals(0, $service->isActive());
        $this->assertEquals(1, $newService->isActive());
    }
}
