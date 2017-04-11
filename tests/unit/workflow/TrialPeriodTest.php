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

class TrialPeriodTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\MembershipHelper;

    protected function setUpPlan()
    {
        $plan = $this->setUpPlanDefault();
        $plan->trial_days = 7;
        $plan->save();

        return $plan;
    }

    /**
     * Set a plan to have a trial period and pay the invoice early, non inclusive.
     */
    public function testWorkflow_Trial_Active_NonInclusive()
    {
        Setting::set('is_trial_inclusive', false);

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertFalse(Setting::get('is_trial_inclusive'));
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(7), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        $now = $this->timeTravelDay(3);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);

        $start = clone $now;
        $end = clone $start;
        $end = $end->addMonth();

        $this->assertEquals($now, $service->service_period_start, '', 5);
        $this->assertEquals($now, $service->current_period_start, '', 5);
        $this->assertEquals($end, $service->service_period_end, '', 5);
        $this->assertEquals($end, $service->current_period_end, '', 5);
    }

    /**
     * Set a plan to have a trial period and pay the invoice early, inclusive.
     */
    public function testWorkflow_Trial_Active_Inclusive()
    {
        Setting::set('is_trial_inclusive', true);

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertTrue(Setting::get('is_trial_inclusive'));
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(7), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        $now = $this->timeTravelDay(3);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);

        $start = clone $now;
        $start->addDays(4);
        $end = clone $start;
        $end->addMonth();

        $this->assertEquals($start, $service->service_period_start, '', 5);
        $this->assertEquals($start, $service->current_period_start, '', 5);
        $this->assertEquals($end, $service->service_period_end, '', 5);
        $this->assertEquals($end, $service->current_period_end, '', 5);
    }

    /**
     * Set a plan to have a trial period then don't pay
     */
    public function testWorkflow_Trial_PastDue()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // Trial period is over after 8 days
        $this->workerProcess();
        $this->timeTravelDay(8);
        $this->workerProcess();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_PASTDUE, $service->status->code);
        $this->assertEquals(0, $service->is_active);
    }
}
