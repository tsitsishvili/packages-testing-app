<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use Illuminate\Http\Request;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

/**
 * Verifies the incoming-callback logging path used for third-party webhooks
 * (e.g. payment provider callbacks): HttpLog::logIncoming() indexes a redacted
 * document tagged as an incoming request.
 */
class HttpLogIncomingTest extends ElasticAuditTestCase
{
    private function incomingRequest(): Request
    {
        return Request::create(
            uri: '/api/webhooks/stripe?api_key=leak-me',
            method: 'POST',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer super-secret-token',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['password' => 'hunter2-should-vanish', 'amount' => 1999]),
        );
    }

    private function context(): HttpLogContext
    {
        return HttpLogContext::forEntity(EntityType::Payment, entityId: 'pi_123', externalId: 'evt_123');
    }

    public function test_it_logs_an_incoming_callback(): void
    {
        config(['http_logs.enabled' => true]);

        HttpLog::logIncoming(
            $this->incomingRequest(),
            Provider::Stripe,
            EventType::PaymentSucceeded,
            $this->context(),
            latencyMs: 12,
            httpStatusCode: 200,
            success: true,
        );

        $this->assertCount(1, $this->es->indexCalls);

        $doc = $this->es->lastIndexedDocument();
        $this->assertSame('stripe', $doc['provider']);
        $this->assertSame('payment.succeeded', $doc['event_type']);
        $this->assertSame('incoming', $doc['direction']);
        $this->assertSame('POST', $doc['http']['method']);
        // The query string is stripped from the stored URL — it may carry keys.
        $this->assertStringNotContainsString('leak-me', $doc['http']['url']);
    }

    public function test_it_redacts_sensitive_data_on_the_incoming_request(): void
    {
        config(['http_logs.enabled' => true]);

        HttpLog::logIncoming(
            $this->incomingRequest(),
            Provider::Stripe,
            EventType::PaymentSucceeded,
            $this->context(),
        );

        $encoded = json_encode($this->es->lastIndexedDocument());

        $this->assertStringContainsString('[REDACTED]', $encoded);
        $this->assertStringNotContainsString('super-secret-token', $encoded);
        $this->assertStringNotContainsString('hunter2-should-vanish', $encoded);
    }

    public function test_it_logs_nothing_when_http_logging_is_disabled(): void
    {
        config(['http_logs.enabled' => false]);

        HttpLog::logIncoming(
            $this->incomingRequest(),
            Provider::Stripe,
            EventType::PaymentSucceeded,
            $this->context(),
        );

        $this->assertEmpty($this->es->indexCalls);
    }
}
