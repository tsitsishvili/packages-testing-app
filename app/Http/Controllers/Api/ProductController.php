<?php

namespace App\Http\Controllers\Api;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductStatisticResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\CookieParam;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\HeaderParam;
use Tsitsishvili\Documentator\Attributes\QueryParam;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\ResponseHeader;
use Tsitsishvili\Documentator\Attributes\Server;
use Tsitsishvili\Documentator\Attributes\Summary;
use Tsitsishvili\Documentator\Attributes\TagDescription;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

#[Group('Products', version: 'v1')]
#[TagDescription('Browse and manage the product catalog. Public reads; writes require a `Bearer` token.')]
class ProductController extends Controller
{
    #[Summary('List products')]
    #[Description('Returns a paginated list of products, newest first.')]
    #[QueryParam('per_page', type: 'integer', required: false, description: 'Items per page (1–100, default 15).', example: 15)]
    #[CookieParam('preview_token', description: 'Opaque token that also surfaces unpublished products in the listing.', example: 'pv_9f40d932c4c0')]
    #[ApiResponse(status: 200, resource: ProductResource::class, paginated: true)]
    public function index(): ProductCollection
    {
        $perPage = (int) min(request()->integer('per_page', 15), 100);

        return new ProductCollection(
            Product::query()->latest()->paginate($perPage)
        );
    }

    #[Summary('Search the product catalog')]
    #[Description('Filters, sorts and includes related data via spatie/laravel-query-builder. The `filter[...]`, `sort`, `include` and `fields[...]` query parameters below are inferred from the allowed lists; results are JSON:API paginated (`page[number]` / `page[size]`) via spatie/laravel-json-api-paginate — both the pagination query params and the response envelope are inferred from the return statement.')]
    public function search(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            QueryBuilder::for(Product::class)
                ->allowedFilters(
                    AllowedFilter::partial('name'),
                    AllowedFilter::exact('price'),
                )
                ->allowedSorts('name', 'price', 'created_at')
                ->allowedIncludes('statistics')
                ->allowedFields('id', 'name', 'price', 'description')
                ->defaultSort('-created_at')
                ->jsonPaginate()
        );
    }

    #[Summary('Create a product')]
    #[Description('Creates a product from the validated payload and returns it with a `201` status. The body is inferred from `StoreProductRequest::rules()`.')]
    #[Authenticated]
    #[HeaderParam('Idempotency-Key', required: false, description: 'Client-supplied key; replaying a request with the same key returns the original result instead of creating a duplicate.', example: 'a1b2c3d4-e5f6')]
    #[ApiResponse(status: 201, resource: ProductResource::class, description: 'Product created.')]
    #[ResponseHeader(201, 'Location', description: 'Canonical URL of the newly created product.', example: '/api/products/101')]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Summary('Show a product')]
    #[Description('Returns a single product resolved by its ID.')]
    #[ApiResponse(status: 200, resource: ProductResource::class)]
    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product);
    }

    #[Summary('Update a product')]
    #[Description('Updates the product from the validated payload and returns the fresh resource. The body is inferred from `UpdateProductRequest::rules()`.')]
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
    #[Description('Returns the aggregated per-day statistics rows produced by the `product:aggregate-statistics` pipeline.')]
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
    #[Server('https://catalog.example.com', description: 'External product catalog service that receives the sync call.')]
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
