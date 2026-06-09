@extends('emails.layout')

@section('header-bg', '#1e40af')
@section('header-title', 'Support Ticket Created')
@section('header-subtitle', 'We\'ve received your request')

@section('body')
    <p>Hi <strong>{{ $ticket->user->name }}</strong>,</p>
    <p>Your support ticket has been created. Our team will review it and get back to you as soon as possible.</p>

    <div class="ticket-box">
        <div class="label">Ticket Number</div>
        <div class="value">{{ $ticket->ticket_number }}</div>

        <div class="label">Subject</div>
        <div class="value">{{ $ticket->subject }}</div>

        <div class="label">Status</div>
        <div class="value" style="text-transform: capitalize; color: #2563eb;">
            {{ str_replace('_', ' ', $ticket->status) }}
        </div>
    </div>

    <a href="{{ config('app.url') }}/support" class="btn">View Ticket →</a>

    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        You'll receive an email notification when a team member replies.
    </p>
@endsection
