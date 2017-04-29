<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateMembershipsTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_memberships', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->date('trial_period_start')->nullable();
            $table->date('trial_period_end')->nullable();
            $table->boolean('is_trial_used')->default(false);
            $table->boolean('is_throwaway')->default(false);
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('first_service_id')->unsigned()->nullable()->index();
            $table->dateTime('last_process_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_memberships');
    }
}
