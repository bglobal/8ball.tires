<?php

namespace Tests\Feature;

use App\Services\ShopifyService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Mockery;
use Shopify\Clients\HttpResponse;

class ShopifyServiceTest extends TestCase
{

    private ShopifyService $shopifyService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Shopify configuration
        Config::set('shopify', [
            'shop' => 'test-shop.myshopify.com',
            'api_key' => 'test_api_key',
            'api_secret' => 'test_api_secret',
            'token' => 'test_token',
            'api_version' => '2024-10',
            'rate_limit' => [
                'retry_after' => 1,
                'max_retries' => 3,
            ],
        ]);

        $this->shopifyService = new ShopifyService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_fetch_locations_from_shopify()
    {
        // Mock the GraphQL client
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        $mockResponse = Mockery::mock(HttpResponse::class);
        
        $mockResponse->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'locations' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Location/123456789',
                                    'name' => 'Main Store',
                                    'address' => [
                                        'address1' => '123 Main St',
                                        'city' => 'New York',
                                        'province' => 'NY',
                                        'country' => 'United States',
                                        'zip' => '10001'
                                    ],
                                    'isActive' => true,
                                    'isPrimary' => true
                                ]
                            ],
                            [
                                'node' => [
                                    'id' => 'gid://shopify/Location/987654321',
                                    'name' => 'Warehouse',
                                    'address' => [
                                        'address1' => '456 Warehouse Ave',
                                        'city' => 'Brooklyn',
                                        'province' => 'NY',
                                        'country' => 'United States',
                                        'zip' => '11201'
                                    ],
                                    'isActive' => true,
                                    'isPrimary' => false
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->once()
            ->with([
                'query' => ShopifyService::LOCATIONS_QUERY,
                'variables' => []
            ])
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $locations = $this->shopifyService->getLocations();

        // Assertions
        $this->assertIsArray($locations);
        $this->assertCount(2, $locations);
        
        $this->assertEquals('gid://shopify/Location/123456789', $locations[0]['node']['id']);
        $this->assertEquals('Main Store', $locations[0]['node']['name']);
        $this->assertTrue($locations[0]['node']['isActive']);
        $this->assertTrue($locations[0]['node']['isPrimary']);
        
        $this->assertEquals('gid://shopify/Location/987654321', $locations[1]['node']['id']);
        $this->assertEquals('Warehouse', $locations[1]['node']['name']);
        $this->assertTrue($locations[1]['node']['isActive']);
        $this->assertFalse($locations[1]['node']['isPrimary']);
    }

    /** @test */
    public function it_can_fetch_inventory_for_variant_at_location()
    {
        $variantGid = 'gid://shopify/ProductVariant/123456789';
        $locationGid = 'gid://shopify/Location/987654321';

        // Mock the GraphQL client
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        $mockResponse = Mockery::mock(HttpResponse::class);
        
        $mockResponse->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'productVariant' => [
                        'id' => $variantGid,
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/555666777',
                            'inventoryLevel' => [
                                'id' => 'gid://shopify/InventoryLevel/888999000',
                                'available' => 15,
                                'location' => [
                                    'id' => $locationGid,
                                    'name' => 'Main Store'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->once()
            ->with([
                'query' => ShopifyService::VARIANT_INVENTORY_QUERY,
                'variables' => [
                    'variantId' => $variantGid,
                    'locationId' => $locationGid
                ]
            ])
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $availableQuantity = $this->shopifyService->getInventoryForVariantAtLocation($variantGid, $locationGid);

        // Assertions
        $this->assertEquals(15, $availableQuantity);
    }

    /** @test */
    public function it_returns_null_when_variant_not_found()
    {
        $variantGid = 'gid://shopify/ProductVariant/nonexistent';
        $locationGid = 'gid://shopify/Location/987654321';

        // Mock the GraphQL client
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        $mockResponse = Mockery::mock(HttpResponse::class);
        
        $mockResponse->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'productVariant' => null
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->once()
            ->with([
                'query' => ShopifyService::VARIANT_INVENTORY_QUERY,
                'variables' => [
                    'variantId' => $variantGid,
                    'locationId' => $locationGid
                ]
            ])
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $availableQuantity = $this->shopifyService->getInventoryForVariantAtLocation($variantGid, $locationGid);

        // Assertions
        $this->assertNull($availableQuantity);
    }

    /** @test */
    public function it_can_fetch_product_variants()
    {
        $productGid = 'gid://shopify/Product/123456789';

        // Mock the GraphQL client
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        $mockResponse = Mockery::mock(HttpResponse::class);
        
        $mockResponse->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'product' => [
                        'id' => $productGid,
                        'title' => 'Test Product',
                        'variants' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/111222333',
                                        'title' => 'Default Title',
                                        'sku' => 'TEST-001',
                                        'price' => '29.99',
                                        'inventoryQuantity' => 10,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/444555666'
                                        ]
                                    ]
                                ],
                                [
                                    'node' => [
                                        'id' => 'gid://shopify/ProductVariant/777888999',
                                        'title' => 'Large',
                                        'sku' => 'TEST-002',
                                        'price' => '39.99',
                                        'inventoryQuantity' => 5,
                                        'inventoryItem' => [
                                            'id' => 'gid://shopify/InventoryItem/000111222'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->once()
            ->with([
                'query' => ShopifyService::PRODUCT_VARIANTS_QUERY,
                'variables' => [
                    'productId' => $productGid
                ]
            ])
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $variants = $this->shopifyService->getProductVariants($productGid);

        // Assertions
        $this->assertIsArray($variants);
        $this->assertCount(2, $variants);
        
        $this->assertEquals('gid://shopify/ProductVariant/111222333', $variants[0]['node']['id']);
        $this->assertEquals('Default Title', $variants[0]['node']['title']);
        $this->assertEquals('TEST-001', $variants[0]['node']['sku']);
        $this->assertEquals('29.99', $variants[0]['node']['price']);
        
        $this->assertEquals('gid://shopify/ProductVariant/777888999', $variants[1]['node']['id']);
        $this->assertEquals('Large', $variants[1]['node']['title']);
        $this->assertEquals('TEST-002', $variants[1]['node']['sku']);
        $this->assertEquals('39.99', $variants[1]['node']['price']);
    }

    /** @test */
    public function it_can_check_service_inventory_availability()
    {
        $locationGid = 'gid://shopify/Location/987654321';
        $serviceParts = [
            [
                'shopify_variant_gid' => 'gid://shopify/ProductVariant/111222333',
                'qty_per_service' => 2
            ],
            [
                'shopify_variant_gid' => 'gid://shopify/ProductVariant/444555666',
                'qty_per_service' => 1
            ]
        ];

        // Mock the GraphQL client for multiple calls
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        
        // First variant call - sufficient inventory
        $mockResponse1 = Mockery::mock();
        $mockResponse1->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'productVariant' => [
                        'id' => 'gid://shopify/ProductVariant/111222333',
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/777888999',
                            'inventoryLevel' => [
                                'id' => 'gid://shopify/InventoryLevel/111222333',
                                'available' => 5, // More than required (2)
                                'location' => [
                                    'id' => $locationGid,
                                    'name' => 'Main Store'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        // Second variant call - sufficient inventory
        $mockResponse2 = Mockery::mock();
        $mockResponse2->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'productVariant' => [
                        'id' => 'gid://shopify/ProductVariant/444555666',
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/000111222',
                            'inventoryLevel' => [
                                'id' => 'gid://shopify/InventoryLevel/444555666',
                                'available' => 3, // More than required (1)
                                'location' => [
                                    'id' => $locationGid,
                                    'name' => 'Main Store'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->twice()
            ->andReturn($mockResponse1, $mockResponse2);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $isAvailable = $this->shopifyService->checkServiceInventoryAvailability($serviceParts, $locationGid);

        // Assertions
        $this->assertTrue($isAvailable);
    }

    /** @test */
    public function it_returns_false_when_inventory_insufficient()
    {
        $locationGid = 'gid://shopify/Location/987654321';
        $serviceParts = [
            [
                'shopify_variant_gid' => 'gid://shopify/ProductVariant/111222333',
                'qty_per_service' => 10 // More than available
            ]
        ];

        // Mock the GraphQL client
        $mockGraphqlClient = Mockery::mock('Shopify\Clients\Graphql');
        $mockResponse = Mockery::mock(HttpResponse::class);
        
        $mockResponse->shouldReceive('getDecodedBody')
            ->once()
            ->andReturn([
                'data' => [
                    'productVariant' => [
                        'id' => 'gid://shopify/ProductVariant/111222333',
                        'inventoryItem' => [
                            'id' => 'gid://shopify/InventoryItem/777888999',
                            'inventoryLevel' => [
                                'id' => 'gid://shopify/InventoryLevel/111222333',
                                'available' => 5, // Less than required (10)
                                'location' => [
                                    'id' => $locationGid,
                                    'name' => 'Main Store'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

        $mockGraphqlClient->shouldReceive('query')
            ->once()
            ->andReturn($mockResponse);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->shopifyService);
        $graphqlClientProperty = $reflection->getProperty('graphqlClient');
        $graphqlClientProperty->setAccessible(true);
        $graphqlClientProperty->setValue($this->shopifyService, $mockGraphqlClient);

        // Execute the method
        $isAvailable = $this->shopifyService->checkServiceInventoryAvailability($serviceParts, $locationGid);

        // Assertions
        $this->assertFalse($isAvailable);
    }
}
