<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tsitsishvili\Documentator\Attributes\Description;
use Tsitsishvili\Documentator\Attributes\Group;
use Tsitsishvili\Documentator\Attributes\Summary;

/**
 * A deliberately FormRequest-free controller: the body is validated with an
 * inline $request->validate([...]) call rather than a typed FormRequest or a
 * spatie Data object. It exists to probe how documentator treats inline
 * validation — none of the package's body-inference strategies
 * (ExtractFormRequestRules / ExtractDataObjects) look inside a method body, so
 * the inline rules below are *not* picked up as documented parameters.
 */
#[Group('Newsletter')]
class NewsletterController extends Controller
{
    #[Summary('Subscribe to the newsletter')]
    #[Description('Validates the subscription payload inline (no FormRequest). Use this endpoint to see whether documentator extracts inline validation rules as body parameters.')]
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:50'],
            'consent' => ['required', 'boolean', 'accepted'],
        ]);

        return response()->json([
            'subscribed' => true,
            'email' => $validated['email'],
            'frequency' => $validated['frequency'],
        ], Response::HTTP_ACCEPTED);
    }
}
