<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_endpoint_returns_all_sections(): void
    {
        $this->getJson('/api/config')
            ->assertStatus(200)
            ->assertJsonStructure([
                'services'           => [['value', 'label']],
                'order_statuses'     => [['value', 'label']],
                'auction_sources'    => [['value', 'label']],
                'vehicle_conditions' => [['value', 'label']],
                'user_roles'         => [['value', 'label']],
                'permission_keys'    => [['value', 'label']],
            ]);
    }

    public function test_config_contains_expected_auction_sources(): void
    {
        $response = $this->getJson('/api/config')->assertStatus(200);

        $sources = collect($response->json('auction_sources'))->pluck('value')->all();
        $this->assertEqualsCanonicalizing(['Copart', 'IAAI', 'Co-parts'], $sources);
    }

    public function test_config_contains_expected_order_statuses(): void
    {
        $response = $this->getJson('/api/config')->assertStatus(200);

        $statuses = collect($response->json('order_statuses'))->pluck('value')->all();
        $this->assertEqualsCanonicalizing(
            ['pending', 'processing', 'in_transit', 'at_port', 'delivered', 'cancelled'],
            $statuses
        );
    }

    public function test_config_contains_expected_services(): void
    {
        $response = $this->getJson('/api/config')->assertStatus(200);

        $services = collect($response->json('services'))->pluck('value')->all();
        $this->assertEqualsCanonicalizing(['trucking', 'shipping'], $services);
    }

    public function test_config_is_publicly_accessible(): void
    {
        // No auth header — should still return 200
        $this->getJson('/api/config')->assertStatus(200);
    }
}
