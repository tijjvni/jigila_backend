<?php

namespace App\Http\Requests\Invoice;

use App\Enums\DeadlineExtensionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin approving or declining a deadline extension (spec 4).
 */
class ReviewDeadlineExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $max = (int) config('orders.deadline_extension.max_hours', 72);

        return [
            'extension_status' => ['required', Rule::in(DeadlineExtensionStatus::values())],
            // Lets an admin grant less (or more) than was asked for; defaults to
            // the requested hours when omitted.
            'granted_hours'    => "nullable|integer|min:1|max:{$max}",
            'reason'           => 'nullable|string|max:500',
            // Approving freezes the fee already accrued by default — the
            // extension forgives further accrual, not the days already late.
            // This clears it too, for a delay that was Jigila's fault.
            'waive_accrued_fees' => 'sometimes|boolean',
        ];
    }
}
