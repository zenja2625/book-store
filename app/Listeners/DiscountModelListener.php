<?php

namespace App\Listeners;

use Illuminate\Support\Facades\App;
use App\Services\PriceService;

class DiscountModelListener
{
    public static function extend($model)
    {
        $model->bindEvent('model.beforeSave', function () use ($model) {
            if ($model->content_group === 'percentage') {
                $model->discount_num = 0;
            } elseif ($model->content_group === 'fixed_amount') {
                $model->discount_pct = null;
            }

        });

        $model->bindEvent('model.afterSave', function () use ($model) {
            if ($model->wasRecentlyCreated === false) {
                self::captureOriginalRelationIds($model);
            }

            App::terminating(function () use ($model) {

                [$book_ids, $genre_ids, $publisher_ids] = self::getAffectedRelationIds($model);

                self::updateBooksPrices($model, $book_ids, $genre_ids, $publisher_ids);
            });
        });

        $model->bindEvent('model.beforeDelete', function () use ($model) {
            self::captureOriginalRelationIds($model);
        });

        $model->bindEvent('model.afterDelete', function () use ($model) {
            App::terminating(function () use ($model) {
                self::updateBooksPrices(
                    $model,
                    $model->original_book_ids,
                    $model->original_genre_ids,
                    $model->original_publisher_ids
                );
            });
        });
    }

    private static function captureOriginalRelationIds($model): void
    {
        $model->original_book_ids = $model->books()->pluck('id');
        $model->original_genre_ids = $model->genres()->pluck('id');
        $model->original_publisher_ids = $model->publishers()->pluck('id');
    }

    private static function updateBooksPrices($model, $book_ids, $genre_ids, $publisher_ids): void
    {
        if ($book_ids->isEmpty() && $genre_ids->isEmpty() && $publisher_ids->isEmpty()) {
            return;
        }

        $repo = App::make(\App\Repositories\DiscountRepository::class);
        $priceService = App::make(PriceService::class);

        $affectedBooks = $repo->getAffectedBooksWithDiscounts($model, $book_ids, $genre_ids, $publisher_ids);

        foreach ($affectedBooks as $book) {
            $offer = $priceService->calculateBestOffer($book->price, $book);

            if ($book->current_price != $offer['price'] || $book->discount_display != $offer['percent']) {
                $book->current_price = $offer['price'];
                $book->discount_display = $offer['percent'];
                $book->saveQuietly();
            }
        }
    }

    private static function getAffectedRelationIds($model): array
    {
        if ($model->wasRecentlyCreated) {
            $book_ids = $model->books()->pluck('id');
            $genre_ids = $model->genres()->pluck('id');
            $publisher_ids = $model->publishers()->pluck('id');
        } elseif ($model->wasChanged(['discount_pct', 'discount_num', 'content_group'])) {
            $book_ids = $model->books()->pluck('id')->merge($model->original_book_ids)->unique();
            $genre_ids = $model->genres()->pluck('id')->merge($model->original_genre_ids)->unique();
            $publisher_ids = $model->publishers()->pluck('id')->merge($model->original_publisher_ids)->unique();
        } else {
            $getChangedRelationIds = fn($newIds, $oldIds) => $newIds
                ->diff($oldIds)
                ->merge($oldIds->diff($newIds))
                ->unique()
                ->values();

            $book_ids = $getChangedRelationIds($model->books()->pluck('id'), $model->original_book_ids);
            $genre_ids = $getChangedRelationIds($model->genres()->pluck('id'), $model->original_genre_ids);
            $publisher_ids = $getChangedRelationIds($model->publishers()->pluck('id'), $model->original_publisher_ids);
        }

        return [$book_ids, $genre_ids, $publisher_ids];
    }
}
