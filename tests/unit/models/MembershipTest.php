<?php namespace Responsiv\Subscribe\Tests\Models;

use Model;
use Carbon\Carbon;
use RainLab\User\Classes\AuthManager;
use RainLab\User\Models\User;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Service;
use Responsiv\Subscribe\Models\Membership;
use Responsiv\Subscribe\Classes\SubscriptionManager;
use Responsiv\Subscribe\Classes\SubscriptionWorker;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceStatus;
use PluginTestCase;

class MembershipTest extends PluginTestCase
{
    public function setUp()
    {
        parent::setUp();

        $plugin = $this->getPluginObject();
        $plugin->registerSubscriptionEvents();

        SubscriptionManager::instance()->clearCache();
    }

    /**
     * When a paid membership is paid, then cannot be paid automatically,
     * then is paid manually during the grace period.
     */
    public function testMembershipActiveGraceActive()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $worker = SubscriptionWorker::instance();

        $this->assertNotNull(
            $membership,
            $service,
            $service->status,
            $invoice,
            $invoice->status
        );

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_NEW, $service->status->code);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $worker->process());

        // Pretend the above happened a month ago (1 month subscription)
        $this->rewindService($service, 31);

        // Should hit grace status
        $this->assertEquals('Processed 1 membership(s).', $worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);

        // Get the unpaid invoice
        $invoice = $service->raiseInvoice();
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);

        // Pay the outstanding invoice
        $invoice->submitManualPayment('Testing');
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
    }

    /**
     * When a paid membership is paid, then cannot be paid automatically,
     * then is never paid during the grace period and it expires.
     */
    public function testMembershipActiveGracePastDue()
    {
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->generateMembership();
        $worker = SubscriptionWorker::instance();

        $this->assertNotNull(
            $membership,
            $service,
            $service->status,
            $invoice,
            $invoice->status
        );

        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);
        $this->assertEquals(Status::STATUS_NEW, $service->status->code);

        // Pay the first invoice, activate membership
        $invoice->submitManualPayment('Testing');

        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $worker->process());

        // Pretend the above happened a month ago (1 month subscription)
        $this->rewindService($service, 31);

        // Should hit grace status
        $this->assertEquals('Processed 1 membership(s).', $worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);

        // Pretend the above happened 15 days ago (14 day grace period)
        $this->rewindService($service, 15, false);

        // Should hit past due status
        $this->assertEquals('Processed 1 membership(s).', $worker->process());
        list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        $this->assertEquals(2, $service->invoices()->count());
        $this->assertEquals(Status::STATUS_PASTDUE, $service->status->code);

        // Get the unpaid invoice
        $invoice = $service->raiseInvoice();
        $this->assertEquals(InvoiceStatus::STATUS_VOID, $invoice->status->code);

        // // Pay the outstanding invoice
        // $invoice->submitManualPayment('Testing');
        // list($user, $plan, $membership, $service, $invoice) = $payload = $this->reloadMembership($payload);

        // $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        // $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
    }

    //
    // Helpers
    //

    protected function reloadMembership(array $payload)
    {
        list($user, $plan, $membership, $service, $invoice) = $payload;

        $plan = Plan::find($plan->id);
        $membership = Membership::find($membership->id);
        $service = Service::find($service->id);
        $invoice = Invoice::find($invoice->id);

        return [$user, $plan, $membership, $service, $invoice];
    }
    protected function generateMembership()
    {
        $user = AuthManager::instance()->register([
            'email' => 'user1@example.com',
            'password_confirmation' => 'Password1',
            'password' => 'Password1'
        ]);

        $plan = Plan::whereCode('basic-month')->first();

        $membership = Membership::createForUser($user, $plan);
        $service = $membership->services->first();
        $invoice = $service->invoices->first();

        return [$user, $plan, $membership, $service, $invoice];
    }

    protected function rewindService($service, $days, $includeOriginal = true)
    {
        $now = Carbon::now()->subDays($days);

        $startDate = $service->current_period_start->subDays($days);
        $endDate = $service->current_period_end->subDays($days);

        $service->current_period_start = $startDate;
        $service->current_period_end = $endDate;

        if ($includeOriginal) {
            $service->original_period_start = $startDate;
            $service->original_period_end = $endDate;
        }

        $service->next_assessment_at = $now;
        $service->save();

        $this->resetProcessedAt($service->membership);
    }

    protected function resetProcessedAt($membership)
    {
        $now = Carbon::now()->subDays(30);

        $membership->last_process_at = $now;
        $membership->save();
    }
}
