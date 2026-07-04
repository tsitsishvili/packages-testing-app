<?php

namespace App\Http\Controllers\Api;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tsitsishvili\Documentator\Attributes\BodyParam;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;

#[Group('Webhooks')]
class WebhookController extends Controller
{
    /**
     * Handle an incoming payment-provider callback.
     *
     * IncomingHttpLogMiddleware (attached to this route) records the request to
     * elastic-audit. It reads provider/event/entity from request *attributes*
     * — never URL segments — so we set them here, server-side, before returning.
     */
    #[Summary('Stripe payment webhook')]
    #[Description('Receives Stripe payment callbacks. Logged by elastic-audit as an incoming HTTP request (provider `stripe`).')]
    // #[BodyParam('id', type: 'string', required: true, description: 'Stripe event id.', example: 'evt_1P9abcD')]
    // #[BodyParam('type', type: 'string', required: true, description: 'Stripe event type.', example: 'payment_intent.succeeded')]
    // #[BodyParam('order_id', type: 'string', required: true, description: 'Your order reference.', example: '10042')]
    #[ApiResponse(status: 200, example: ['received' => true])]
    public function stripe(
        Request $request
    ): JsonResponse {
        $succeeded = rand(0, 1) || (bool) rand(0, 1);
        $provider = rand(0, 1) ? Provider::Stripe->value : Provider::Crypto->value;
        $orderId = Str::uuid();

        $request->attributes->set('third_party_provider', $provider);
        $request->attributes->set(
            'third_party_event_type',
            ($succeeded ? EventType::PaymentSucceeded : EventType::PaymentFailed)->value,
        );
        $request->attributes->set('third_party_entity_type', EntityType::Payment->value);
        $request->attributes->set('third_party_entity_id', $orderId);

        return response()->json(['received' => $succeeded], $succeeded ? 200 : 422);
    }
}
