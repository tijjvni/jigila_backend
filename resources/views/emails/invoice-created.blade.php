@extends('emails.layout')

@section('header-bg', '#1d4ed8')
@section('header-title', 'New Invoice Generated')
@section('header-subtitle', '{{ $invoice->invoice_number }}')

@section('body')
    <p>Hi <strong>{{ $invoice->user->first_name ?? $invoice->user->name }}</strong>,</p>
    <p>A new invoice has been generated on your account. Please review the details below and complete payment at your earliest convenience.</p>

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
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Amount due</span>
                <span style="font-weight: 700; color: #111827; font-size: 18px;">${{ number_format($invoice->amount, 2) }}</span>
            </td>
        </tr>
        @if ($invoice->description)
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Description</span>
                <span style="color: #374151;">{{ $invoice->description }}</span>
            </td>
        </tr>
        @endif
        @if ($invoice->order)
        <tr>
            <td style="padding: 12px 16px; @if (!$invoice->due_date) @else border-bottom: 1px solid #e5e7eb; @endif">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Order reference</span>
                <span style="font-weight: 600; color: #111827;">#{{ str_pad($invoice->order->id, 5, '0', STR_PAD_LEFT) }}</span>
            </td>
        </tr>
        @endif
        @if ($invoice->due_date)
        <tr>
            <td style="padding: 12px 16px;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Due date</span>
                <span style="font-weight: 600; color: #dc2626;">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</span>
            </td>
        </tr>
        @endif
    </table>

    @if ($invoice->payment_url)
        <a href="{{ $invoice->payment_url }}" class="btn">Pay Invoice →</a>
    @else
        <a href="{{ config('app.url') }}/invoices" class="btn">View Invoice →</a>
    @endif

    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        You can also log in to your Jigila dashboard to view and pay this invoice at any time.
        If you have any questions, please open a support ticket from your dashboard.
    </p>
@endsection
