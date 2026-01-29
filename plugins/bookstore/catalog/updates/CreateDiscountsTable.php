<?php

namespace Bookstore\Catalog\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateDiscountsTable extends Migration
{
    public function up()
    {
        Schema::create('discounts', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('discounts');
    }
}
