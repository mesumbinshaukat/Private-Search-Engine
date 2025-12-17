<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_endpoint_requires_query()
    {
        $response = $this->getJson('/api/v1/search');

        $response->assertStatus(422);
    }

    public function test_search_endpoint_validates_category()
    {
        $response = $this->getJson('/api/v1/search?q=test&category=invalid');

        $response->assertStatus(422);
    }

    public function test_search_endpoint_returns_no_results_when_cache_empty()
    {
        $response = $this->getJson('/api/v1/search?q=test&category=technology');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'error' => [
                    'code' => 'NO_RESULTS',
                ],
            ]);
    }

    public function test_categories_endpoint_returns_all_categories()
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'categories',
                    'total_categories',
                    'total_records',
                ],
                'meta',
            ]);
    }

    public function test_health_endpoint_returns_status()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'api',
                    'cache',
                    'database',
                ],
                'meta',
            ]);
    }

    public function test_stats_endpoint_returns_statistics()
    {
        $response = $this->getJson('/api/v1/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'index',
                    'crawler',
                    'api',
                ],
                'meta',
            ]);
    }
}
