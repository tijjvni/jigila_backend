@extends('emails.layout')

@section('header-bg', '#2563eb')
@section('header-title', 'New Reply on Your Ticket')

@section('header-subtitle')
{{ $ticket->ticket_number }}
@endsection

@section('body')
    <p>A new reply has been added to your support ticket:</p>

    <div class="ticket-box">
        <div class="label">Ticket</div>
        <div class="value">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</div>

        <div class="label">Reply from {{ $message->user->name }}</div>
        <div class="message-body">{{ $message->body }}</div>
    </div>

    <a href="{{ config('app.frontend_url') }}/support" class="btn">Reply to Ticket →</a>

    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        Log in to your dashboard to continue the conversation.
    </p>
@endsection
