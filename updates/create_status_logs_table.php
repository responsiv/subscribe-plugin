<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateStatusLogsTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_status_logs', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->text('comment')->nullable();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->integer('service_id')->unsigned()->nullable()->index();
            $table->integer('membership_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_status_logs');
    }
}
