<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class AdminSettingsController extends Controller
{
    /**
     * Settings backed by `config/orders.php` defaults. Each is stored as a flat
     * key/value row; an unset row falls through to the config default, so the
     * payload always reflects what the system will actually do.
     *
     * @var array<string, string>
     */
    private const DEADLINE_SETTINGS = [
        'payment_deadline_hours'       => 'orders.payment_deadline_hours',
        'deadline_extension_max_hours' => 'orders.deadline_extension.max_hours',
        'late_fee_mode'                => 'orders.late_fee.mode',
        'late_fee_flat_amount'         => 'orders.late_fee.flat_amount',
        'late_fee_percent'             => 'orders.late_fee.percent',
        'late_fee_grace_hours'         => 'orders.late_fee.grace_hours',
        'late_fee_max_days'            => 'orders.late_fee.max_days',
        'late_fee_cap_percent'         => 'orders.late_fee.cap_percent',
    ];

    /** Keys that must come back as numbers rather than the stored strings. */
    private const NUMERIC_SETTINGS = [
        'payment_deadline_hours'       => 'int',
        'deadline_extension_max_hours' => 'int',
        'late_fee_flat_amount'         => 'float',
        'late_fee_percent'             => 'float',
        'late_fee_grace_hours'         => 'int',
        'late_fee_max_days'            => 'int',
        'late_fee_cap_percent'         => 'float',
    ];

    public function index(): JsonResponse
    {
        $rate = Setting::get('exchange_rate');

        $settings = ['exchange_rate' => $rate ? (float) $rate : null];

        foreach (self::DEADLINE_SETTINGS as $key => $configKey) {
            $value = Setting::get($key, config($configKey));

            $settings[$key] = match (self::NUMERIC_SETTINGS[$key] ?? null) {
                'int'   => (int) $value,
                'float' => (float) $value,
                default => $value,
            };
        }

        return $this->okResponse($settings);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('exchange_rate', $validated)) {
            Setting::set('exchange_rate', $validated['exchange_rate']);
        }

        foreach (array_keys(self::DEADLINE_SETTINGS) as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::set($key, $validated[$key]);
            }
        }

        return $this->messageResponse('Settings updated successfully.');
    }
}
