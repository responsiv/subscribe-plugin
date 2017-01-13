<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateServicesTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_services', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            // Copied from Plan
            $table->string('name')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('setup_price', 15, 2)->nullable();
            $table->decimal('membership_price', 15, 2)->nullable();
            $table->integer('trial_days')->nullable();
            $table->integer('grace_days')->nullable();

            $table->integer('count_renewal')->nullable();
            $table->integer('count_fail')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->date('next_assessment_at')->nullable();
            $table->dateTime('status_updated_at')->nullable();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_throwaway')->default(false);

            $table->dateTime('activated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('expired_at')->nullable();

            // Not sure of these
            $table->date('original_period_start')->nullable();
            $table->date('original_period_end')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->dateTime('delay_activated_at')->nullable();
            $table->dateTime('delay_cancelled_at')->nullable();

            $table->integer('plan_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->integer('membership_id')->unsigned()->nullable()->index();
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('invoice_item_id')->unsigned()->nullable()->index();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_services');
    }
}
