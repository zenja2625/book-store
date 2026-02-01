<?php

namespace Bookstore\Catalog\Models;

use Winter\Storm\Database\Model;

class DiscountTarget extends Model
{
    public const TARGET_BOOK = 'book';
    public const TARGET_GENRE = 'genre';
    public const TARGET_PUBLISHER = 'publisher';
    public const TARGET_ALL = 'all';

    public const TARGET_TYPES = [
        self::TARGET_BOOK,
        self::TARGET_GENRE,
        self::TARGET_PUBLISHER,
        self::TARGET_ALL,
    ];

    protected $table = 'discount_targets';

    protected $fillable = [
        'discount_id',
        'target_type',
        'target_id',
    ];

    public $belongsTo = [
        'discount' => Discount::class,
    ];

    public $morphTo = [
        'target' => [],
    ];
}
