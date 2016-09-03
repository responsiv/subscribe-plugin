<?php namespace Responsiv\Subscribe\Updates;

use October\Rain\Database\Updates\Seeder;
use Responsiv\Subscribe\Models\Plan;
use Responsiv\Subscribe\Models\Policy;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Transition;

class SeedTables extends Seeder
{
    public function run()
    {
        Status::create([
            'id' => 1,
            'code' => 'active',
            'name' => 'Active',
            'color' => '#9acd32',
            'notify_user' => 1,
            'notify_admin' => 1,
            'user_template' => 'responsiv.subscribe::mail.membership_thankyou',
            'admin_template' => 'responsiv.subscribe::mail.new_membership_internal',
        ]);

        Status::create([
            'id' => 2,
            'code' => 'complete',
            'name' => 'Complete',
            'color' => '#333333',
            'notify_admin' => 1,
            'admin_template' => 'responsiv.subscribe::mail.membership_status_update_internal',
        ]);

        Status::create([
            'id' => 3,
            'code' => 'cancelled',
            'name' => 'Cancelled',
            'color' => '#dddddd',
            'notify_admin' => 1,
            'admin_template' => 'responsiv.subscribe::mail.membership_status_update_internal',
        ]);

        Status::create([
            'id' => 4,
            'code' => 'pastdue',
            'name' => 'Past Due',
            'color' => '#ff0000',
            'notify_admin' => 1,
            'admin_template' => 'responsiv.subscribe::mail.membership_status_update_internal',
        ]);

        Status::create([
            'id' => 5,
            'code' => 'pending',
            'name' => 'Pending',
            'color' => '#999999',
            'notify_user' => 1,
            'user_template' => 'responsiv.subscribe::mail.membership_thankyou',
        ]);

        Transition::create(['from_state_id' => 1, 'to_state_id' => 2, 'role_id' => 1]);
        Transition::create(['from_state_id' => 1, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 5, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 4, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 4, 'to_state_id' => 1, 'role_id' => 1]);
        Transition::create(['from_state_id' => 3, 'to_state_id' => 1, 'role_id' => 1]);

        Policy::create([
            'id' => 1,
            'name' => 'Trial policy',
            'invoice_advance_days' => 1,
            'grace_period' => 3,
            'trial_period' => 10,
        ]);

        Policy::create([
            'id' => 2,
            'name' => 'Strict policy',
            'invoice_advance_days' => 1,
            'grace_period' => 7,
        ]);

        Policy::create([
            'id' => 3,
            'name' => 'Relaxed policy',
            'invoice_advance_days' => 7,
            'grace_period' => 28,
        ]);

        Plan::create([
            'name' => 'Basic Monthly',
            'code' => 'basic-month',
            'price' => 9.95,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
            'policy_id' => 1,
        ]);

        Plan::create([
            'name' => 'Basic Yearly',
            'code' => 'basic-year',
            'price' => 99.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_year_interval' => 1,
            'policy_id' => 3,
        ]);

        Plan::create([
            'name' => 'Pro Monthly',
            'code' => 'pro-month',
            'price' => 19.95,
            'plan_type' => Plan::TYPE_MONTHLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_month_interval' => 1,
            'plan_monthly_behavior' => 'monthly_signup',
            'policy_id' => 2,
        ]);

        Plan::create([
            'name' => 'Pro Yearly',
            'code' => 'pro-year',
            'price' => 199.95,
            'plan_type' => Plan::TYPE_YEARLY,
            'features' => ['First', 'Second', 'Third'],
            'plan_year_interval' => 1,
            'policy_id' => 3,
        ]);

    }
}
