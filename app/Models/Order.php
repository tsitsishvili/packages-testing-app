<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FulfillmentPriority;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tsitsishvili\ElasticAudit\Traits\ActivityLoggable;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use ActivityLoggable, HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'status',
        'currency',
        'priority',
        'total',
        'notes',
        'gift_message',
        'placed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'currency' => Currency::class,
        'priority' => FulfillmentPriority::class,
        'total' => 'decimal:2',
        'placed_at' => 'immutable_datetime',
    ];

    // Entity type recorded on every activity log row for this model.
    protected string $activityEntityType = 'order';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
