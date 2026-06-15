<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_get_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure(['exchange_rate']);
    }

    public function test_exchange_rate_is_null_when_not_set(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('exchange_rate', null);
    }

    public function test_exchange_rate_is_returned_as_float_when_set(): void
    {
        Setting::set('exchange_rate', '1650.50');
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('exchange_rate', 1650.50);
    }

    public function test_guest_cannot_get_settings(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
    }

    public function test_regular_user_cannot_get_settings(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->getJson('/api/admin/settings')
            ->assertForbidden();
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_exchange_rate(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings', ['exchange_rate' => 1500.00])
            ->assertOk()
            ->assertJsonPath('message', 'Settings updated successfully.');

        $this->assertSame('1500', Setting::where('key', 'exchange_rate')->value('value'));
    }

    public function test_update_persists_exchange_rate_and_is_readable(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->putJson('/api/admin/settings', ['exchange_rate' => 1750.50]);

        $this->actingAs($admin)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('exchange_rate', 1750.50);
    }

    public function test_admin_can_clear_exchange_rate(): void
    {
        Setting::set('exchange_rate', '1600');
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings', ['exchange_rate' => null])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('exchange_rate', null);
    }

    public function test_update_rejects_non_numeric_exchange_rate(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings', ['exchange_rate' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exchange_rate']);
    }

    public function test_update_rejects_negative_exchange_rate(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings', ['exchange_rate' => -100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exchange_rate']);
    }

    public function test_guest_cannot_update_settings(): void
    {
        $this->putJson('/api/admin/settings', ['exchange_rate' => 1500])->assertUnauthorized();
    }

    public function test_regular_user_cannot_update_settings(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->putJson('/api/admin/settings', ['exchange_rate' => 1500])
            ->assertForbidden();
    }

    // ── config endpoint reflects setting ─────────────────────────────────────

    public function test_config_endpoint_reflects_exchange_rate(): void
    {
        Setting::set('exchange_rate', '1200.75');

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('exchange_rate', 1200.75);
    }

    public function test_config_exchange_rate_is_null_when_not_set(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('exchange_rate', null);
    }
}
