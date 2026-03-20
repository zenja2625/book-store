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
                $model->old_books = $model->books()->pluck('id');
                $model->old_genres = $model->genres()->pluck('id');
                $model->old_publishers = $model->publishers()->pluck('id');
            }

            App::terminating(function () use ($model) {
                $repo = App::make(\App\Repositories\DiscountRepository::class);

                if ($model->wasRecentlyCreated) {
                    $bookIds = $model->books()->pluck('id');
                    $genreIds = $model->genres()->pluck('id');
                    $publisherIds = $model->publishers()->pluck('id');
                } elseif ($model->wasChanged('discount_pct') || $model->wasChanged('discount_num') || $model->wasChanged('content_group')) {
                    $bookIds = $model->books()->pluck('id')->merge($model->old_books)->unique();
                    $genreIds = $model->genres()->pluck('id')->merge($model->old_genres)->unique();
                    $publisherIds = $model->publishers()->pluck('id')->merge($model->old_publishers)->unique();
                } else {
                    $getChangedRelationIds = fn($newIds, $oldIds) => $newIds
                        ->diff($oldIds)
                        ->merge($oldIds->diff($newIds))
                        ->unique()
                        ->values();

                    $bookIds = $getChangedRelationIds($model->books()->pluck('id'), $model->old_books);
                    $genreIds = $getChangedRelationIds($model->genres()->pluck('id'), $model->old_genres);
                    $publisherIds = $getChangedRelationIds($model->publishers()->pluck('id'), $model->old_publishers);
                }

                if ($bookIds->isEmpty() && $genreIds->isEmpty() && $publisherIds->isEmpty())
                    return;

                $affectedBooks = $repo->getAffectedBooksWithDiscounts($model, $bookIds, $genreIds, $publisherIds);

                $priceService = App::make(PriceService::class);
                foreach ($affectedBooks as $book) {
                    $offer = $priceService->calculateBestOffer($book->price, $book);

                    if ($book->current_price != $offer['price'] || $book->discount_display != $offer['percent']) {
                        $book->current_price = $offer['price'];
                        $book->discount_display = $offer['percent'];
                        $book->saveQuietly();
                    }
                }
            });
        });

        $model->bindEvent('model.beforeDelete', function () use ($model) {
            $repo = App::make(\App\Repositories\DiscountRepository::class);
            $model->books_to_update_on_delete = $repo->getAffectedBooksWithDiscounts(
                $model,
                $model->books()->pluck('id'),
                $model->genres()->pluck('id'),
                $model->publishers()->pluck('id')
            );
        });

        $model->bindEvent('model.afterDelete', function () use ($model) {
            App::terminating(function () use ($model) {
                if (empty($model->books_to_update_on_delete)) {
                    return;
                }

                $repo = App::make(\App\Repositories\DiscountRepository::class);
                $priceService = App::make(PriceService::class);

                foreach ($model->books_to_update_on_delete as $book) {
                    $discounts = $repo->getBestDiscountForBook($book->id, $book->genre_id, $book->publisher_id);
                    $offer = $priceService->calculateBestOffer($book->price, $discounts);

                    if ($book->current_price != $offer['price'] || $book->discount_display != $offer['percent']) {
                        $book->current_price = $offer['price'];
                        $book->discount_display = $offer['percent'];
                        $book->saveQuietly();
                    }
                }
            });
        });
    }
}
