<?php

namespace Bookstore\Catalog\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateGenresTable extends Migration
{
    public function up()
    {
        Schema::create('genres', function ($table) {
            $table->increments('id');

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('meta_description');
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('genres');
    }
}
