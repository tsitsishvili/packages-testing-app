<?php

namespace App\Enums;

/**
 * Lifecycle of an order. String-backed so it round-trips through the
 * `orders.status` column cast and is documented as a string enum.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}
