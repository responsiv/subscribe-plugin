<?php namespace Responsiv\Subscribe\Tests\Models;

use Model;
use Carbon\Carbon;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Classes\SubscriptionEngine;
use PluginTestCase;

class PlanTest extends PluginTestCase
{
    protected $now;

    public function setUp()
    {
        parent::setUp();

        // Always pretend it is the start of the month
        $this->now = Carbon::now()->startOfMonth();
        SubscriptionEngine::instance()->now($this->now);
    }

    protected function setUpPlan()
    {
        $plan = new Plan;
        $plan->name = 'Testing';
        $plan->sku = 'testing';
        $plan->price = 100;
        $plan->plan_type = Plan::TYPE_MONTHLY;
        $plan->plan_monthly_behavior = 'monthly_prorate';
        $plan->plan_month_day = '10';
        $plan->save();

        return $plan;
    }

    public function testDaysInCycle()
    {
        $plan = $this->setUpPlan();

        // Current date: 1st
        // Cycle date: 10th
        // Days in cycle = number of days in last month
        $date = clone $this->now;
        $prev = clone $this->now;
        $prev = $prev->subMonth();

        $this->assertEquals($prev->daysInMonth, $plan->daysInCycle());
        $this->assertEquals($prev->daysInMonth, $plan->daysInCycle($date));

        // Current date: 20th
        // Cycle date: 10th
        // Days in cycle = number of days in current month
        $date = clone $this->now;
        $date->addDays(20);
        $current = clone $this->now;

        $this->assertEquals($current->daysInMonth, $plan->daysInCycle($date));
    }

    public function testDaysUntilBilling()
    {
        $plan = $this->setUpPlan();

        // Current date: 1st
        // Cycle date: 10th
        // Days until billing = 10 - 1 = 9
        $date = clone $this->now;
        $this->assertEquals(9, $plan->daysUntilBilling());
        $this->assertEquals(9, $plan->daysUntilBilling($date));

        // Current date: 5th
        // Cycle date: 10th
        // Days until billing = 10 - 5 = 5
        $date = clone $this->now;
        $date->day = 5;
        $this->assertEquals(5, $plan->daysUntilBilling($date));

        // Current date: 7th
        // Cycle date: 10th
        // Days until billing = 10 - 7 = 3
        $date = clone $this->now;
        $date->day = 7;
        $this->assertEquals(3, $plan->daysUntilBilling($date));

        // Current date: 10th
        // Cycle date: 10th
        // Days until billing = 10 - 10 = 0
        $date = clone $this->now;
        $date->day = 10;
        $this->assertEquals(0, $plan->daysUntilBilling($date));

        // Current date: 20th
        // Cycle date: 10th (next month)
        // Days until billing = days in current month - 20 + 10
        $date = clone $this->now;
        $date->day = 20;
        $daysInCurrentMonth = $date->daysInMonth;
        $expected = $daysInCurrentMonth - 20 + 10;

        $this->assertEquals($expected, $plan->daysUntilBilling($date));
    }

    public function testAdjustPrice()
    {
        $plan = $this->setUpPlan();

        // Current date: 1st
        // Cycle date: 10th
        // Price: 100
        // Adjusted price = (100 / days in previous month) * 9
        $date = clone $this->now;
        $date->subMonth();
        $daysInMonth = $date->daysInMonth;

        $pricePerDay = $plan->price / $daysInMonth;
        $expectedPrice = round($pricePerDay * 9, 2);

        $this->assertEquals($expectedPrice, $plan->adjustPrice(100));
        $this->assertEquals($expectedPrice, $plan->adjustPrice(100, $this->now));

        // Current date: 10th
        // Cycle date: 10th
        // Price: 100
        // Adjusted price = 100
        $now = clone $this->now;
        $now->day = 10;
        $this->assertEquals(100, $plan->adjustPrice(100, $now));

        // Current date: 20th
        // Cycle date: 10th
        // Price: 100
        // Billable days = (days in current month - 20 + 10)
        // Adjusted price = (100 / days in current month) * Billable days

        // Billable days example:
        //
        // March has 31 days
        // 20th March -> 10th April = 21 days
        // Billable days = 31 - 20 + 10 = 21
        //
        // April has 30 days
        // 20th April -> 10th May = 20 days
        // Billable days = 30 - 20 + 10 = 20
        //
        // Adjusted price example:
        //
        // 10th April to 10th May = 30 days
        // 20th April to 10th May = 20 days (2/3)
        // 2 / 3 * 100 = $66.67
        // (100 / 30 days) * 20 = $66.67

        $date = clone $this->now;
        $daysInMonth = $date->daysInMonth;
        $billableDays = $daysInMonth - 20 + 10;

        $pricePerDay = $plan->price / $daysInMonth;
        $expectedPrice = round($pricePerDay * $billableDays, 2);

        $now = clone $this->now;
        $now->day = 20;
        $this->assertEquals($expectedPrice, $plan->adjustPrice(100, $now));
    }
}
