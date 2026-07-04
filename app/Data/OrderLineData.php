<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/** A priced line on an order, as returned to the client. */
class OrderLineData extends Data
{
    public function __construct(
        public int $product_id,
        public string $product_name,
        public int $quantity,
        public float $unit_price,
        public float $line_total,
    ) {}
}
