<?php

namespace App\Http\Controllers\Api;

use App\Enums\ElasticAudit\EntityType;
use App\Enums\ElasticAudit\EventType;
use App\Enums\ElasticAudit\Provider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\RequestMediaType;
use Tsitsishvili\Documentator\Attributes\Response as ApiResponse;
use Tsitsishvili\Documentator\Attributes\Summary;

#[Group('Webhooks')]
class WebhookController extends Controller
{
    /**
     * Handle an incoming payment-provider callback.
     *
     * The request body is documented entirely from the inline
     * $request->validate([...]) call below — no FormRequest, no #[BodyParam].
     * Documentator parses the rule array for types, the `in:` enum, formats and
     * bounds, and honours the per-field PHPDoc (`@var`, `@example`, `@default`,
     * `@ignoreParam`). Rules are `sometimes`, so partial provider payloads still
     * pass.
     *
     * IncomingHttpLogMiddleware (attached to this route) records the request to
     * elastic-audit. It reads provider/event/entity from request *attributes*
     * — never URL segments — so we set them here, server-side, before returning.
     */
    #[Summary('Stripe payment webhook')]
    #[Description('Receives Stripe payment callbacks. Logged by elastic-audit as an incoming HTTP request (provider `stripe`).')]
    #[RequestMediaType('application/json')]
    #[ApiResponse(status: 200, example: ['received' => true])]
    public function stripe(
        Request $request
    ): JsonResponse {
        $request->validate([
            /**
             * Stripe event id.
             *
             * @example evt_1P9abcD
             */
            'id' => ['sometimes', 'string', 'starts_with:evt_'],

            /** The event type. */
            'type' => ['sometimes', 'string', 'in:payment_intent.succeeded,payment_intent.payment_failed,charge.refunded'],

            /**
             * Amount in the smallest currency unit (e.g. cents).
             *
             * @example 1999
             */
            'amount' => ['sometimes', 'integer', 'min:0'],

            /** ISO-4217 currency code. */
            'currency' => ['sometimes', 'string', 'size:3'],

            /** Customer receipt email, when present on the charge. */
            'receipt_email' => ['sometimes', 'email'],

            /**
             * Idempotency/replay id for the delivery attempt.
             *
             * @var uuid
             */
            'request_id' => ['sometimes', 'string'],

            /**
             * Whether Stripe sent this from live mode.
             *
             * @default false
             */
            'livemode' => ['sometimes', 'boolean'],

            /** Your internal order reference, echoed in the event metadata. */
            'metadata.order_id' => ['sometimes', 'string'],

            /**
             * Raw signature echo — internal only, hidden from the docs.
             *
             * @ignoreParam
             */
            'signature' => ['sometimes', 'string'],
        ]);

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
