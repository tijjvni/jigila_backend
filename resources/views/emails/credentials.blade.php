@extends('emails.layout')

@section('header-bg', '#1d4ed8')
@section('header-title', 'Your Account Credentials')
@section('header-subtitle', 'Keep these details safe')

@section('body')
    <p>Hi <strong>{{ $user->first_name ?? $user->name }}</strong>,</p>
    <p>Your Jigila account has been set up. Use the details below to log in for the first time.</p>

    <div class="divider"></div>

    <p style="font-weight: 600; margin-bottom: 6px;">Login details</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; margin-bottom: 20px;">
        <tr>
            <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Email address</span>
                <span style="font-weight: 600; color: #111827;">{{ $user->email }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 16px;">
                <span style="font-size: 12px; color: #6b7280; display: block; margin-bottom: 2px;">Temporary password</span>
                <span style="font-weight: 700; color: #1d4ed8; font-family: monospace; font-size: 16px; letter-spacing: 0.05em;">{{ $temporaryPassword }}</span>
            </td>
        </tr>
    </table>

    <a href="{{ config('app.url') }}/login" class="btn">Log In to Jigila →</a>

    <p style="margin-top: 20px; font-size: 13px; color: #6b7280;">
        For security, please change your password immediately after your first login. If you did not expect this email, contact our support team.
    </p>
@endsection
