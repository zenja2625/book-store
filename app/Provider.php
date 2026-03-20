<?php

namespace App;

use System\Classes\AppBase;
use Responsiv\Currency\Models\Currency;
use Tailor\Models\StreamRecord;
use Tailor\Models\EntryRecord;
use Illuminate\Support\Facades\App;

use App\Services\PriceService;

/**
 * Provider is an application level plugin, all registration methods are supported.
 */
class Provider extends AppBase
{
    public function registerListColumnTypes()
    {
        return [
            'discount_smart' => fn($value, $column, $record) => $record->content_group === 'percentage'
                ? $value . '%'
                : Currency::getDefault()->formatCurrency($value),
        ];
    }
    /**
     * register method, called when the app is first registered.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->registerConsoleCommand('app.importBooks', \App\Console\Commands\ImportBooks::class);
        $this->registerConsoleCommand('app.seedTestDb', \App\Console\Commands\SeedTestDb::class);

        $this->app->singleton(
            PriceService::class,
            fn() => new PriceService()
        );

        $this->app->singleton(
            \App\Repositories\DiscountRepository::class,
            fn() => new \App\Repositories\DiscountRepository()
        );
    }


    public function registerComponents()
    {
        return [
            \App\Components\Cart::class => 'cart'
        ];
    }

    /**
     * boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();


        StreamRecord::extendInSection('Catalog\Discount', function ($model) {
            \App\Listeners\DiscountModelListener::extend($model);
        });


        EntryRecord::extendInSection('Catalog\Book', function ($model) {
            \App\Listeners\BookModelListener::extend($model);
        });
    }
}
