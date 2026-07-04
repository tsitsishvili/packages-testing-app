<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductStatisticsRepository
{
    /**
     * Upsert product statistics.
     */
    public function upsert(Collection $statistics): void
    {
        if ($statistics->isEmpty()) {
            return;
        }

        // Chunking is handled here to prevent placeholder limits
        $statistics->chunk(1000)->each(function (Collection $chunk) {
            $rows = $chunk->map(function ($row) {
                return array_map(
                    fn ($value) => is_object($value) ? (string) $value : $value, (array) $row
                );
            })->filter()->values()->toArray();

            if (empty($rows)) {
                return;
            }

            $updatableColumns = array_diff(
                array_keys($rows[0]),
                ['product_id', 'event_date']
            );

            $updatableColumnQuery = [];

            foreach ($updatableColumns as $column) {
                $updatableColumnQuery[$column] = DB::raw("$column + VALUES($column)");
            }

            DB::table('product_statistics')->upsert(
                $rows,
                ['product_id', 'event_date'],
                $updatableColumnQuery
            );
        });
    }
}
