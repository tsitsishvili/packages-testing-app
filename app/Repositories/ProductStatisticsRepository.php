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

            // MySQL/MariaDB expose the pending row as VALUES(col); SQLite and Postgres expose it as excluded.col.
            $usesValuesSyntax = in_array(
                DB::connection()->getDriverName(), ['mysql', 'mariadb'], true
            );

            foreach ($updatableColumns as $column) {
                $incoming = $usesValuesSyntax ? "VALUES($column)" : "excluded.$column";

                $updatableColumnQuery[$column] = DB::raw("$column + $incoming");
            }

            DB::table('product_statistics')->upsert(
                $rows,
                ['product_id', 'event_date'],
                $updatableColumnQuery
            );
        });
    }
}
