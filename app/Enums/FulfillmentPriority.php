<?php

namespace App\Enums;

/**
 * How quickly an order should ship. Integer-backed on purpose: it documents as
 * an `integer` enum (1/2/3), exercising the int-backed enum path in both the
 * spatie/laravel-data schema reader and the FormRequest `Rule::in` parser.
 */
enum FulfillmentPriority: int
{
    case Standard = 1;
    case Express = 2;
    case Overnight = 3;
}
