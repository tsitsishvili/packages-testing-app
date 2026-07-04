<?php

namespace App\Data;

use App\Enums\Currency;
use App\Enums\FulfillmentPriority;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * The order representation returned by the API. Used as a controller return type
 * so documentator derives the success-response schema from it — including the
 * nested priced lines, the string/int enums and the `date-time` timestamps.
 */
class OrderData extends Data
{
    /**
     * @param  array<int, OrderLineData>  $items
     */
    public function __construct(
        public int $id,
        public string $reference,
        public OrderStatus $status,
        public Currency $currency,
        public FulfillmentPriority $priority,
        public float $total,
        public ?string $notes,
        public ?string $gift_message,
        #[DataCollectionOf(OrderLineData::class)]
        public array $items,
        public CarbonImmutable $placed_at,
        public CarbonImmutable $created_at,
    ) {}

    public static function fromModel(Order $order): self
    {
        $lines = $order->items->map(fn (OrderItem $item) => new OrderLineData(
            product_id: $item->product_id,
            product_name: $item->product?->name ?? 'Unknown product',
            quantity: $item->quantity,
            unit_price: (float) $item->unit_price,
            line_total: round((float) $item->unit_price * $item->quantity, 2),
        ))->all();

        return new self(
            id: $order->id,
            reference: $order->reference,
            status: $order->status,
            currency: $order->currency,
            priority: $order->priority,
            total: (float) $order->total,
            notes: $order->notes,
            gift_message: $order->gift_message,
            items: $lines,
            placed_at: $order->placed_at,
            created_at: $order->created_at->toImmutable(),
        );
    }
}
