<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use Illuminate\Support\Facades\Http;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

/**
 * Verifies the outgoing-request logging path: a request made through
 * HttpLog::make() is captured by the Guzzle middleware, dispatched as a job
 * (run synchronously under the `sync` queue) and indexed as a redacted document.
 */
class HttpLogOutgoingTest extends ElasticAuditTestCase
{
    private function context(): HttpLogContext
    {
        return HttpLogContext::forEntity(EntityType::Order, entityId: '42');
    }

    public function test_it_logs_an_outgoing_request_as_an_indexed_document(): void
    {
        config(['http_logs.enabled' => true]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        HttpLog::make(Provider::Catalog, EventType::CatalogSync, $this->context())
            ->get('https://catalog.example/items');

        $this->assertCount(1, $this->es->indexCalls);

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame('catalog', $doc['provider']);
        $this->assertSame('catalog.sync', $doc['event_type']);
        $this->assertSame('outgoing', $doc['direction']);
        $this->assertSame('GET', $doc['http']['method']);
        $this->assertSame(200, $doc['http']['status_code']);
        $this->assertTrue($doc['success']);
    }

    public function test_it_redacts_sensitive_headers_and_body_fields(): void
    {
        config(['http_logs.enabled' => true]);
        Http::fake(['*' => Http::response([], 200)]);

        HttpLog::make(Provider::Catalog, EventType::CatalogSync, $this->context())
            ->withHeaders(['Authorization' => 'Bearer super-secret-token'])
            ->post('https://catalog.example/sync', [
                'password' => 'hunter2-should-vanish',
                'sku' => 'WIDGET-1',
            ]);

        $encoded = json_encode($this->es->lastIndexedDocument());

        $this->assertStringContainsString('[REDACTED]', $encoded);
        $this->assertStringNotContainsString('super-secret-token', $encoded);
        $this->assertStringNotContainsString('hunter2-should-vanish', $encoded);
        // Non-sensitive fields survive redaction.
        $this->assertStringContainsString('WIDGET-1', $encoded);
    }

    public function test_it_records_a_failed_upstream_response(): void
    {
        config(['http_logs.enabled' => true]);
        Http::fake(['*' => Http::response(['error' => 'boom'], 503)]);

        HttpLog::make(Provider::Catalog, EventType::CatalogSync, $this->context())
            ->get('https://catalog.example/items');

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame(503, $doc['http']['status_code']);
        $this->assertFalse($doc['success']);
    }

    public function test_it_logs_nothing_when_http_logging_is_disabled(): void
    {
        config(['http_logs.enabled' => false]);
        Http::fake(['*' => Http::response([], 200)]);

        HttpLog::make(Provider::Catalog, EventType::CatalogSync, $this->context())
            ->get('https://catalog.example/items');

        $this->assertEmpty($this->es->indexCalls);
    }
}
