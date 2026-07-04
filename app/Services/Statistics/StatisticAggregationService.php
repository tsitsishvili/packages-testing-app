<?php

namespace App\Services\Statistics;

use App\Services\Statistics\Contracts\MetricAggregator;
use Illuminate\Support\Collection;

class StatisticAggregationService
{
    /**
     * @param  iterable<MetricAggregator>  $aggregators
     */
    public function __construct(
        protected iterable $aggregators
    ) {}

    public function getAggregatedStatistics(): Collection
    {
        $aggregated = collect();
        $allKeys = collect();

        foreach ($this->aggregators as $aggregator) {
            $keys = $aggregator->metrics();
            $allKeys = $allKeys->merge($keys);

            $aggregator->calculate()->each(function ($stats, $productId) use ($aggregated, $keys) {
                if (! $aggregated->has($productId)) {
                    $aggregated->put($productId, ['product_id' => $productId]);
                }

                $current = $aggregated->get($productId);

                foreach ($keys as $key) {
                    $current[$key] = $stats->$key ?? 0;
                }

                $aggregated->put($productId, $current);
            });
        }

        $uniqueKeys = $allKeys->unique();

        return $aggregated->map(function (array $item) use ($uniqueKeys) {
            foreach ($uniqueKeys as $key) {
                if (! isset($item[$key])) {
                    $item[$key] = 0;
                }
            }

            return $item;
        })->values();
    }
}
