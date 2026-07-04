<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

/**
 * Drives the elastic-audit index-management artisan commands against the
 * in-memory client, asserting the Elasticsearch operations they would issue.
 */
class ElasticAuditCommandsTest extends ElasticAuditTestCase
{
    public function test_create_http_log_index_creates_the_index_and_aliases(): void
    {
        $this->es->indexExists = false;
        $this->es->aliasExists = false;

        $this->artisan('http-logs:create-index')->assertSuccessful();

        $this->assertCount(1, $this->es->createIndexCalls);
        $this->assertStringContainsString('_http_logs_', $this->es->createIndexCalls[0]['index']);

        // Read + write aliases are attached to the new physical index.
        $aliases = array_column($this->es->putAliasCalls, 'alias');
        $this->assertContains(config('http_logs.index_alias'), $aliases);
        $this->assertContains(config('http_logs.index_alias_write'), $aliases);
    }

    public function test_create_activity_log_index_creates_the_index(): void
    {
        $this->es->indexExists = false;
        $this->es->aliasExists = false;

        $this->artisan('activity-logs:create-index')->assertSuccessful();

        $this->assertCount(1, $this->es->createIndexCalls);
        $this->assertStringContainsString('_activity_logs_', $this->es->createIndexCalls[0]['index']);
    }

    public function test_create_index_is_idempotent_when_the_index_already_exists(): void
    {
        $this->es->indexExists = true;
        $this->es->aliasExists = true;

        $this->artisan('http-logs:create-index')->assertSuccessful();

        // Nothing new is created when the index and aliases are already present.
        $this->assertEmpty($this->es->createIndexCalls);
    }

    public function test_prune_does_nothing_without_retention_buckets(): void
    {
        // Default search response carries no aggregations.
        $this->artisan('http-logs:prune')
            ->expectsOutputToContain('Nothing to prune')
            ->assertSuccessful();

        $this->assertEmpty($this->es->deleteByQueryCalls);
    }

    public function test_prune_deletes_documents_per_retention_bucket(): void
    {
        $this->es->searchResolver = fn (): array => [
            'aggregations' => [
                'retention_buckets' => [
                    'buckets' => [
                        ['key' => 30, 'doc_count' => 5],
                        ['key' => 360, 'doc_count' => 2],
                    ],
                ],
            ],
        ];
        $this->es->deleteByQueryResponse = ['deleted' => 5];

        $this->artisan('http-logs:prune')->assertSuccessful();

        // One delete-by-query per distinct retention_days bucket.
        $this->assertCount(2, $this->es->deleteByQueryCalls);
    }
}
