<?php

namespace App\Http\Resources\V2;

use App\Models\ProductStatistic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * v2 daily-statistics representation.
 *
 * Groups the flat v1 counters into per-metric blocks (with their unique-user
 * variants nested alongside the total) for a clearer payload.
 *
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
            'metrics' => [
                'search_appearances' => [
                    'total' => $this->search_appearance_count,
                ],
                'views' => [
                    'total' => $this->view_count,
                    'unique_users' => $this->unique_users_view_count,
                ],
                'add_to_cart' => [
                    'total' => $this->add_to_cart_count,
                    'unique_users' => $this->unique_users_add_to_cart_count,
                ],
            ],
        ];
    }
}
