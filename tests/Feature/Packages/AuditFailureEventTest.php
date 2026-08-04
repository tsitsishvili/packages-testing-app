<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tsitsishvili\ElasticAudit\DataTransferObjects\ActivityLogContext;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Events\AuditOperationFailed;
use Tsitsishvili\ElasticAudit\Facades\ActivityLog;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

/**
 * Covers App\Listeners\ReportAuditOperationFailure: elastic-audit swallows its
 * own failures so they cannot break the audited request, and reports them
 * through AuditOperationFailed instead. The application mirrors those onto the
 * dedicated `audit` log channel.
 */
class AuditFailureEventTest extends ElasticAuditTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['http_logs.enabled' => true, 'activity_logs.enabled' => true]);
    }

    public function test_it_writes_a_failed_activity_indexing_attempt_to_the_audit_channel(): void
    {
        $channel = $this->fakeAuditChannel();

        // Two reports arrive under the `sync` queue: the indexing job fails, and
        // the exception then propagates back out of the dispatch call into the
        // logger's own capture guard. On a real worker only the first occurs.
        $channel->shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Elastic audit operation failed'
                && $context['subsystem'] === AuditOperationFailed::SUBSYSTEM_ACTIVITY
                && $context['stage'] === AuditOperationFailed::STAGE_INDEXING
                && $context['exception'] === RuntimeException::class
                && str_contains($context['message'], 'elasticsearch unreachable')
                && $context['context']['action'] === 'order.cancelled');

        $channel->shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['stage'] === AuditOperationFailed::STAGE_CAPTURE);

        $this->es->indexException = new RuntimeException('elasticsearch unreachable');

        ActivityLog::record(
            action: 'order.cancelled',
            context: ActivityLogContext::forActor(
                actorType: 'user',
                actorId: 1,
                entityType: 'order',
                entityId: '42',
            ),
        );
    }

    public function test_a_failing_indexer_does_not_break_the_audited_provider_call(): void
    {
        $this->fakeAuditChannel()->shouldReceive('warning')->zeroOrMoreTimes();

        $this->es->indexException = new RuntimeException('elasticsearch unreachable');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $response = HttpLog::make(
            provider: Provider::Catalog,
            eventType: EventType::CatalogSync,
            context: HttpLogContext::forEntity(EntityType::Product, entityId: '7'),
        )->post('https://catalog.example/items', ['sku' => 'WIDGET-1']);

        $this->assertTrue($response->successful());
        $this->assertSame(['ok' => true], $response->json());
    }

    public function test_the_listener_reports_the_subsystem_and_stage_of_the_failure(): void
    {
        $channel = $this->fakeAuditChannel();

        $channel->shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $context['subsystem'] === 'http'
                    && $context['stage'] === 'capture'
                    && $context['context']['event_id'] === '01J0000000000000000000';
            });

        event(new AuditOperationFailed(
            subsystem: AuditOperationFailed::SUBSYSTEM_HTTP,
            stage: AuditOperationFailed::STAGE_CAPTURE,
            exceptionClass: RuntimeException::class,
            message: 'capture failed',
            context: ['event_id' => '01J0000000000000000000'],
        ));
    }

    /**
     * The package logs its own failure on the default channel; only the
     * application listener writes to `audit`, so standing a mock in for that one
     * channel keeps the two apart.
     */
    private function fakeAuditChannel(): MockInterface
    {
        $channel = Mockery::mock(LoggerInterface::class);

        Log::shouldReceive('channel')->with('audit')->andReturn($channel);
        Log::shouldReceive('error')->zeroOrMoreTimes();

        return $channel;
    }
}
