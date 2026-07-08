<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url');

        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
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
            return $this->errorResponse('Email already verified.', 422);
        }

        $user->sendEmailVerificationNotification();

        return $this->messageResponse('Verification email sent.');
    }
}
