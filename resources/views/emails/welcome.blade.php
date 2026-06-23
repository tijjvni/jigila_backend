@extends('emails.layout')

@section('header-bg', '#1d4ed8')
@section('header-title', 'Welcome to Jigila!')
@section('header-subtitle', 'Your account is ready to go')

@section('body')
    <p>Hi <strong>{{ $user->name }}</strong>,</p>
    <p>Your account has been created successfully. You can now log in to track your vehicle imports, manage shipments, and more — all from a single dashboard.</p>
    <div class="divider"></div>
    <p>Get started by visiting your dashboard:</p>
    <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Go to Dashboard →</a>
    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        If you have any questions, open a support ticket from your dashboard and our team will be happy to help.
    </p>
@endsection
