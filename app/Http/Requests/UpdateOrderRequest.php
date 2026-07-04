<?php

namespace App\Http\Requests;

use App\Enums\FulfillmentPriority;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update rules written to exercise documentator's FormRequest inference:
 * `Rule::enum` (string enum), `Rule::in` over the int-backed priority, a
 * `date` field, and bounded strings all become documented parameters with the
 * right OpenAPI types.
 */
class UpdateOrderRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'priority' => ['sometimes', Rule::in(array_column(FulfillmentPriority::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
