<?php

namespace Bookstore\Catalog\Models;

use Winter\Storm\Database\Model;

class Book extends Model {
    protected $table = 'books';

    protected $fillable = [
        'genre_id',
        'publisher_id',
        'name',
        'slug',
        'author',
        'description',
        'price',
        'publisher_year',
        'stock_qty',
        'is_featured',
        'is_visible',
        'meta_title',
        'meta_description',
    ];

    public $belongsTo = [
        'genre' => Genre::class,
        'publisher' => Publisher::class,
    ];

    public $morphMany = [
        'discountTargets' => [DiscountTarget::class, 'name' => 'target'],
    ];
}
