<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePlanPoliciesTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_policies', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('trial_period')->nullable();
            $table->integer('grace_period')->nullable();
            $table->boolean('is_grace_on_renewal')->default(false);
            $table->integer('invoice_advance_days')->nullable();
            $table->integer('invoice_advance_days_interval')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_policies');
    }
}
