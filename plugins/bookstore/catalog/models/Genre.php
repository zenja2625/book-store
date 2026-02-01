<?php

namespace Bookstore\Catalog\Models;

use Winter\Storm\Database\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $sort_order
 */
class Genre extends Model
{
    private const ORDER_STEP = 100;

    protected $table = 'genres';

    protected $fillable = [
        'name',
        'slug',
        'meta_description',
        'sort_order'
    ];

    public $hasMany = [
        'books' => Book::class,
    ];

    public $morphMany = [
        'discountTargets' => [DiscountTarget::class, 'name' => 'target'],
    ];

    public static function createAfter($data, $afterId = null)
    {
        return DB::transaction(function () use ($data, $afterId) {
            $order = self::getOrderAndNormalizeIfNeeded($afterId);

            $data['sort_order'] = $order;
            return self::create($data);
        }, 2);
    }

    public static function moveAfter($id, $afterId = null)
    {
        return DB::transaction(function () use ($id, $afterId) {
            $currentGenre = self::where('id', $id)
                ->lockForUpdate()
                ->firstOrFail(['id', 'sort_order']);

            if ($id !== $afterId) {
                $order = self::getOrderAndNormalizeIfNeeded($afterId);

                if ($afterId !== null || $currentGenre->sort_order - $order !== self::ORDER_STEP) {
                    $currentGenre->sort_order = $order;
                    $currentGenre->save();
                }
            }

            return $currentGenre;
        });
    }

    public static function normalizeOrder(): void
    {
        $genres = self::orderBy('sort_order')
            ->select('id', 'sort_order')
            ->lockForUpdate()
            ->get();

        foreach ($genres as $i => $genre) {
            $genre->sort_order = self::ORDER_STEP * $i;
            $genre->save();
        }
    }

    private static function getOrderAndNormalizeIfNeeded($afterId)
    {
        if ($afterId === null) {
            $firstGenre = self::orderBy('sort_order')
                ->lockForUpdate()
                ->first(['sort_order']);

            if ($firstGenre === null) return 0;
            else return $firstGenre->sort_order - self::ORDER_STEP;
        } else {
            $currentGenre = self::where('id', $afterId)
                ->lockForUpdate()
                ->first(['sort_order']);

            if ($currentGenre === null) return self::getOrderAndNormalizeIfNeeded(null);

            $secondGenre = self::where('sort_order', '>', $currentGenre->sort_order)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->first(['sort_order']);

            if ($secondGenre === null) return $currentGenre->sort_order + self::ORDER_STEP;

            if ($secondGenre->sort_order - $currentGenre->sort_order <= 1) {
                self::normalizeOrder();

                return self::getOrderAndNormalizeIfNeeded($afterId);
            }

            return $currentGenre->sort_order + intdiv($secondGenre->sort_order - $currentGenre->sort_order, 2);
        }
    }
}
