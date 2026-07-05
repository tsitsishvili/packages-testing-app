<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use Tsitsishvili\ElasticAudit\Services\Elasticsearch\LogElasticsearchClientInterface;

/**
 * In-memory stand-in for the logs Elasticsearch client used across the
 * elastic-audit integration tests. It records every write so tests can assert
 * on what would have been indexed/pruned without a live cluster, and lets each
 * test steer the read-side (`search`, `existsIndex`, `existsAlias`) behaviour.
 */
final class RecordingElasticsearchClient implements LogElasticsearchClientInterface
{
    /** @var list<array<string, mixed>> Every params array passed to index(). */
    public array $indexCalls = [];

    /** @var list<array<string, mixed>> Every params array passed to bulk(). */
    public array $bulkCalls = [];

    /** @var list<array<string, mixed>> Every params array passed to createIndex(). */
    public array $createIndexCalls = [];

    /** @var list<array<string, mixed>> Every params array passed to deleteByQuery(). */
    public array $deleteByQueryCalls = [];

    /** @var list<array{index: string, alias: string, params: array<string, mixed>}> */
    public array $putAliasCalls = [];

    /** @var list<array<string, mixed>> Every params array passed to search(). */
    public array $searchCalls = [];

    /** @var list<array{alias: string, conditions: array<string, mixed>, newIndex: ?string}> */
    public array $rolloverCalls = [];

    /** Whether existsIndex() reports the physical index as already present. */
    public bool $indexExists = false;

    /** Whether existsAlias() reports the alias as already present. */
    public bool $aliasExists = false;

    /** Default response returned from search(). */
    public array $searchResponse = [
        'hits' => ['total' => ['value' => 0], 'hits' => []],
    ];

    /** Optional per-call resolver, taking precedence over $searchResponse. */
    public ?Closure $searchResolver = null;

    /** Response returned from deleteByQuery(). */
    public array $deleteByQueryResponse = ['deleted' => 0];

    public function ping(): bool
    {
        return true;
    }

    public function search(array $params): array
    {
        $this->searchCalls[] = $params;

        if ($this->searchResolver !== null) {
            return ($this->searchResolver)($params);
        }

        return $this->searchResponse;
    }

    public function index(array $params): void
    {
        $this->indexCalls[] = $params;
    }

    public function bulk(array $params): void
    {
        $this->bulkCalls[] = $params;
    }

    public function deleteByQuery(array $params): array
    {
        $this->deleteByQueryCalls[] = $params;

        return $this->deleteByQueryResponse;
    }

    public function createIndex(array $params): array
    {
        $this->createIndexCalls[] = $params;

        return [];
    }

    public function existsIndex(string $index): bool
    {
        return $this->indexExists;
    }

    public function putAlias(string $index, string $name, array $params = []): void
    {
        $this->putAliasCalls[] = ['index' => $index, 'alias' => $name, 'params' => $params];
    }

    public function existsAlias(string $name): bool
    {
        return $this->aliasExists;
    }

    public function getAlias(string $name): array
    {
        return [];
    }

    public function updateAliases(array $actions): void {}

    public function putLifecyclePolicy(string $name, array $policy): void {}

    public function rollover(string $alias, array $conditions, ?string $newIndex = null): array
    {
        $this->rolloverCalls[] = ['alias' => $alias, 'conditions' => $conditions, 'newIndex' => $newIndex];

        return [];
    }

    /** The document body from the most recent index() call. */
    public function lastIndexedDocument(): array
    {
        $last = $this->indexCalls[array_key_last($this->indexCalls)] ?? [];

        return $last['body'] ?? [];
    }
}
