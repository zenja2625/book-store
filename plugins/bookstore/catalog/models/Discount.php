<?php

namespace Bookstore\Catalog\Models;

use Winter\Storm\Database\Model;

class Discount extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const TYPES = [
        self::TYPE_PERCENT,
        self::TYPE_FIXED,
    ];

    protected $table = 'discounts';

    protected $fillable = [
        'name',
        'type',
        'value',
        'ends_at',
        'cancelled_at',
    ];

    public $hasMany = [
        'targets' => DiscountTarget::class,
    ];
}
