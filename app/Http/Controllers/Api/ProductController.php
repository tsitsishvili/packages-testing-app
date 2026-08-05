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
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\QueryParam;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;
use Tsitsishvili\Documentator\Attributes\TagDescription;
use Tsitsishvili\ElasticAudit\DataTransferObjects\HttpLogContext;
use Tsitsishvili\ElasticAudit\Facades\HttpLog;

#[Group('Products', version: 'v1')]
#[TagDescription('Browse and manage the product catalog. Public reads; writes require a `Bearer` token.')]
class ProductController extends Controller
{
    private const string PRODUCT_RESPONSE_TYPE = 'array{data: array{id: int, name: string, description: string|null, price: string, created_at: date-time, updated_at: date-time}}';

    private const array PRODUCT_RESPONSE_EXAMPLE = [
        'data' => [
            'id' => 1,
            'name' => 'Reference product',
            'description' => 'A product returned by the integration fixture.',
            'price' => '19.99',
            'created_at' => '2026-08-04T12:00:00.000000Z',
            'updated_at' => '2026-08-04T12:00:00.000000Z',
        ],
    ];

    #[Summary('List products')]
    #[Description('Returns a paginated list of products, newest first.')]
    #[QueryParam('per_page', type: 'integer', required: false, description: 'Items requested per page. Values above 100 are capped; the default is 15.', example: 15)]
    #[ApiResponse(status: 200, resource: ProductResource::class, paginated: true)]
    public function index(): ProductCollection
    {
        $perPage = (int) min(request()->integer('per_page', 15), 100);

        return new ProductCollection(
            Product::query()->latest()->paginate($perPage)
        );
    }

    #[Summary('Search the product catalog')]
    #[Description('Accepts name or price filters, supported sorts, relationship includes, sparse field selection, and JSON:API pagination parameters through the Query Builder integration fixture.')]
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
    #[Description('Creates a product from the validated payload.')]
    #[Authenticated]
    #[ApiResponse(status: 201, type: self::PRODUCT_RESPONSE_TYPE, description: 'Product created.', example: self::PRODUCT_RESPONSE_EXAMPLE)]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Summary('Show a product')]
    #[Description('Returns a single product resolved by its ID.')]
    #[ApiResponse(status: 200, type: self::PRODUCT_RESPONSE_TYPE, example: self::PRODUCT_RESPONSE_EXAMPLE)]
    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product);
    }

    #[Summary('Update a product')]
    #[Description('Updates the product from the validated payload and returns its current representation.')]
    #[Authenticated]
    #[ApiResponse(status: 200, type: self::PRODUCT_RESPONSE_TYPE, description: 'Product updated.', example: self::PRODUCT_RESPONSE_EXAMPLE)]
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
    #[Description('Performs a synchronous request to the demo JSONPlaceholder catalog endpoint and reports its status. Elastic Audit records the outgoing call as provider `catalog` and event `catalog.sync`.')]
    #[Authenticated]
    #[ApiResponse(status: 200, type: 'array{synced: bool, catalog_status: int}', description: 'Demo catalog request completed.', example: ['synced' => true, 'catalog_status' => 200])]
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
