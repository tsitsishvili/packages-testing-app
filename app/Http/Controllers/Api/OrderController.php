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
use Tsitsishvili\Documentator\Attributes\BodyParam;
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
    public function __construct(
        private readonly OrderService $service,
        private readonly OrderRepository $orders,
    ) {}

    #[Summary('List orders')]
    #[Description('Returns the authenticated user\'s orders, newest first. The filters are inferred as query parameters from `SearchOrdersData` because this is a GET route.')]
    #[ApiResponse(status: 200, resource: OrderResource::class, paginated: true)]
    public function index(SearchOrdersData $query): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orders->paginateForUser(request()->user(), $query)
        );
    }

    #[Summary('Query orders')]
    #[Description('Searches orders with structured criteria sent in an HTTP `QUERY` request body. Documentator emits this as an OpenAPI 3.2 `query` operation instead of flattening the criteria into URI parameters.')]
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
     * Creates an order for the authenticated user from a set of line items,
     * pricing each line from the catalog. Both the request body and the 201
     * response are inferred entirely from the `CreateOrderData` / `OrderData`
     * objects — the summary/description here come straight from the docblock.
     *
     * The one exception is `gift_message`: it's a spatie `Optional` union
     * (`string|Optional`), which documentator can't yet read — left to inference
     * it mis-renders as an array — so we pin its schema with an explicit
     * #[BodyParam]. (Attribute overrides run last and fully replace inference.)
     */
    #[BodyParam('gift_message', type: 'string', required: false, description: 'Optional gift message printed on the packing slip.', example: 'Happy birthday!')]
    public function store(CreateOrderData $data): OrderData
    {
        $order = $this->service->place($data, request()->user());

        return OrderData::fromModel($order);
    }

    #[Summary('Show an order')]
    #[Description('Returns a single order with its line items and their products. The response shape is inferred from `OrderData`.')]
    public function show(Order $order): OrderData
    {
        return OrderData::fromModel($order->load('items.product'));
    }

    #[Summary('Update an order')]
    #[Description('Adjusts status, priority, notes or the scheduled date. The body is documented from `UpdateOrderRequest::rules()`.')]
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
    #[ApiResponse(status: 200, description: 'Order cancelled.', example: ['cancelled' => true, 'status' => 'cancelled'])]
    public function cancel(Order $order): JsonResponse
    {
        $this->service->cancel($order);

        return response()->json([
            'cancelled' => true,
            'status' => $order->status->value,
        ]);
    }

    #[Summary('Delete an order')]
    #[Description('Permanently deletes the order. Requires an admin token — documented with the non-default `admin` security scheme.')]
    #[Authenticated('admin')]
    #[ApiResponse(status: 204, description: 'Order deleted.')]
    public function destroy(Order $order): Response
    {
        $order->delete();

        return response()->noContent();
    }

    #[Summary('Ship an order')]
    #[Description('Records the shipment and marks the order shipped. The body is `multipart/form-data` because of the optional `label` image, and the response is the **bare `Shipment` model** — documentator types it from the model\'s $casts and `@property` docblock rather than a Resource.')]
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
    #[Description('Returns the bare `Shipment` model for an order.')]
    #[PathParam('order', type: 'integer', description: 'ID of the order whose shipment to return.', example: 42)]
    public function shipment(Order $order): Shipment
    {
        return $order->shipment()->firstOrFail();
    }

    /**
     * Internal reconciliation hook — excluded from the docs with #[Hidden] even
     * though it lives on a documented (api/*) route.
     */
    #[Hidden]
    public function reconcile(Order $order): JsonResponse
    {
        return response()->json(['reconciled' => true, 'order' => $order->reference]);
    }
}
