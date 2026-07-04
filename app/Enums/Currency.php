<?php

namespace App\Enums;

/** ISO 4217 currency codes the catalog can be priced in. */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
}
