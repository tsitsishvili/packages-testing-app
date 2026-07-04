<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dispatches an order. Written to cover the rule-parser branches the rest of the
 * demo doesn't reach:
 *  - `regex`         -> JSON Schema `pattern`
 *  - `digits_between`-> integer
 *  - `decimal`       -> number
 *  - `integer`+`between` -> integer with minimum/maximum
 *  - `ip`            -> `ipv4` format
 *  - `image`         -> binary (which forces the body to multipart/form-data)
 */
class ShipOrderRequest extends FormRequest
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
            'tracking_number' => ['required', 'string', 'regex:/^[A-Z]{2}\d{9}[A-Z]{2}$/'],
            'carrier' => ['required', Rule::in(['fedex', 'ups', 'dhl'])],
            'weight_grams' => ['required', 'digits_between:1,6'],
            'declared_value' => ['required', 'decimal:2'],
            'parcel_count' => ['required', 'integer', 'between:1,99'],
            'origin_ip' => ['required', 'ip'],
            'label' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
