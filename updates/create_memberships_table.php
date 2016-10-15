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
            $table->integer('renewal_period')->nullable();
            $table->date('original_period_start')->nullable();
            $table->date('original_period_end')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->date('next_assessment')->nullable();
            $table->date('trial_period_start')->nullable();
            $table->date('trial_period_end')->nullable();
            $table->dateTime('status_updated_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('delay_activated_at')->nullable();
            $table->dateTime('delay_cancelled_at')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_guest')->default(false);

            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();

            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->integer('plan_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('invoice_item_id')->unsigned()->nullable()->index();
            $table->integer('coupon_id')->unsigned()->nullable()->index();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_memberships');
    }
}
