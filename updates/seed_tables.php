<?php namespace Responsiv\Subscribe\Updates;

use October\Rain\Database\Updates\Seeder;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Status;

class SeedTables extends Seeder
{
    public function run()
    {
        Status::create([
            'id' => 1,
            'code' => 'active',
            'name' => 'Active',
            'color' => '#9acd32',
        ]);

        Status::create([
            'id' => 2,
            'code' => 'complete',
            'name' => 'Complete',
            'color' => '#333333',
        ]);

        Status::create([
            'id' => 3,
            'code' => 'cancelled',
            'name' => 'Cancelled',
            'color' => '#dddddd',
        ]);

        Status::create([
            'id' => 4,
            'code' => 'pastdue',
            'name' => 'Past Due',
            'color' => '#ff0000',
        ]);

        Status::create([
            'id' => 5,
            'code' => 'pending',
            'name' => 'Pending',
            'color' => '#999999',
        ]);

        Status::create([
            'id' => 6,
            'code' => 'trial',
            'name' => 'Trial',
            'color' => '#999999',
        ]);

        Status::create([
            'id' => 7,
            'code' => 'grace',
            'name' => 'Grace',
            'color' => '#ff0000',
        ]);

        Status::create([
            'id' => 8,
            'code' => 'new',
            'name' => 'New',
            'color' => '#999999',
        ]);

        Plan::create([
            'name' => 'Basic Monthly',
            'code' => 'basic-month',
            'price' => 9.95,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
        ]);

        Plan::create([
            'name' => 'Basic Yearly',
            'code' => 'basic-year',
            'price' => 99.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_year_interval' => 1,
        ]);

        Plan::create([
            'name' => 'Pro Monthly',
            'code' => 'pro-month',
            'price' => 19.95,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
        ]);

        Plan::create([
            'name' => 'Pro Yearly',
            'code' => 'pro-year',
            'price' => 199.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_year_interval' => 1,
        ]);
    }
}
