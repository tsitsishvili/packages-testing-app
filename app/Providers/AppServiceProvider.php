<?php

namespace App\Providers;

use App\Services\Statistics\Counters\AddToCartCounter;
use App\Services\Statistics\Counters\AppearanceCounter;
use App\Services\Statistics\Counters\ViewsCounter;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Support\ServiceProvider;
use Tsitsishvili\Documentator\Documentator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StatisticAggregationService::class, function () {
            return new StatisticAggregationService([
                new ViewsCounter,
                new AppearanceCounter,
                new AddToCartCounter,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Documentator::auth(fn ($request) => true);
    }
}
