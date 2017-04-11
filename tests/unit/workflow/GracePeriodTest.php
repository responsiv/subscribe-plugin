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

        // Should hit grace status
        $this->workerProcess();
        $this->timeTravelDay(32);
        $this->workerProcess();
        $this->workerProcessBilling();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Get the unpaid invoice
        $invoice = $this->generateInvoice($service);
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

        // Should hit grace status
        $this->workerProcess();
        $this->timeTravelMonth(1);
        $this->workerProcess();

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertTrue($service->hasUnpaidInvoices());

        $this->workerProcessBilling();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Should hit past due status
        $this->timeTravelDay(15);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_PASTDUE, $service->status->code);
        $this->assertEquals(0, $service->is_active);

        // Get the unpaid invoice
        $invoice = $this->generateInvoice($service);
        $this->assertEquals(InvoiceStatus::STATUS_VOID, $invoice->status->code);
    }

    /**
     * When the grace period is longer than the subscription period.
     * The subsriber effectively stays in grace period after paying
     * their invoice.
     */
    public function testWorkflow_Active_Grace_Grace()
    {
        $plan = $this->setUpPlanDefault();
        $plan->trial_days = 0;
        $plan->grace_days = 90;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // Invoice not paid, grace status activated
        $this->timeTravelMonth(1);
        $this->workerProcess();

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertTrue($service->hasUnpaidInvoices());

        $this->workerProcessBilling();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addMonth(), $service->service_period_end, '', 5);
        $this->assertEquals(Carbon::now()->addMonth()->addDays(90), $service->current_period_end, '', 5);

        // Still no payment, 28 days of grace left
        // Two service periods have now passed
        $now = $this->timeTravelDay(62);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Pay the second invoice, grace period should reset to +90 days
        $invoice = $this->generateInvoice($service);
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(2, $service->count_renewal);
        $this->assertEquals(3, $service->invoices()->count());
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addMonths(2), $service->service_period_end, '', 5);
        $this->assertEquals(Carbon::now()->addMonths(2)->addDays(90), $service->current_period_end, '', 5);

        // Pay the third invoice
        $invoice = $this->generateInvoice($service);
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(3, $service->count_renewal);
        $this->assertEquals(4, $service->invoices()->count());
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addMonths(3), $service->service_period_end, '', 5);
        $this->assertEquals(Carbon::now()->addMonths(3)->addDays(90), $service->current_period_end, '', 5);

        // Pay the fourth invoice (now in the clear)
        $invoice = $this->generateInvoice($service);
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(4, $service->count_renewal);
        $this->assertEquals(4, $service->invoices()->count());
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addMonths(4), $service->service_period_end, '', 5);
        $this->assertEquals(Carbon::now()->addMonths(4), $service->current_period_end, '', 5);
    }
}
