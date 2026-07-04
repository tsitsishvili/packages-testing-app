<?php

namespace App\Services\Statistics\Counters;

use App\Services\Statistics\Contracts\MetricAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppearanceCounter implements MetricAggregator
{
    public function calculate(): Collection
    {
        return DB::table('temp_product_appearance_events')
            ->select(
                'product_id',
                DB::raw('count(*) as search_appearance_count')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    public function metrics(): array
    {
        return [
            'search_appearance_count',
        ];
    }
}
