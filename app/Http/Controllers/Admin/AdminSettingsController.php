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
        $rate = Setting::get('exchange_rate');

        return $this->okResponse([
            'exchange_rate' => $rate ? (float) $rate : null,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('exchange_rate', $validated)) {
            Setting::set('exchange_rate', $validated['exchange_rate']);
        }

        return $this->messageResponse('Settings updated successfully.');
    }
}
