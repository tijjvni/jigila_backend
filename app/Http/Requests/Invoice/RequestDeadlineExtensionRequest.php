<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Customer asking for more time to pay (spec 4).
 *
 * The upper bound is validated again in `PaymentDeadlineService` against the
 * live admin setting — this rule uses the same ceiling so the customer gets a
 * field-level error rather than a 422 from the service.
 */
class RequestDeadlineExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $max = (int) config('orders.deadline_extension.max_hours', 72);

        return [
            'requested_hours' => "required|integer|min:1|max:{$max}",
            'reason'          => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'requested_hours.required' => 'Tell us how many extra hours you need.',
            'reason.required'          => 'Please tell us why you need more time to pay.',
        ];
    }
}
