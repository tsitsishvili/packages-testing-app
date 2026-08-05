<?php

namespace App\Actions;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\OperationId;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;

/**
 * A lorisleiva/laravel-actions single-action controller. Documentator reads the
 * request body from `rules()`; operation metadata and the explicit runtime
 * response envelope come from attributes on `asController()`, which is where
 * the route points.
 */
#[Group('Products', version: 'v1')]
#[Authenticated]
class PublishProduct
{
    use AsController;

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

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:web,mobile,retail'],
            'published_at' => ['nullable', 'date'],
            'notify_subscribers' => ['nullable', 'boolean'],
        ];
    }

    public function handle(Product $product, string $channel, ?Carbon $publishedAt = null): JsonResponse
    {
        // No dedicated column in this demo schema — touch() stands in for the
        // real "publish" side effect so the endpoint is exercisable end-to-end.
        $product->touch();

        return ProductResource::make($product)->response();
    }

    #[Summary('Run the product publication fixture')]
    #[Description('Validates publication options and updates the product timestamp. This integration fixture does not persist channel-specific publication state or send subscriber notifications.')]
    #[OperationId('publishProduct')]
    #[ApiResponse(status: 200, type: self::PRODUCT_RESPONSE_TYPE, description: 'Publication fixture completed.', example: self::PRODUCT_RESPONSE_EXAMPLE)]
    public function asController(ActionRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        return $this->handle(
            $product,
            $validated['channel'],
            isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : null,
        );
    }
}
