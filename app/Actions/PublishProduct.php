<?php

namespace App\Actions;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Tsitsishvili\Documentator\Attributes\Authenticated;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\OperationId;
use Tsitsishvili\Documentator\Attributes\Summary;

/**
 * A lorisleiva/laravel-actions single-action controller. Documentator reads the
 * request body from `rules()` and the success response from the `handle()`
 * return type; the operation metadata comes from the attributes on
 * `asController()`, which is where the route points.
 */
#[Group('Products', version: 'v1')]
#[Authenticated]
class PublishProduct
{
    use AsController;

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

    public function handle(Product $product, string $channel, ?Carbon $publishedAt = null): ProductResource
    {
        // No dedicated column in this demo schema — touch() stands in for the
        // real "publish" side effect so the endpoint is exercisable end-to-end.
        $product->touch();

        return ProductResource::make($product);
    }

    #[Summary('Publish a product')]
    #[Description('Publishes a product to a sales channel. The request body is inferred from `PublishProduct::rules()` and the response from the `handle()` return type — no FormRequest or #[BodyParam] needed.')]
    #[OperationId('publishProduct')]
    public function asController(ActionRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        return $this->handle(
            $product,
            $validated['channel'],
            isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : null,
        );
    }
}
