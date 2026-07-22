<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fixtures\RecordingElasticsearchClient;
use Tsitsishvili\ElasticAudit\Services\Elasticsearch\LogElasticsearchClientInterface;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // elastic-audit indexes activity/HTTP logs to a real Elasticsearch cluster
        // via queued jobs; under the `sync` test queue those run in-process. As of
        // package v4 activity jobs dispatch after-commit, so any model persisted
        // inside an application DB::transaction() (e.g. OrderRepository::create())
        // fires the job the moment that transaction commits. Bind a no-op client so
        // incidental audit writes never require a live cluster. Tests that assert on
        // indexed documents rebind their own recorder in ElasticAuditTestCase.
        $this->app->instance(LogElasticsearchClientInterface::class, new RecordingElasticsearchClient);
    }
}
