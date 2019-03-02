<?php namespace Responsiv\Subscribe\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateStatusesTable extends Migration
{
    public function up()
    {
        Schema::create('responsiv_subscribe_statuses', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_subscribe_statuses');
    }
}
