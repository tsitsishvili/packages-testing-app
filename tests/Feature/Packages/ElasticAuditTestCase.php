<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use Tests\Fixtures\RecordingElasticsearchClient;
use Tests\TestCase;
use Tsitsishvili\ElasticAudit\Dashboard\ActivityDashboardQuery;
use Tsitsishvili\ElasticAudit\Dashboard\HttpLogDashboardQuery;
use Tsitsishvili\ElasticAudit\Services\ActivityLogIndexer;
use Tsitsishvili\ElasticAudit\Services\Elasticsearch\LogElasticsearchClientInterface;
use Tsitsishvili\ElasticAudit\Services\HttpLogIndexer;

/**
 * Shared setup for elastic-audit integration tests: swaps the real
 * Elasticsearch client for an in-memory recorder so the queued indexing path
 * (queue is `sync` under phpunit) can be asserted without a live cluster.
 */
abstract class ElasticAuditTestCase extends TestCase
{
    protected RecordingElasticsearchClient $es;

    protected function setUp(): void
    {
        parent::setUp();

        $this->es = new RecordingElasticsearchClient;
        $this->app->instance(LogElasticsearchClientInterface::class, $this->es);

        // The singletons that inject the client are rebuilt so they pick up the
        // recorder rather than a client resolved before this instance was bound.
        $this->app->forgetInstance(HttpLogIndexer::class);
        $this->app->forgetInstance(ActivityLogIndexer::class);
        $this->app->forgetInstance(HttpLogDashboardQuery::class);
        $this->app->forgetInstance(ActivityDashboardQuery::class);
    }
}
