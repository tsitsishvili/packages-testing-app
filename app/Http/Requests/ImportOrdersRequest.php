<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A deliberately rich request that pushes the rule parser hard: a binary `file`
 * (which forces the documented body to multipart), `Rule::in` enums, `uuid`,
 * `email`, `url`, `date` and boolean scalars, plus a wildcard string array
 * (`tags.*`) and a nested object array (`mappings.*.column`).
 */
class ImportOrdersRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'source' => ['required', Rule::in(['shopify', 'woocommerce', 'manual'])],
            'reference' => ['nullable', 'uuid'],
            'notify_email' => ['nullable', 'email'],
            'callback_url' => ['nullable', 'url'],
            'effective_date' => ['required', 'date'],
            'dry_run' => ['boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:30'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.column' => ['required_with:mappings', 'string'],
            'mappings.*.field' => ['required_with:mappings', Rule::in(['name', 'price', 'sku'])],
        ];
    }
}
