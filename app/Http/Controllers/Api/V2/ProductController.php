<?php

namespace App\Http\Controllers\Api\V2;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\V2\ProductCollection;
use App\Http\Resources\V2\ProductResource;
use App\Http\Resources\V2\ProductStatisticResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\QueryParam;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;
use Tsitsishvili\Documentator\Attributes\TagDescription;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

/**
 * v2 of the Products API. Validation is shared with v1 (same FormRequests);
 * the difference is the more structured response shape (see the v2 Resources).
 */
#[Group('Products (v2)', version: 'v2')]
#[TagDescription('v2 of the catalog API — same validation as v1 with richer, more structured response shapes. A distinct tag name (from v1\'s "Products") keeps the two versions from colliding when the exported spec is consumed by generic OpenAPI tooling.')]
class ProductController extends Controller
{
    #[Summary('List products')]
    #[Description('Returns a paginated list of products, newest first, in the v2 shape.')]
    #[QueryParam('per_page', type: 'integer', required: false, description: 'Items per page (1–100, default 15).', example: 15)]
    #[ApiResponse(status: 200, resource: ProductResource::class, paginated: true)]
    public function index(): ProductCollection
    {
        $perPage = (int) min(request()->integer('per_page', 15), 100);

        return new ProductCollection(
            Product::query()->latest()->paginate($perPage)
        );
    }

    #[Summary('Create a product')]
    #[Description('Creates a product from the validated payload and returns it in the v2 shape with a `201` status.')]
    #[Authenticated]
    #[ApiResponse(status: 201, resource: ProductResource::class, description: 'Product created.')]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Summary('Show a product')]
    #[Description('Returns a single product resolved by its ID, in the v2 shape.')]
    #[ApiResponse(status: 200, resource: ProductResource::class)]
    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product);
    }

    #[Summary('Update a product')]
    #[Description('Updates the product from the validated payload and returns the fresh resource in the v2 shape.')]
    #[Authenticated]
    #[ApiResponse(status: 200, resource: ProductResource::class, description: 'Product updated.')]
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return ProductResource::make($product);
    }

    #[Summary('Delete a product')]
    #[Description('Permanently deletes the product and returns an empty `204` response.')]
    #[Authenticated]
    #[ApiResponse(status: 204, description: 'Product deleted.')]
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    #[Summary('List a product\'s daily statistics')]
    #[Description('Returns the aggregated per-day statistics rows produced by the `product:aggregate-statistics` pipeline, grouped per metric.')]
    #[ApiResponse(status: 200, resource: ProductStatisticResource::class, collection: true)]
    public function statistics(Product $product): AnonymousResourceCollection
    {
        return ProductStatisticResource::collection(
            $product->statistics()->orderByDesc('event_date')->get()
        );
    }

    #[Summary('Sync a product to the external catalog')]
    #[Description('Pushes the product to the external catalog service over HTTP. The outgoing call is recorded by elastic-audit (provider `catalog`, event `catalog.sync`).')]
    #[Authenticated]
    #[ApiResponse(status: 200, description: 'Sync dispatched.', example: ['synced' => true, 'catalog_status' => 200])]
    public function sync(Product $product): JsonResponse
    {
        $context = HttpLogContext::forEntity(
            entityType: EntityType::Product,
            entityId: (string) $product->id,
        );

        // Every request made through this client is logged by elastic-audit.
        $response = HttpLog::make(Provider::Catalog, EventType::CatalogSync, $context)
            ->post('https://jsonplaceholder.typicode.com/posts', [
                'title' => $product->name,
                'body' => $product->description,
                'price' => $product->price,
            ]);

        return response()->json([
            'synced' => $response->successful(),
            'catalog_status' => $response->status(),
        ]);
    }
}
