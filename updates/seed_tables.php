<?php namespace Responsiv\Subscribe\Updates;

use October\Rain\Database\Updates\Seeder;
use Responsiv\Subscribe\Models\Status;
use Responsiv\Subscribe\Models\Transition;

class SeedTables extends Seeder
{

    public function run()
    {
        Status::create(['id' => 1, 'code' => 'active', 'name' => 'Active', 'color' => '#9acd32']);
        Status::create(['id' => 2, 'code' => 'complete', 'name' => 'Complete', 'color' => '#333333']);
        Status::create(['id' => 3, 'code' => 'cancelled', 'name' => 'Cancelled', 'color' => '#dddddd']);
        Status::create(['id' => 4, 'code' => 'pastdue', 'name' => 'Past Due', 'color' => '#ff0000']);
        Status::create(['id' => 5, 'code' => 'pending', 'name' => 'Pending', 'color' => '#999999']);

        Transition::create(['from_state_id' => 1, 'to_state_id' => 2, 'role_id' => 1]);
        Transition::create(['from_state_id' => 1, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 5, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 4, 'to_state_id' => 3, 'role_id' => 1]);
        Transition::create(['from_state_id' => 4, 'to_state_id' => 1, 'role_id' => 1]);
        Transition::create(['from_state_id' => 3, 'to_state_id' => 1, 'role_id' => 1]);
    }

}

// update ldmembership_status set 
//     notify_customer = 1, customer_message_template_id = ('ldmembership:membership_thankyou')
//     where code='pending';

// // update ldmembership_status set 
//     notify_customer = 1, customer_message_template_id = ('ldmembership:membership_thankyou')
//     where code='active';

// // update ldmembership_status set 
//     notify_recipient= 1, admin_message_template_id = ('ldmembership:new_membership_internal')
//     where code='active';

// // update ldmembership_status set 
//     notify_recipient= 1, admin_message_template_id = ('ldmembership:membership_status_update_internal')
//     where code <> 'active' or code is null;
