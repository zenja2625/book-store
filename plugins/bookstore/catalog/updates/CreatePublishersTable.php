<?php

namespace Bookstore\Catalog\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class CreatePublishersTable extends Migration
{
    public function up()
    {

        Schema::create('publishers', function ($table) {
            $table->increments('id');

            $table->string('name');
            $table->string('slug')->unique();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('publishers');
    }
}
