<?php

namespace App\Services\Statistics\Counters;

use App\Services\Statistics\Contracts\MetricAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ViewsCounter implements MetricAggregator
{
    public function calculate(): Collection
    {
        return DB::table('temp_product_view_events')
            ->select(
                'product_id',
                DB::raw('count(DISTINCT user_id) as unique_users_view_count'),
                DB::raw('count(*) as view_count')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    public function metrics(): array
    {
        return [
            'unique_users_view_count',
            'view_count',
        ];
    }
}
