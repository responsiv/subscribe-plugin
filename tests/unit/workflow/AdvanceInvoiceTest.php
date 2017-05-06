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

class AdvanceInvoiceTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\WorkflowHelper;

    protected function setUpPlan()
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
        $plan->save();

        return $plan;
    }

    /**
     * When an invoice is created early and the user pays early.
     */
    public function testWorkflow_Active_Invoice_PayEarly()
    {
        // 7 day window
        Setting::set('invoice_advance_days', 7);

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(7, Setting::get('invoice_advance_days'));

        // 15th of month (outside window)
        $now = $this->timeTravelDay(15);
        $this->workerProcess();

        $this->assertEquals(1, $service->invoices()->count());

        // 25th of month (inside window)
        $now = $this->timeTravelDay(10);
        $this->workerProcess();
        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Carbon::now()->addMonth(), $service->service_period_end, '', 5);
        $this->assertTrue($service->hasUnpaidInvoices());

        // Second invoice due date should be the day service expires
        $invoice = $this->generateInvoice($service);
        $this->assertEquals($service->service_period_end, $invoice->due_at, '', 5);

        // Pay the second invoice early
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertFalse($service->hasUnpaidInvoices());
        $this->assertEquals(Carbon::now()->addMonths(2), $service->service_period_end, '', 5);
    }

    /**
     * When an invoice is created early and the user pays late.
     */
    public function testWorkflow_Active_Invoice_PayLate()
    {
        // 7 day window
        Setting::set('invoice_advance_days', 7);

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(7, Setting::get('invoice_advance_days'));

        // 15th of month (outside window)
        $now = $this->timeTravelDay(15);
        $this->workerProcess();

        $this->assertEquals(1, $service->invoices()->count());

        // 25th of month (inside window)
        $now = $this->timeTravelDay(10);
        $this->workerProcess();
        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Carbon::now()->addMonth(), $service->service_period_end, '', 5);
        $this->assertTrue($service->hasUnpaidInvoices());

        // Now in the next month (4-6th), grace period
        $now = $this->timeTravelDay(10);
        $this->workerProcess();
        $this->workerProcessBilling();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addMonth()->addDays(14), $service->current_period_end, '', 5);
    }
}
