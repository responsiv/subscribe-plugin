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

        $features = [
            ['name' => 'First'],
            ['name' => 'Second'],
            ['name' => 'Third']
        ];

        Plan::create([
            'is_active' => true,
            'name' => 'Basic Monthly',
            'sku' => 'basic-month',
            'price' => 9.95,
            'trial_days' => 7,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => $features,
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
            'short_description' => 'Base level plan with base features.',
        ]);

        Plan::create([
            'is_active' => true,
            'name' => 'Basic Yearly',
            'sku' => 'basic-year',
            'price' => 99.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => $features,
            'plan_year_interval' => 1,
            'short_description' => 'Base level plan with a yearly discount.',
        ]);

        Plan::create([
            'is_active' => true,
            'name' => 'Pro Monthly',
            'sku' => 'pro-month',
            'price' => 19.95,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => $features,
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
            'short_description' => 'Professional level plan with extra features.',
        ]);

        Plan::create([
            'is_active' => true,
            'name' => 'Pro Yearly',
            'sku' => 'pro-year',
            'price' => 199.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => $features,
            'plan_year_interval' => 1,
            'short_description' => 'Professional level plan with a yearly discount.',
        ]);
    }
}
