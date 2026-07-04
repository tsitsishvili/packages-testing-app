<?php

namespace App\Data;

use App\Enums\Currency;
use App\Enums\FulfillmentPriority;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The request body for placing an order. Documentator infers every field —
 * enums (string + int backed), a nested collection of {@see OrderItemData}, and
 * the nullable/optional fields — straight from the typed properties, no
 * attributes required.
 */
class CreateOrderData extends Data
{
    /**
     * @param  array<int, OrderItemData>  $items
     */
    public function __construct(
        public Currency $currency,
        public FulfillmentPriority $priority,
        #[DataCollectionOf(OrderItemData::class)]
        #[Min(1)]
        public array $items,
        #[Max(2000)]
        public ?string $notes = null,
        public ?string $coupon = null,
        // A spatie `Optional` field: absent from the payload entirely when not
        // sent (vs. an explicit null). The `new Optional()` default is what lets
        // documentator see it as an optional, non-nullable string.
        public string|Optional $gift_message = new Optional,
    ) {}
}
