<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStatistic extends Model
{
    protected $fillable = [
        'product_id',
        'search_appearance_count',
        'view_count',
        'unique_users_view_count',
        'add_to_cart_count',
        'unique_users_add_to_cart_count',
        'event_date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
