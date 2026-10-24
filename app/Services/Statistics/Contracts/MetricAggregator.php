<?php

namespace App\Services\Statistics\Contracts;

use Illuminate\Support\Collection;

interface MetricAggregator
{
    /**
     * Get the aggregated metrics keyed by product ID.
     */
    public function calculate(): Collection;

    /**
     * Discard the events counted by the last calculate() call.
     *
     * Bounded by the id watermark taken while counting, so events written during
     * the run are left for the next one instead of being dropped uncounted.
     */
    public function flush(): void;

    /**
     * Get the list of metric keys provided by this aggregator.
     *
     * @return array<string>
     */
    public function metrics(): array;
}
