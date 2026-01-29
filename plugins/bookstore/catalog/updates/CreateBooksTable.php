<?php

namespace Bookstore\Catalog\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateBooksTable extends Migration
{
    public function up()
    {
        Schema::create('books', function ($table) {

            $table->increments('id');

            $table->integer('genre_id')->unsigned();
            $table->integer('publisher_id')->unsigned();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('author');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->smallInteger('publisher_year')->unsigned();;
            $table->integer('stock_qty')->unsigned()->default(0);
            $table->tinyInteger('is_featured')->default(0);
            $table->tinyInteger('is_visible')->default(1);
            $table->string('meta_title');
            $table->string('meta_description');

            $table->timestamps();

            $table->foreign('genre_id')->references('id')->on('genres');
            $table->foreign('publisher_id')->references('id')->on('publishers');
        });
    }

    public function down()
    {
        Schema::dropIfExists('books');
    }
}
