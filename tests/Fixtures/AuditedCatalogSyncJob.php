<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

/**
 * Makes an audited provider call from inside a queued job, so tests can assert
 * the `queue` execution origin elastic-audit resolves from Laravel's
 * JobProcessing/JobProcessed events (the `sync` driver raises both).
 */
class AuditedCatalogSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $productId = '7') {}

    public function handle(): void
    {
        HttpLog::make(
            provider: Provider::Catalog,
            eventType: EventType::CatalogSync,
            context: HttpLogContext::forEntity(EntityType::Product, entityId: $this->productId),
        )->post('https://catalog.example/items', ['sku' => 'WIDGET-1']);
    }
}
