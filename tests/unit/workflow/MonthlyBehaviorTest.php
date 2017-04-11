<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Models\Setting;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class MonthlyBehaviorTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\WorkflowHelper;

    protected function setUpPlan()
    {
        $plan = $this->setUpPlanDefault();
        $plan->price = 100;
        $plan->save();

        return $plan;
    }

    /**
     * Set a plan to have a trial period and pay the invoice early, non inclusive.
     */
    public function testWorkflow_Active_FromSignup()
    {
        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_signup';
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Carbon::now(), $service->service_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addMonth(), $service->service_period_end, '', 5);
        $this->assertEquals(100, $invoice->total);
    }

    public function testWorkflow_Active_Prorated()
    {
        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_prorate';
        $plan->plan_month_day = Carbon::now()->addDays(10)->day;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $expectedPrice = $plan->adjustPrice(100);

        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals(Carbon::now(), $service->service_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(10), $service->service_period_end, '', 5);
        $this->assertEquals($expectedPrice, $invoice->total);

        // Bump to next month, raise the next invoice
        $this->workerProcess();
        $this->timeTravelDay(11);
        $this->workerProcess();
        $this->workerProcessBilling();

        // Next invoice should be back to normal
        $invoice = $this->generateInvoice($service);
        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(100, $invoice->total);
    }

    /**
     * Pro rated plans that use a trial are always considered inclusive.
     */
    public function testWorkflow_Trial_Active_Prorated()
    {
        Setting::set('is_trial_inclusive', false);

        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_prorate';
        $plan->plan_month_day = Carbon::now()->addDays(10)->day;
        $plan->trial_days = 7;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(7), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        $now = $this->timeTravelDay(5);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $expectedDate = clone $now;
        $expectedPrice = $plan->adjustPrice(100, $expectedDate->addDays(2));

        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals($expectedPrice, $invoice->total);
        $this->assertEquals($now->addDays(5), $service->service_period_end, '', 5);
    }

    /**
     * Pro rated plans that use a trial are always considered inclusive.
     */
    public function testWorkflow_Trial_Active_Prorated_Alt()
    {
        Setting::set('is_trial_inclusive', false);

        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_prorate';
        $plan->plan_month_day = Carbon::now()->addDays(10)->day;
        $plan->trial_days = 14;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(14), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        $now = $this->timeTravelDay(5);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $expectedDate = clone $now;
        $expectedPrice = $plan->adjustPrice(100, $expectedDate->addDays(9));

        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals($expectedPrice, $invoice->total);
        $this->assertEquals($now->addMonth()->addDays(5), $service->service_period_end, '', 5);
    }
}
