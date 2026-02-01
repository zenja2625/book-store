<?php

namespace Bookstore\Catalog\Models;

use Winter\Storm\Database\Model;

class Publisher extends Model
{
    protected $table = 'publishers';

    protected $fillable = [
        'name',
        'slug'
    ];

    public $hasMany = [
        'books' => Book::class,
    ];

    public $morphMany = [
        'discountTargets' => [DiscountTarget::class, 'name' => 'target'],
    ];
}
