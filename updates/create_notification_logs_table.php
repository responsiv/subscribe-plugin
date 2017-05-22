<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateNotificationLogsTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_notification_logs', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('service_id')->unsigned()->nullable()->index();
            $table->integer('membership_id')->unsigned()->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->text('content_html')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_notification_logs');
    }
}
