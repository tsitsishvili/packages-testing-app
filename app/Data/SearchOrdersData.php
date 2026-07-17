<?php

namespace App\Data;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use Spatie\LaravelData\Data;

/**
 * Filters for the order listing. Documentator emits these properties as URI query
 * parameters for GET and as request-body fields for HTTP QUERY.
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
