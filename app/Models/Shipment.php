<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dispatched shipment for an order. Returned as a bare Eloquent model from the
 * API (no Resource), so documentator types the response via `extractModel`: the
 * string columns below come from these `@property` annotations, the rest from
 * $casts.
 *
 * @property int $id
 * @property int $order_id
 * @property string $tracking_number
 * @property string $carrier
 * @property int $weight_grams
 * @property float $declared_value
 * @property int $parcel_count
 * @property string $origin_ip
 * @property string|null $label_filename
 * @property CarbonImmutable $shipped_at
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'carrier',
        'weight_grams',
        'declared_value',
        'parcel_count',
        'origin_ip',
        'label_filename',
        'shipped_at',
    ];

    protected $casts = [
        'weight_grams' => 'integer',
        'declared_value' => 'decimal:2',
        'parcel_count' => 'integer',
        'shipped_at' => 'immutable_datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
