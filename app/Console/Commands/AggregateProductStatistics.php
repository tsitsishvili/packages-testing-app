<?php

namespace App\Console\Commands;

use App\Repositories\ProductStatisticsRepository;
use App\Services\Statistics\StatisticAggregationService;
use Illuminate\Console\Command;
use Tsitsishvili\ElasticAudit\DataTransferObjects\ActivityLogContext;
use Tsitsishvili\ElasticAudit\DataTransferObjects\ExecutionOrigin;
use Tsitsishvili\ElasticAudit\Facades\ActivityLog;

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

        $this->recordAggregationActivity($date, $data->count(), $completionTime);

        $this->info("Total time: $completionTime");

        $this->info("Aggregated {$data->count()} statistics");
    }

    /**
     * The repository writes through the query builder (`DB::table()->upsert()`),
     * so no Eloquent lifecycle event fires and `ActivityLoggable` cannot see the
     * write. The run is recorded explicitly instead.
     *
     * The origin is pinned to the pipeline rather than left to automatic
     * detection, so the activity dashboard filters on the domain process itself.
     * Automatic detection would report whichever entry point happened to run it
     * — this command today, a queued job or a different command tomorrow.
     */
    private function recordAggregationActivity(string $date, int $productCount, float $durationSeconds): void
    {
        ActivityLog::record(
            action: 'product_statistics.aggregated',
            context: ActivityLogContext::forActor(
                actorType: 'system',
                actorId: null,
                entityType: 'product_statistics',
                entityId: $date,
                executionOrigin: ExecutionOrigin::manual('product.statistics_aggregation', 'aggregate'),
            ),
            metadata: [
                'event_date' => $date,
                'products' => $productCount,
                'duration_ms' => (int) round($durationSeconds * 1000),
            ],
        );
    }
}
