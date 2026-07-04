<?php

namespace App\Http\Resources\V2;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * v2 product representation.
 *
 * Differs from v1 by nesting pricing and timestamps and exposing a self link,
 * so clients can rely on a more structured payload without breaking v1.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'product',
            'name' => $this->name,
            'description' => $this->description,
            'pricing' => [
                'amount' => (float) $this->price,
                'formatted' => (string) $this->price,
            ],
            'timestamps' => [
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'links' => [
                'self' => route('api.v2.products.show', ['product' => $this->id]),
            ],
        ];
    }
}
