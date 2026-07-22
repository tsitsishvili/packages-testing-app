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
        // Physical index is the initial rollover generation of the read alias, e.g. app_http_logs-000001.
        $this->assertSame(
            config('http_logs.index_alias').'-000001',
            $this->es->createIndexCalls[0]['index'],
        );

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
        $this->assertSame(
            config('activity_logs.index_alias').'-000001',
            $this->es->createIndexCalls[0]['index'],
        );
    }

    public function test_create_index_rolls_over_when_the_write_alias_already_exists(): void
    {
        $this->es->existingIndexes = [config('http_logs.index_alias').'-000001'];
        $this->es->aliasExists = true;

        $this->artisan('http-logs:create-index')->assertSuccessful();

        // With an existing write alias the command rolls the alias over to the next
        // physical index instead of creating a fresh index and moving the alias.
        $this->assertEmpty($this->es->createIndexCalls);
        $this->assertCount(1, $this->es->rolloverCalls);
        $this->assertSame(config('http_logs.index_alias_write'), $this->es->rolloverCalls[0]['alias']);
        $this->assertSame(
            config('http_logs.index_alias').'-000002',
            $this->es->rolloverCalls[0]['newIndex'],
        );
    }

    public function test_prune_does_nothing_without_retention_buckets(): void
    {
        // An empty index still returns the composite aggregation, just with no
        // buckets and no after_key to page past.
        $this->es->searchResolver = fn (): array => [
            'aggregations' => ['retention_buckets' => ['buckets' => []]],
        ];

        $this->artisan('http-logs:prune')
            ->expectsOutputToContain('Nothing to prune')
            ->assertSuccessful();

        $this->assertEmpty($this->es->deleteByQueryCalls);
    }

    public function test_prune_deletes_documents_per_retention_bucket(): void
    {
        // Retention values now come from a composite aggregation, so each bucket
        // key is nested under the source name rather than being a bare scalar.
        $this->es->searchResolver = fn (): array => [
            'aggregations' => [
                'retention_buckets' => [
                    'buckets' => [
                        ['key' => ['retention_days' => 30], 'doc_count' => 5],
                        ['key' => ['retention_days' => 360], 'doc_count' => 2],
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
