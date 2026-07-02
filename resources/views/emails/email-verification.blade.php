@extends('emails.layout')

@section('header-bg', '#1d4ed8')
@section('header-title', 'Verify Your Email Address')
@section('header-subtitle', 'One quick step to activate your account')

@section('body')
    <p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>
    <p>Thanks for signing up with Jigila! Please verify your email address by clicking the button below.</p>

    <div class="divider"></div>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address →</a>
    </div>

    <div class="divider"></div>

    <p style="font-size: 13px; color: #6b7280;">
        This link expires in <strong>60 minutes</strong>. If you did not create a Jigila account, you can safely ignore this email.
    </p>
    <p style="font-size: 12px; color: #9ca3af; margin-top: 12px; word-break: break-all;">
        If the button doesn't work, copy and paste this URL into your browser:<br>
        <a href="{{ $verificationUrl }}" style="color: #1d4ed8;">{{ $verificationUrl }}</a>
    </p>
@endsection