<?php

namespace App\Repositories;

use App\Data\SearchOrdersData;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    /**
     * Persist an order and its line items in a single transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $attributes, array $items): Order
    {
        return DB::transaction(function () use ($attributes, $items) {
            $order = Order::create($attributes);
            $order->items()->createMany($items);

            return $order->load('items.product');
        });
    }

    /**
     * A user's orders, filtered and paginated.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(User $user, SearchOrdersData $filters): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->when($filters->currency, fn ($query, $currency) => $query->where('currency', $currency))
            ->when($filters->min_total, fn ($query, $min) => $query->where('total', '>=', $min))
            ->with('items.product')
            ->latest('placed_at')
            ->paginate(perPage: $filters->per_page, page: $filters->page);
    }
}
