<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class CreateMembershipTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\WorkflowHelper;

    protected $plan1;
    protected $plan2;
    protected $plan3;

    public function setUpPlans()
    {
        $plan = $this->setUpPlanDefault();
        $plan->name = 'Test1';
        $plan->code = 'test1';
        $plan->price = 10;
        $plan->save();
        $this->plan1 = $plan;

        $plan = $this->setUpPlanDefault();
        $plan->name = 'Test2';
        $plan->code = 'test2';
        $plan->price = 20;
        $plan->save();
        $this->plan2 = $plan;

        $plan = $this->setUpPlanDefault();
        $plan->name = 'Test3';
        $plan->code = 'test3';
        $plan->price = 30;
        $plan->save();
        $this->plan3 = $plan;
    }

    /**
     * When a user is selecting a plan, they can potentially create disposible models,
     * this test ensures these "throwaway" models are reused during the selection
     * process. Then also locked in once the payment is received.
     */
    public function testWorkflow_PrepareMembership()
    {
        $this->setUpPlans();

        // User selected first plan
        list($user, $plan, $membership, $service, $invoice) = $this->generateMembership($this->plan1);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(1, $membership->services()->count());
        $this->assertEquals(1, Invoice::applyUser($user)->count());
        $this->assertEquals(10, $this->plan1->price);
        $this->assertEquals(10, $invoice->total);
        $this->assertEquals(1, $invoice->items()->count());
        $this->assertEquals(1, $service->is_throwaway);
        $this->assertEquals(1, $invoice->is_throwaway);
        $this->assertEquals($service->freshTimestamp(), $invoice->due_at, '', 5);

        // User selected second plan
        list($user, $plan, $membership, $service, $invoice) = $this->generateMembership($this->plan2, $user);

        $this->assertEquals(1, $membership->services()->count());
        $this->assertEquals(1, Invoice::applyUser($user)->count());
        $this->assertEquals(1, $invoice->items()->count());
        $this->assertEquals(20, $invoice->total);
        $this->assertEquals($service->freshTimestamp(), $invoice->due_at, '', 5);

        // User selected yet another plan
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($this->plan3, $user);

        $this->assertEquals(1, $membership->services()->count());
        $this->assertEquals(1, Invoice::applyUser($user)->count());
        $this->assertEquals(30, $invoice->total);

        // Pay the invoice, activate the membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(0, $invoice->is_throwaway);
        $this->assertEquals(0, $service->is_throwaway);
    }
}
