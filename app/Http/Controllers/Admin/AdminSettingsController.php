<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'exchange_rate' => Setting::get('exchange_rate') ? (float) Setting::get('exchange_rate') : null,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('exchange_rate', $validated)) {
            Setting::set('exchange_rate', $validated['exchange_rate']);
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
