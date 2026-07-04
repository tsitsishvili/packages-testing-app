<?php

namespace App\Data;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use Spatie\LaravelData\Data;

/**
 * Filters for the order listing. Because the listing route is a GET, documentator
 * turns each of these properties into a documented query parameter rather than a
 * body field.
 */
class SearchOrdersData extends Data
{
    public function __construct(
        public ?OrderStatus $status = null,
        public ?Currency $currency = null,
        public ?float $min_total = null,
        public int $page = 1,
        public int $per_page = 15,
    ) {}
}
