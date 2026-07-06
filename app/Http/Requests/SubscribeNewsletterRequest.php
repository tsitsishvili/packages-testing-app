<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:50'],
            'consent' => ['required', 'boolean', 'accepted'],
        ];
    }
}
