<?php

namespace App\Listeners;

use Illuminate\Support\Facades\App;
use App\Services\PriceService;

class BookModelListener
{
    public static function extend($model)
    {
        $model->bindEvent('model.beforeSave', function () use ($model) {
            if ($model->isDirty('price') || $model->isDirty('genre_id') || $model->isDirty('publisher_id')) {

                $discount_repository = App::make(\App\Repositories\DiscountRepository::class);
                $discounts = $discount_repository->getBestDiscountForBook($model->id, $model->genre_id, $model->publisher_id);

                $price_service = App::make(PriceService::class);
                $offer = $price_service->calculateBestOffer($model->price, $discounts);

                $model->current_price = $offer['price'];
                $model->discount_display = $offer['percent'];
            }
        });
    }
}
