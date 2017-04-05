<?php namespace Responsiv\Subscribe\Tests\Traits;

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

trait MembershipHelper
{

    protected $worker;

    protected $manager;

    public function setUp()
    {
        parent::setUp();

        $plugin = $this->getPluginObject();
        $plugin->registerSubscriptionEvents();

        $this->manager = SubscriptionManager::instance();
        $this->worker = SubscriptionWorker::instance();

        $this->manager->clearCache();
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

    protected function generateMembership($plan = null)
    {
        $user = AuthManager::instance()->register([
            'email' => 'user1@example.com',
            'password_confirmation' => 'Password1',
            'password' => 'Password1'
        ]);

        if ($plan === null) {
            $plan = Plan::whereCode('basic-month')->first();
        }

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
