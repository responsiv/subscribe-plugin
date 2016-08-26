<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePlansTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_plans', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('setup_price', 15, 2)->default(0);
            $table->string('plan_type')->nullable();
            $table->string('plan_monthly_behavior')->nullable();
            $table->integer('plan_day_interval')->nullable();
            $table->integer('plan_month_day')->nullable();
            $table->integer('plan_month_interval')->nullable();
            $table->integer('plan_year_interval')->nullable();
            $table->integer('renewal_period')->nullable();
            $table->integer('grace_period')->nullable();
            $table->integer('trial_period')->nullable();
            $table->integer('invoice_advance_days')->nullable();
            $table->integer('invoice_advance_days_interval')->nullable();
            $table->boolean('is_grace_on_renewal')->default(false);

            $table->integer('dunning_plan_id')->unsigned()->nullable()->index();
            $table->integer('expire_notification_id')->unsigned()->nullable()->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_plans');
    }
}
