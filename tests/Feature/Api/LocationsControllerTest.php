<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_all_active_locations()
    {
        // Create test locations
        $activeLocation1 = Location::factory()->create([
            'name' => 'Downtown Location',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        $activeLocation2 = Location::factory()->create([
            'name' => 'Uptown Location',
            'timezone' => 'America/Los_Angeles',
            'is_active' => true,
        ]);

        $inactiveLocation = Location::factory()->create([
            'name' => 'Inactive Location',
            'timezone' => 'America/Chicago',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'timezone',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Should only return active locations
        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
        
        $locationNames = collect($responseData)->pluck('name')->toArray();
        $this->assertContains('Downtown Location', $locationNames);
        $this->assertContains('Uptown Location', $locationNames);
        $this->assertNotContains('Inactive Location', $locationNames);
    }

    /** @test */
    public function it_returns_empty_array_when_no_active_locations()
    {
        // Create only inactive locations
        Location::factory()->create(['is_active' => false]);
        Location::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/locations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => []
            ]);
    }
}