<?php

namespace App\Services\Statistics\Counters;

use App\Services\Statistics\Contracts\MetricAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ViewsCounter implements MetricAggregator
{
    private const TABLE = 'temp_product_view_events';

    /**
     * Highest event id counted by the last calculate() call.
     */
    private ?int $watermark = null;

    public function calculate(): Collection
    {
        $this->watermark = DB::table(self::TABLE)->max('id');

        if ($this->watermark === null) {
            return collect();
        }

        return DB::table(self::TABLE)
            ->where('id', '<=', $this->watermark)
            ->select(
                'product_id',
                DB::raw('count(DISTINCT user_id) as unique_users_view_count'),
                DB::raw('count(*) as view_count')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    public function flush(): void
    {
        if ($this->watermark === null) {
            return;
        }

        DB::table(self::TABLE)->where('id', '<=', $this->watermark)->delete();

        $this->watermark = null;
    }

    public function metrics(): array
    {
        return [
            'unique_users_view_count',
            'view_count',
        ];
    }
}
