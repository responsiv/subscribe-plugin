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
     * Prorated plan created on exact day of the month.
     */
    public function testWorkflow_Active_Prorated_SameDay()
    {
        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_prorate';
        $plan->plan_month_day = Carbon::now()->day;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals(Carbon::now(), $service->service_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addMonth(), $service->service_period_end, '', 5);
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
        $this->workerProcess();

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
     * When the trial goes past the prorating day of the month. Need to ensure the user
     * is not charged twice here, the service end should be greater than 1 month.
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
        $this->workerProcess();

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $expectedDate = clone $now;
        $expectedPrice = $plan->adjustPrice(100, $expectedDate->addDays(9));

        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals($expectedPrice, $invoice->total);
        $this->assertEquals($now->addMonth()->addDays(5), $service->service_period_end, '', 5);
    }

    public function testWorkflow_Active_FreeDays()
    {
        // Always pretend it is the start of the month
        $nowOriginal = clone $this->engine->now();
        $now = Carbon::now()->startOfMonth();
        $this->engine->now($now);

        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_free';
        $plan->plan_month_day = 10;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $now2 = clone $now;
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals($now, $service->service_period_start, '', 5);
        $this->assertEquals($now2->addMonth()->addDays(9), $service->service_period_end, '', 5);
        $this->assertEquals(100, $invoice->total);

        // It is now the 12th of the month
        $this->timeTravelDay(11);
        $this->workerProcess();

        // Another invoice should not be raised yet
        $this->assertEquals(1, $service->invoices()->count());

        // It is now the 12th of next month
        $this->timeTravelMonth();
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // Second invoice is ready to go 1 month + 11 days later
        $this->assertEquals(2, $service->invoices()->count());

        // Reset
        $this->engine->now($nowOriginal);
    }

    public function testWorkflow_Active_FreeDays_Alt()
    {
        // Always pretend it is the start of the month
        $nowOriginal = clone $this->engine->now();
        $now = Carbon::now()->day(15);
        $this->engine->now($now);

        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_free';
        $plan->plan_month_day = 10;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $now2 = clone $now;
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals($now, $service->service_period_start, '', 5);
        $this->assertEquals($now2->addMonth()->day(10), $service->service_period_end, '', 5);
        $this->assertEquals(100, $invoice->total);

        // It is now the 26th of the month
        $this->timeTravelDay(11);
        $this->workerProcess();

        // Another invoice should not be raised yet
        $this->assertEquals(1, $service->invoices()->count());

        // It is now the 26th of the next month
        $this->timeTravelMonth();
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // Second invoice is ready to go 1 month + 11 days later
        $this->assertEquals(2, $service->invoices()->count());

        // Reset
        $this->engine->now($nowOriginal);
    }

    public function testWorkflow_Active_NoStart()
    {
        $plan = $this->setUpPlan();
        $plan->plan_monthly_behavior = 'monthly_none';
        $plan->plan_month_day = Carbon::now()->addDays(10)->day;
        $plan->save();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generatePaidMembership($plan);
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(Status::STATUS_PENDING, $service->status->code);
        $this->assertEquals(0, $service->is_active);
        $this->assertEquals(1, $service->invoices()->count());
        $this->assertNull($service->service_period_start);
        $this->assertNull($service->service_period_end);
        $this->assertEquals(100, $invoice->total);

        // Bump to next month, raise the next invoice
        $this->workerProcess();
        $this->timeTravelDay(11);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(1, $service->invoices()->count());

        $this->timeTravelMonth();
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // Second invoice is ready to go 1 month + 11 days later
        $this->assertEquals(2, $service->invoices()->count());
    }
}
