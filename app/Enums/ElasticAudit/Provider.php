<?php

declare(strict_types=1);

namespace App\Enums\ElasticAudit;

use Tsitsishvili\ElasticAudit\Contracts\ProviderContract;

// String-backed so values land directly in ES keyword fields.
// Adding a new case requires bumping HttpLogData::SCHEMA_VERSION.
enum Provider: string implements ProviderContract
{
    // Payment providers — their callbacks arrive as incoming webhooks. Their
    // ->value is listed in config('http_logs.payment_provider_values') so the
    // PaymentRedactor is applied to their bodies instead of the generic one.
    case Stripe = 'stripe';
    case Crypto = 'crypto';

    // Outbound integrations we call ourselves.
    case Catalog = 'catalog';

    public function getValue(): string
    {
        return $this->value;
    }
}
