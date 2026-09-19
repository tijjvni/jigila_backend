@extends('emails.layout')

@section('header-bg', $isCritical ? '#b91c1c' : '#c2410c')
@section('btn-bg', $isCritical ? '#b91c1c' : '#1d4ed8')
@section('header-title', $isOverdue ? 'Payment Overdue' : ($stage === 'expiry' ? 'Payment Deadline Reached' : 'Payment Reminder'))
@section('header-subtitle'){{ $invoice->invoice_number }}@endsection

@section('body')
    <p>Hi <strong>{{ $invoice->user->first_name ?? $invoice->user->name }}</strong>,</p>

    @if ($isOverdue)
        <p>
            The payment deadline for the invoice below has passed, and we have placed a
            <strong>hold on your shipment</strong> until it is settled. Paying now lifts the hold
            automatically.
        </p>
    @elseif ($stage === 'expiry')
        <p>
            The payment deadline for the invoice below has been reached. Please pay now — once the
            deadline lapses a hold is placed on your shipment and late fees begin to accrue.
        </p>
    @else
        <p>
            This is a reminder that the invoice below is due in <strong>{{ $stage }} hours</strong>.
            Paying before the deadline keeps your shipment on schedule.
        </p>
    @endif

    <div class="divider"></div>

    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; margin-bottom: 20px;">
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Invoice number</span>
                <span style="font-weight: 600; color: #111827;">{{ $invoice->invoice_number }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Invoice amount</span>
                <span style="font-weight: 700; color: #111827; font-size: 18px;">${{ number_format($invoice->amount, 2) }}</span>
            </td>
        </tr>
        @if ((float) $invoice->late_fee_amount > 0)
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">
                    Late fee ({{ $invoice->late_fee_days }} {{ \Illuminate\Support\Str::plural('day', $invoice->late_fee_days) }} overdue)
                </span>
                <span style="font-weight: 700; color: #b91c1c;">+ ${{ number_format($invoice->late_fee_amount, 2) }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Total now owing</span>
                <span style="font-weight: 700; color: #111827; font-size: 18px;">${{ number_format($invoice->totalDue(), 2) }}</span>
            </td>
        </tr>
        @endif
        @if ($invoice->order)
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Order reference</span>
                <span style="font-weight: 600; color: #111827;">#{{ str_pad($invoice->order->id, 5, '0', STR_PAD_LEFT) }} — VIN {{ $invoice->order->vin }}</span>
            </td>
        </tr>
        @endif
        @if ($invoice->payment_due_at)
        <tr>
            <td style="padding: 12px 16px;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Payment deadline</span>
                <span style="font-weight: 600; color: #dc2626;">{{ $invoice->payment_due_at->format('M d, Y \a\t H:i') }} UTC</span>
            </td>
        </tr>
        @endif
    </table>

    @if ($invoice->payment_url)
        <a href="{{ $invoice->payment_url }}" class="btn">Pay Invoice →</a>
    @else
        <a href="{{ config('app.frontend_url') }}/invoices" class="btn">View Invoice →</a>
    @endif

    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        Need more time? You can request a one-time deadline extension from the invoice page in your
        Jigila dashboard. Extensions are reviewed by our team.
    </p>
@endsection
