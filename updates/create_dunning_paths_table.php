<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateDunningPathsTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_dunning_paths', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('failed_attempts')->nullable();
            $table->string('user_template')->nullable();
            $table->string('admin_template')->nullable();
            $table->integer('policy_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_dunning_paths');
    }
}
