<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url');

        $user = \App\Models\User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect("{$frontendUrl}/login?verified=0&error=invalid_link");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect("{$frontendUrl}/login?verified=1");
        }

        $user->markEmailAsVerified();

        return redirect("{$frontendUrl}/login?verified=1");
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }
}
