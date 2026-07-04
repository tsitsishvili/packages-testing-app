<?php

declare(strict_types=1);

namespace App\Enums\ElasticAudit;

use Tsitsishvili\ElasticAudit\Contracts\EventTypeContract;

// String-backed so values land directly in ES keyword fields.
enum EventType: string implements EventTypeContract
{
    // Incoming third-party callbacks.
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case OrderShipped = 'order.shipped';

    // Outgoing requests we initiate.
    case CatalogSync = 'catalog.sync';

    public function getValue(): string
    {
        return $this->value;
    }
}
