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

class CancellationTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\MembershipHelper;

    /**
     * When a user pays for their membership then decides to cancel early.
     */
    public function testWorkflow_Active_Cancelled()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);

        // 15 days later...
        $this->workerProcess();
        $this->timeTravelDay(15);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // Cancel the service at the end of the period
        $service->cancelService('No good');
        $this->assertTrue($service->isCancelled());
        $this->assertEquals(Carbon::now()->addMonth(), $service->delay_cancelled_at, '', 5);

        // The next day
        $this->timeTravelDay(1);
        $this->workerProcess();

        // Still active
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Subscription is over now
        $this->timeTravelMonth(15);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_CANCELLED, $service->status->code);
        $this->assertEquals(0, $service->is_active);
    }

    public function testWorkflow_Active_Grace_Cancelled()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);

        // Should hit grace status
        $this->workerProcess();
        $now = $this->timeTravelDay(32);
        $this->workerProcess();
        $this->workerProcessBilling();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Cancel the service while in grace
        $service->cancelService('No good');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertTrue($service->isCancelled());
        $this->assertEquals(0, $service->is_active);
        $this->assertNull($service->delay_cancelled_at);
        $this->assertEquals($now, $service->cancelled_at, '', 5);

        // Get the unpaid invoice
        $invoice = $this->generateInvoice($service);
        $this->assertEquals(InvoiceStatus::STATUS_VOID, $invoice->status->code);
    }
}
