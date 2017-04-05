<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\SubscriptionManager;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class GracePeriodTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\MembershipHelper;

    /**
     * When a paid membership is paid, then cannot be paid automatically,
     * then is paid manually during the grace period.
     */
    public function testWorkflow_Active_Grace_Active()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();

        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_NEW, $service->status->code);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());

        // Pretend the above happened a month ago (1 month subscription)
        $this->rewindService($service, 31);

        // Should hit grace status
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Get the unpaid invoice
        $invoice = $service->raiseInvoice();
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);

        // Pay the outstanding invoice
        $invoice->submitManualPayment('Testing');
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(2, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);
    }

    /**
     * When a paid membership is paid, then cannot be paid automatically,
     * then is never paid during the grace period and it expires.
     */
    public function testWorkflow_Active_Grace_PastDue()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();

        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_NEW, $service->status->code);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());

        // Pretend the above happened a month ago (1 month subscription)
        $this->rewindService($service, 31);

        // Should hit grace status
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Pretend the above happened 15 days ago (14 day grace period)
        $this->rewindService($service, 15, false);

        // Should hit past due status
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_PASTDUE, $service->status->code);
        $this->assertEquals(0, $service->is_active);

        // Get the unpaid invoice
        $invoice = $service->raiseInvoice();
        $this->assertEquals(InvoiceStatus::STATUS_VOID, $invoice->status->code);
    }
}
