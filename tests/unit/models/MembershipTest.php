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
    }

    public function testCreateMembership()
    {
        list($user, $plan, $membership, $service, $invoice) = $this->generateMembership();
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
        $invoice = Invoice::find($invoice->id);
        $service = Service::find($service->id);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);

        // For brevity
        $this->assertEquals('Processed 1 membership(s).', $worker->process());

        // Pretend the above happened a month ago
        $this->unrenewService($service, 31);
        $this->resetProcessedAt($membership);

        // Should hit grace status
        $this->assertEquals('Processed 1 membership(s).', $worker->process());
        $service = Service::find($service->id);
        $membership = Membership::find($membership->id);

        $this->assertEquals(2, $membership->invoices()->count());
        $this->assertEquals(Status::STATUS_GRACE, $service->status->code);

        // Get the unpaid invoice
        $invoice = $membership->raiseInvoice();
        $this->assertEquals(InvoiceStatus::STATUS_APPROVED, $invoice->status->code);

        // Pay the outstanding invoice
        $invoice->submitManualPayment('Testing');
        $invoice = Invoice::find($invoice->id);
        $service = Service::find($service->id);

        $this->assertEquals(InvoiceStatus::STATUS_PAID, $invoice->status->code);
        $this->assertEquals(Status::STATUS_ACTIVE, $service->status->code);
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
        $invoice = $membership->invoices->first();

        return [$user, $plan, $membership, $service, $invoice];
    }

    protected function unrenewService($service, $days)
    {
        $now = Carbon::now()->subDays($days);

        $startDate = $service->current_period_start->subDays($days);
        $endDate = $service->current_period_end->subDays($days);

        $service->current_period_start = $service->original_period_start = $startDate;
        $service->current_period_end = $service->original_period_end = $endDate;

        $service->next_assessment_at = $now;
        $service->save();
    }

    protected function resetProcessedAt($membership)
    {
        $now = Carbon::now()->subDays(30);

        $membership->last_process_at = $now;
        $membership->save();
    }
}
