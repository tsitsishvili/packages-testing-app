<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tsitsishvili\ElasticAudit\Traits\ActivityLoggable;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use ActivityLoggable, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Entity type recorded on every activity log row for this model.
    protected string $activityEntityType = 'product';

    public function events(): HasMany
    {
        return $this->hasMany(ProductEvent::class);
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(ProductStatistic::class);
    }
}
