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
     * Get the list of metric keys provided by this aggregator.
     *
     * @return array<string>
     */
    public function metrics(): array;
}
