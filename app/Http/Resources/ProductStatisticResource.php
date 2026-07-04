<?php

namespace App\Http\Resources;

use App\Models\ProductStatistic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductStatistic
 */
class ProductStatisticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'event_date' => $this->event_date,
            'search_appearance_count' => $this->search_appearance_count,
            'view_count' => $this->view_count,
            'unique_users_view_count' => $this->unique_users_view_count,
            'add_to_cart_count' => $this->add_to_cart_count,
            'unique_users_add_to_cart_count' => $this->unique_users_add_to_cart_count,
        ];
    }
}
