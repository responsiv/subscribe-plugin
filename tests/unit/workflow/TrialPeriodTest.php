<?php namespace Responsiv\Subscribe\Tests\Workflow;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Models\Setting;
use Responsiv\Subscribe\Classes\SubscriptionManager;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class TrialPeriodTest extends PluginTestCase
{
    use \Responsiv\Subscribe\Tests\Traits\MembershipHelper;

    protected function setUpPlan()
    {
        $plan = Plan::whereCode('basic-month')->first();
        $plan->is_custom_membership = true;
        $plan->trial_days = 7;
        $plan->grace_days = 0;
        $plan->save();

        return $plan;
    }

    /**
     * Set a plan to have a trial period and pay the invoice early, non inclusive.
     */
    public function testWorkflow_Trial_Active_NonInclusive()
    {
        Setting::set('is_trial_inclusive', false);

        $plan = $this->setUpPlan();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);

        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertFalse(Setting::get('is_trial_inclusive'));
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(7), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        // Pretend the above happened a 3 days ago (arbitrary number)
        $this->rewindService($service, 3);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
    }

    /**
     * Set a plan to have a trial period and pay the invoice early, inclusive.
     */
    public function testWorkflow_Trial_Active_Inclusive()
    {
        Setting::set('is_trial_inclusive', true);

        $plan = $this->setUpPlan();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);

        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertTrue(Setting::get('is_trial_inclusive'));
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(Carbon::now(), $service->current_period_start, '', 5);
        $this->assertEquals(Carbon::now()->addDays(7), $service->current_period_end, '', 5);
        $this->assertEquals(1, $service->is_active);

        // Pretend the above happened a 3 days ago (arbitrary number)
        $this->rewindService($service, 3);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
        $this->assertEquals(1, $service->count_renewal);
        $this->assertEquals(1, $service->is_active);
        $this->assertEquals(Carbon::now()->addDays(4), $service->current_period_start, '', 5);
    }

    /**
     * Set a plan to have a trial period then don't pay
     */
    public function testWorkflow_Trial_PastDue()
    {
        $plan = $this->setUpPlan();

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership($plan);

        $this->assertNotNull($plan, $membership, $service, $service->status, $invoice, $invoice->status);

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_TRIAL, $service->status->code);
        $this->assertEquals(1, $service->is_active);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());

        // Pretend the above happened a 8 days ago (7 day trial period)
        $this->rewindService($service, 8);

        // Should hit past due status
        $this->assertEquals('Processed 1 membership(s).', $this->worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(1, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_PASTDUE, $service->status->code);
        $this->assertEquals(0, $service->is_active);
    }

}
