<?php

namespace Bookstore\Catalog\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateDiscountTargetsTable extends Migration
{
    public function up()
    {
        Schema::create('discount_targets', function ($table) {
            $table->increments('id');
            $table->integer('discount_id')->unsigned();
            $table->enum('target_type', ['book', 'genre', 'publisher', 'all']);
            $table->integer('target_id')->unsigned()->nullable();

            $table->timestamps();

            $table->foreign('discount_id')->references('id')->on('discounts');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discount_targets');
    }
}
