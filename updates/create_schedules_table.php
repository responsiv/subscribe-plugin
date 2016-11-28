<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateSchedulesTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_schedules', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('billing_period')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->text('comment')->nullable();
            $table->integer('membership_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_schedules');
    }
}
