<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * A single line of an inbound order request: which product and how many. The
 * unit price is intentionally not accepted from the client — the service prices
 * each line from the catalog.
 */
class OrderItemData extends Data
{
    public function __construct(
        public int $product_id,
        public int $quantity,
    ) {}
}
