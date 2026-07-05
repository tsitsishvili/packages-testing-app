<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use Tests\TestCase;
use Tsitsishvili\ElasticAudit\Contracts\EntityTypeContract;
use Tsitsishvili\ElasticAudit\Contracts\EventTypeContract;
use Tsitsishvili\ElasticAudit\Contracts\ProviderContract;

/**
 * The elastic-audit middleware resolves the app's backed enums by class name
 * from config('http_logs.enums'). If those classes drift out of the package's
 * contracts, logging breaks at runtime rather than at compile time — so pin the
 * wiring down with a test.
 */
class ElasticAuditEnumsTest extends TestCase
{
    public function test_app_enums_implement_the_package_contracts(): void
    {
        $this->assertInstanceOf(ProviderContract::class, Provider::Stripe);
        $this->assertInstanceOf(EventTypeContract::class, EventType::PaymentSucceeded);
        $this->assertInstanceOf(EntityTypeContract::class, EntityType::Order);

        // As of package v3 the contracts extend PHP's BackedEnum and the package
        // reads ->value directly; the enums must stay string-backed.
        $this->assertInstanceOf(\BackedEnum::class, Provider::Stripe);
        $this->assertInstanceOf(\BackedEnum::class, EventType::PaymentSucceeded);
        $this->assertInstanceOf(\BackedEnum::class, EntityType::Order);
    }

    public function test_backing_values_are_the_expected_strings(): void
    {
        $this->assertSame('stripe', Provider::Stripe->value);
        $this->assertSame('payment.succeeded', EventType::PaymentSucceeded->value);
        $this->assertSame('order', EntityType::Order->value);
    }

    public function test_config_points_at_contract_implementations(): void
    {
        $enums = config('http_logs.enums');

        $this->assertSame(Provider::class, $enums['provider']);
        $this->assertSame(EventType::class, $enums['event_type']);
        $this->assertSame(EntityType::class, $enums['entity_type']);

        $this->assertTrue(is_subclass_of($enums['provider'], ProviderContract::class));
        $this->assertTrue(is_subclass_of($enums['event_type'], EventTypeContract::class));
        $this->assertTrue(is_subclass_of($enums['entity_type'], EntityTypeContract::class));
    }

    public function test_the_default_entity_type_resolves_to_a_real_case(): void
    {
        $default = config('http_logs.enums.entity_type_default');

        $this->assertNotNull(EntityType::tryFrom($default));
    }

    public function test_payment_provider_values_cover_the_stripe_case(): void
    {
        // The PaymentRedactor is applied to providers whose ->value is listed
        // here; Stripe's callbacks carry card data, so it must be present.
        $this->assertContains(Provider::Stripe->value, config('http_logs.payment_provider_values'));
    }
}
