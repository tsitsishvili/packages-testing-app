<?php

namespace App\Services\Orders;

use App\Data\CreateOrderData;
use App\Data\OrderItemData;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * Application service for the order lifecycle. Prices each requested line from
 * the catalog (via {@see ProductRepository}) and persists through the
 * {@see OrderRepository}, keeping pricing authority on the server.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
    ) {}

    public function place(CreateOrderData $data, User $user): Order
    {
        $productIds = array_map(static fn (OrderItemData $item) => $item->product_id, $data->items);
        $catalog = $this->products->findManyByIds($productIds);

        $lines = [];
        $total = 0.0;

        foreach ($data->items as $index => $item) {
            $product = $catalog->get($item->product_id);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ["Product {$item->product_id} does not exist."],
                ]);
            }

            $unitPrice = (float) $product->price;
            $total += $unitPrice * $item->quantity;

            $lines[] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return $this->orders->create([
            'user_id' => $user->id,
            'reference' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => OrderStatus::Pending,
            'currency' => $data->currency,
            'priority' => $data->priority,
            'total' => round($total, 2),
            'notes' => $data->notes,
            'gift_message' => $data->gift_message instanceof Optional ? null : $data->gift_message,
            'placed_at' => now(),
        ], $lines);
    }

    public function cancel(Order $order): Order
    {
        $order->update(['status' => OrderStatus::Cancelled]);

        return $order->load('items.product');
    }
}
