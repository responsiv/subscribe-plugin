<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateTransitionsTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_transitions', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('from_state_id')->unsigned()->nullable()->index();
            $table->integer('to_state_id')->unsigned()->nullable()->index();
            $table->integer('role_id')->unsigned()->nullable()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_transitions');
    }
}
