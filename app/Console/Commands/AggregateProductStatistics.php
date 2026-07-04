<?php

namespace App\Console\Commands;

use App\Repositories\ProductStatisticsRepository;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Console\Command;

class AggregateProductStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:aggregate-statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate product events into statistics';

    /**
     * Execute the console command.
     */
    public function handle(StatisticAggregationService $service, ProductStatisticsRepository $repository): void
    {
        $start = microtime(true);

        $stats = $service->getAggregatedStatistics();

        if ($stats->isEmpty()) {
            $this->info('No statistics to aggregate');

            return;
        }

        $date = now()->toDateString();

        $data = $stats->map(function ($row) use ($date) {
            return array_merge($row, ['event_date' => $date]);
        });

        $repository->upsert($data);

        $completionTime = microtime(true) - $start;
        $this->info("Total time: $completionTime");

        $this->info("Aggregated {$data->count()} statistics");
    }
}
