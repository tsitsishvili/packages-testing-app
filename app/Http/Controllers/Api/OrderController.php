<?php

namespace App\Http\Controllers\Api;

use App\Data\CreateOrderData;
use App\Data\OrderData;
use App\Data\SearchOrdersData;
use App\Enums\FulfillmentPriority;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShipOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Repositories\OrderRepository;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Deprecated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\Hidden;
use Tsitsishvili\Documentator\Attributes\PathParam;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;

/**
 * Every endpoint here is authenticated (class-level #[Authenticated]) and tagged
 * "Orders" (class-level #[Group]). The methods deliberately mix the inference
 * paths documentator supports: spatie/laravel-data request + response objects,
 * a FormRequest, a model-bound path param, and inline attribute overrides.
 */
#[Group('Orders')]
#[Authenticated]
class OrderController extends Controller
{
    private const string SHIPMENT_RESPONSE_TYPE = 'array{id: int, order_id: int, tracking_number: string, carrier: string, weight_grams: int, declared_value: string, parcel_count: int, origin_ip: string, label_filename: string|null, shipped_at: date-time, created_at: date-time, updated_at: date-time}';

    private const array SHIPMENT_RESPONSE_EXAMPLE = [
        'id' => 1,
        'order_id' => 42,
        'tracking_number' => 'AB123456789CD',
        'carrier' => 'fedex',
        'weight_grams' => 1500,
        'declared_value' => '199.99',
        'parcel_count' => 2,
        'origin_ip' => '10.0.0.1',
        'label_filename' => 'label.jpg',
        'shipped_at' => '2026-08-04T12:00:00.000000Z',
        'created_at' => '2026-08-04T12:00:00.000000Z',
        'updated_at' => '2026-08-04T12:00:00.000000Z',
    ];

    public function __construct(
        private readonly OrderService $service,
        private readonly OrderRepository $orders,
    ) {}

    #[Summary('List orders')]
    #[Description('Returns the authenticated user\'s orders, newest first, with optional status, currency, minimum-total, and pagination filters.')]
    #[ApiResponse(status: 200, resource: OrderResource::class, paginated: true)]
    public function index(SearchOrdersData $query): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orders->paginateForUser(request()->user(), $query)
        );
    }

    #[Summary('Query orders')]
    #[Description('Searches the authenticated user\'s orders using structured criteria in an HTTP `QUERY` request body.')]
    #[ApiResponse(status: 200, resource: OrderResource::class, paginated: true)]
    public function query(SearchOrdersData $criteria): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orders->paginateForUser(request()->user(), $criteria)
        );
    }

    /**
     * Place an order.
     *
     * Creates an order for the authenticated user and prices every requested
     * line from the server-side product catalog.
     */
    public function store(CreateOrderData $data): OrderData
    {
        $order = $this->service->place($data, request()->user());

        return OrderData::fromModel($order);
    }

    #[Summary('Show an order')]
    #[Description('Returns an order with its priced line items and product names.')]
    public function show(Order $order): OrderData
    {
        return OrderData::fromModel($order->load('items.product'));
    }

    #[Summary('Update an order')]
    #[Description('Adjusts an order\'s status, fulfillment priority, notes, or scheduled date.')]
    public function update(UpdateOrderRequest $request, Order $order): OrderData
    {
        $validated = $request->validated();

        $order->fill(array_filter([
            'status' => $validated['status'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'placed_at' => $validated['scheduled_for'] ?? null,
        ], static fn ($value) => $value !== null));

        if (array_key_exists('priority', $validated)) {
            $order->priority = FulfillmentPriority::from((int) $validated['priority']);
        }

        $order->save();

        return OrderData::fromModel($order->load('items.product'));
    }

    #[Summary('Cancel an order')]
    #[Description('Marks the order cancelled. **Deprecated** — prefer `DELETE /api/orders/{order}`.')]
    #[Deprecated]
    public function cancel(Order $order): JsonResponse
    {
        $this->service->cancel($order);

        return response()->json([
            'cancelled' => true,
            'status' => $order->status->value,
        ]);
    }

    #[Summary('Delete an order')]
    #[Description('Permanently deletes the order. The route requires an authenticated Sanctum bearer token.')]
    #[ApiResponse(status: 204, description: 'Order deleted.')]
    public function destroy(Order $order): Response
    {
        $order->delete();

        return response()->noContent();
    }

    #[Summary('Ship an order')]
    #[Description('Creates a shipment, marks the order shipped, and returns the shipment. Send `multipart/form-data` when attaching the optional label image.')]
    #[ApiResponse(status: 201, type: self::SHIPMENT_RESPONSE_TYPE, description: 'Shipment created.', example: self::SHIPMENT_RESPONSE_EXAMPLE)]
    public function ship(ShipOrderRequest $request, Order $order): Shipment
    {
        $shipment = $order->shipment()->create([
            'tracking_number' => $request->validated('tracking_number'),
            'carrier' => $request->validated('carrier'),
            'weight_grams' => (int) $request->validated('weight_grams'),
            'declared_value' => $request->validated('declared_value'),
            'parcel_count' => (int) $request->validated('parcel_count'),
            'origin_ip' => $request->validated('origin_ip'),
            'label_filename' => $request->file('label')?->getClientOriginalName(),
            'shipped_at' => now(),
        ]);

        $order->update(['status' => OrderStatus::Shipped]);

        return $shipment;
    }

    #[Summary('Show an order\'s shipment')]
    #[Description('Returns the order\'s shipment. A missing order or an order without a shipment returns `404`.')]
    #[PathParam('order', type: 'integer', description: 'ID of the order whose shipment to return.', example: 42)]
    #[ApiResponse(status: 200, type: self::SHIPMENT_RESPONSE_TYPE, example: self::SHIPMENT_RESPONSE_EXAMPLE)]
    public function shipment(Order $order): Shipment
    {
        return $order->shipment()->firstOrFail();
    }

    /**
     * Reconciliation fixture — excluded from the docs with #[Hidden] even
     * though it lives on a documented (api/*) route. Hidden controls
     * documentation visibility, not route authorization.
     */
    #[Hidden]
    public function reconcile(Order $order): JsonResponse
    {
        return response()->json(['reconciled' => true, 'order' => $order->reference]);
    }
}
