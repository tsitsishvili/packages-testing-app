<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Tsitsishvili\Documentator\Attributes\UsesModel;

/**
 * Used for the paginated listing. `#[UsesModel]` tells documentator which model
 * this resource wraps so the field types (the enum casts, the `decimal` total,
 * the `immutable_datetime` timestamp) are read from the model's $casts.
 *
 * @mixin Order
 */
#[UsesModel(Order::class)]
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'currency' => $this->currency,
            'priority' => $this->priority,
            'total' => $this->total,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'created_at' => $this->created_at,
        ];
    }
}
