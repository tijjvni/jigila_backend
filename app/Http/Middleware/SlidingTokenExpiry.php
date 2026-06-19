<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class SlidingTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->expires_at) {
            $hoursLeft = now()->diffInHours($token->expires_at, false);
            if ($hoursLeft >= 0 && $hoursLeft < 2) {
                $token->forceFill(['expires_at' => now()->addHours(8)])->save();
            }
        }

        return $response;
    }
}
