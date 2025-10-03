<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_all_active_services()
    {
        // Create a shop and location
        $location = Location::factory()->create();
        
        // Create test services
        $activeService1 = Service::factory()->create([
            'shop_id' => $location->shop_id,
            'title' => 'Oil Change',
            'slug' => 'oil-change',
            'duration_minutes' => 60,
            'price_cents' => 7500, // $75.00
            'active' => true,
        ]);

        $activeService2 = Service::factory()->create([
            'shop_id' => $location->shop_id,
            'title' => 'Brake Pad Install',
            'slug' => 'brake-pad-install',
            'duration_minutes' => 120,
            'price_cents' => 15000, // $150.00
            'active' => true,
        ]);

        $inactiveService = Service::factory()->create([
            'shop_id' => $location->shop_id,
            'title' => 'Inactive Service',
            'slug' => 'inactive-service',
            'duration_minutes' => 90,
            'price_cents' => 10000,
            'active' => false,
        ]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'duration_minutes',
                        'price',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Should only return active services
        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
        
        $serviceTitles = collect($responseData)->pluck('title')->toArray();
        $this->assertContains('Oil Change', $serviceTitles);
        $this->assertContains('Brake Pad Install', $serviceTitles);
        $this->assertNotContains('Inactive Service', $serviceTitles);

        // Check price conversion from cents to dollars
        $oilChangeService = collect($responseData)->firstWhere('title', 'Oil Change');
        $this->assertEquals(75.00, $oilChangeService['price']);
        
        $brakeService = collect($responseData)->firstWhere('title', 'Brake Pad Install');
        $this->assertEquals(150.00, $brakeService['price']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_active_services()
    {
        // Create only inactive services
        $location = Location::factory()->create();
        Service::factory()->create(['shop_id' => $location->shop_id, 'slug' => 'inactive-1', 'active' => false]);
        Service::factory()->create(['shop_id' => $location->shop_id, 'slug' => 'inactive-2', 'active' => false]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => []
            ]);
    }
}