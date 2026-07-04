<?php

namespace App\Services\Statistics\Counters;

use App\Services\Statistics\Contracts\MetricAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddToCartCounter implements MetricAggregator
{
    public function calculate(): Collection
    {
        return DB::table('temp_product_add_to_cart_events')
            ->select(
                'product_id',
                DB::raw('count(DISTINCT user_id) as unique_users_add_to_cart_count'),
                DB::raw('count(*) as add_to_cart_count')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    public function metrics(): array
    {
        return [
            'unique_users_add_to_cart_count',
            'add_to_cart_count',
        ];
    }
}
