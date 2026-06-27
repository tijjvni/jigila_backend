@extends('emails.layout')

@section('header-bg', '#1d4ed8')
@section('header-title', 'Reset Your Password')
@section('header-subtitle', 'Your one-time verification code')

@section('body')
    <p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>
    <p>We received a request to reset your Jigila account password. Use the code below to continue.</p>

    <div class="divider"></div>

    <div style="text-align: center; margin: 24px 0;">
        <p style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.07em; font-weight: 600; margin-bottom: 10px;">Your OTP Code</p>
        <div style="display: inline-block; background: #eff6ff; border: 2px solid #bfdbfe; border-radius: 12px; padding: 16px 40px;">
            <span style="font-size: 36px; font-weight: 800; letter-spacing: 0.15em; color: #1d4ed8; font-family: monospace;">{{ $otp }}</span>
        </div>
        <p style="font-size: 12px; color: #ef4444; margin-top: 10px;">Expires in 15 minutes</p>
    </div>

    <div class="divider"></div>

    <p style="font-size: 13px; color: #6b7280;">
        If you did not request a password reset, you can safely ignore this email — your password will not change.
        Never share this code with anyone.
    </p>
@endsection
