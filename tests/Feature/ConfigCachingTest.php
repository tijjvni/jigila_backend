<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /config is ~25 KB, identical for every visitor, and fetched on nearly every
 * page load. These pin the ETag revalidation that keeps it off the wire.
 */
class ConfigCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_response_carries_an_etag_and_cache_headers(): void
    {
        $response = $this->getJson('/api/v1/config')->assertStatus(200);

        $this->assertNotNull($response->headers->get('ETag'), 'No ETag on /config.');
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function test_matching_etag_returns_304_with_no_body(): void
    {
        $etag = $this->getJson('/api/v1/config')->headers->get('ETag');

        $response = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/config')
            ->assertStatus(304);

        $this->assertSame('', $response->getContent(), '304 must not carry a body.');
    }

    /** A changed exchange rate must produce a new ETag, not a stale 304. */
    public function test_etag_changes_when_the_payload_changes(): void
    {
        Setting::set('exchange_rate', '1500');
        $first = $this->getJson('/api/v1/config')->headers->get('ETag');

        Setting::set('exchange_rate', '1600');
        $second = $this->getJson('/api/v1/config')->headers->get('ETag');

        $this->assertNotSame($first, $second, 'ETag did not change after the exchange rate did.');

        // okResponse() returns a plain array unwrapped, so the config sits at
        // the top level — this is the shape the frontend's getConfig expects.
        $this->withHeaders(['If-None-Match' => $first])
            ->getJson('/api/v1/config')
            ->assertStatus(200)
            // json_encode drops the zero fraction, so a whole rate decodes as int.
            ->assertJsonPath('exchange_rate', 1600);
    }

    public function test_stats_endpoint_is_also_revalidated(): void
    {
        $response = $this->getJson('/api/v1/config/stats')->assertStatus(200);

        $this->assertNotNull($response->headers->get('ETag'));
    }
}
