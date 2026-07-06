<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeNewsletterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\Summary;

/**
 * The subscription payload is validated through a typed FormRequest
 * (SubscribeNewsletterRequest) so documentator infers the request body from its
 * rules() — types, required flags, the `frequency` enum and the `topics[]` array
 * all come across without a single #[BodyParam] override.
 */
#[Group('Newsletter')]
class NewsletterController extends Controller
{
    #[Summary('Subscribe to the newsletter')]
    #[Description('Registers an email address for the newsletter. Body parameters are inferred from `SubscribeNewsletterRequest::rules()`.')]
    public function subscribe(SubscribeNewsletterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'subscribed' => true,
            'email' => $validated['email'],
            'frequency' => $validated['frequency'],
        ], Response::HTTP_ACCEPTED);
    }
}
