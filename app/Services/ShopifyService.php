<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ShopifyException extends \Exception
{
}

class ShopifyService
{
    private array $config;
    private array $circuitBreaker = [];

    public function __construct()
    {
        $this->config = Config::get('shopify');
    }


    /**
     * GraphQL query constants
     */
    public const LOCATIONS_QUERY = '
        query getLocations {
            locations(first: 50) {
                edges {
                    node {
                        id
                        name
                        address {
                            address1
                            address2
                            city
                            province
                            country
                            zip
                        }
                        isActive
                        isPrimary
                    }
                }
            }
        }
    ';

    public const VARIANT_INVENTORY_QUERY = '
        query getVariantInventory($variantId: ID!, $locationId: ID!) {
            productVariant(id: $variantId) {
                id
                inventoryItem {
                    id
                    inventoryLevel(locationId: $locationId) {
                        id
                        available
                        location {
                            id
                            name
                        }
                    }
                }
            }
        }
    ';

    // Step 1: Get inventoryItem ID from a product variant
    public const VARIANT_TO_INVENTORY_ITEM_QUERY = '
       query getInventoryItemFromVariant($variantId: ID!) {
            productVariant(id: $variantId) {
                id
                inventoryItem {
                    id
                }
            }
        }
    ';

    // Step 2: Get available inventor level from inventory item
    public const INVENTORY_LEVELS_FROM_ITEM_QUERY = '
    query getInventoryLevels($inventoryItemId: ID!) {
        inventoryItem(id: $inventoryItemId) {
            id
            inventoryLevels(first: 50) {
                edges {
                    node {
                        quantities(names: ["available"]) {
                            name
                            quantity
                        }
                        location {
                            id
                            name
                        }
                        updatedAt
                    }
                }
            }
        }
    }
';


    public const PRODUCT_VARIANTS_QUERY = '
        query getProductVariants($productId: ID!) {
            product(id: $productId) {
                id
                title
                variants(first: 50) {
                    edges {
                        node {
                            id
                            title
                            sku
                            price
                            inventoryQuantity
                            inventoryItem {
                                id
                            }
                        }
                    }
                }
            }
        }
    ';

    public const CREATE_DRAFT_ORDER_QUERY = '
        mutation draftOrderCreate($input: DraftOrderInput!) {
            draftOrderCreate(input: $input) {
                draftOrder {
                    id
                    invoiceUrl
                    totalPrice
                    subtotalPrice
                    totalTax
                    currencyCode
                }
                userErrors {
                    field
                    message
                }
            }
        }
    ';

    public const SEND_DRAFT_ORDER_INVOICE_QUERY = '
        mutation draftOrderInvoiceSend($id: ID!) {
            draftOrderInvoiceSend(id: $id) {
                draftOrder {
                    id
                    invoiceUrl
                }
                userErrors {
                    field
                    message
                }
            }
        }
    ';

    public const PRODUCTS_BY_TAG_QUERY = '
        query getProductsByTag($tag: String!, $first: Int!) {
            products(first: $first, query: $tag) {
                edges {
                    node {
                        id
                        title
                        handle
                        productType
                        tags
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    title
                                    price
                                }
                            }
                        }
                    }
                }
            }
        }
    ';

    public const ALL_PRODUCTS_WITH_VARIANTS_QUERY = '
        query getAllProductsWithVariants($first: Int!) {
            products(first: $first) {
                edges {
                    node {
                        id
                        title
                        handle
                        productType
                        tags
                        variants(first: 50) {
                            edges {
                                node {
                                    id
                                    title
                                    price
                                    sku
                                }
                            }
                        }
                    }
                }
            }
        }
    ';

    public const ORDERS_QUERY = '
        query getOrders($first: Int!, $query: String) {
            orders(first: $first, query: $query) {
                edges {
                    node {
                        id
                        name
                        createdAt
                        totalPriceSet {
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        lineItems(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    quantity
                                    variant {
                                        id
                                        title
                                        price
                                        compareAtPrice
                                        product {
                                            id
                                            title
                                            handle
                                            vendor
                                            tags
                                            featuredImage {
                                                id
                                                url
                                                altText
                                            }
                                        }
                                    }
                                    originalUnitPriceSet {
                                        shopMoney {
                                            amount
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
    ';

    /**
     * Execute GraphQL query with retry logic using direct cURL
     */
    public function executeGraphQLQuery(string $query, array $variables = []): array
    {
        $maxRetries = $this->config['rate_limit']['max_retries'];
        $retryAfter = $this->config['rate_limit']['retry_after'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $url = "https://{$this->config['shop']}/admin/api/{$this->config['api_version']}/graphql.json";

                $data = [
                    'query' => $query
                ];

                if (!empty($variables)) {
                    $data['variables'] = $variables;
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Shopify-Access-Token: ' . $this->config['token']
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false || !empty($curlError)) {
                    throw new ShopifyException('cURL error occurred: ' . $curlError);
                }

                $decodedResponse = json_decode($response, true);

                if ($httpCode === 429 && $attempt < $maxRetries) {
                    // Rate limited - wait and retry with exponential backoff
                    $waitTime = $retryAfter * pow(2, $attempt - 1);
                    Log::warning("Shopify API rate limited. Retrying in {$waitTime} seconds...", [
                        'attempt' => $attempt,
                        'wait_time' => $waitTime,
                        'response' => $decodedResponse
                    ]);

                    sleep($waitTime);
                    continue;
                }

                if ($httpCode !== 200) {
                    Log::error('Shopify API HTTP error', [
                        'http_code' => $httpCode,
                        'response' => $response,
                        'query' => $query
                    ]);
                    throw new ShopifyException('HTTP error: ' . $httpCode . ' - ' . $response);
                }

                // Check for GraphQL errors
                if (isset($decodedResponse['errors'])) {
                    Log::error('Shopify GraphQL errors', [
                        'errors' => $decodedResponse['errors'],
                        'query' => $query
                    ]);
                    throw new ShopifyException('GraphQL errors: ' . json_encode($decodedResponse['errors']));
                }

                return $decodedResponse;

            } catch (ShopifyException $e) {
                Log::error('Shopify service error', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'query' => $query
                ]);

                if ($attempt === $maxRetries) {
                    throw $e;
                }

                // Wait before retry
                sleep($retryAfter);
            }
        }

        throw new ShopifyException('Max retries exceeded for GraphQL query');
    }

    /**
     * Execute REST API call with retry logic using direct cURL
     */
    protected function executeRestCall(string $method, string $path, array $data = []): array
    {
        $maxRetries = $this->config['rate_limit']['max_retries'];
        $retryAfter = $this->config['rate_limit']['retry_after'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $url = "https://{$this->config['shop']}/admin/api/{$this->config['api_version']}/{$path}";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Shopify-Access-Token: ' . $this->config['token']
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

                if (strtoupper($method) === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($response === false || !empty($curlError)) {
                    throw new ShopifyException('cURL error occurred: ' . $curlError);
                }

                $decodedResponse = json_decode($response, true);

                if ($httpCode === 429 && $attempt < $maxRetries) {
                    // Rate limited - wait and retry
                    $waitTime = $retryAfter * $attempt;
                    Log::warning("Shopify API rate limited. Retrying in {$waitTime} seconds...", [
                        'attempt' => $attempt,
                        'wait_time' => $waitTime,
                        'response' => $decodedResponse
                    ]);

                    sleep($waitTime);
                    continue;
                }

                if ($httpCode !== 200) {
                    throw new ShopifyException('HTTP error: ' . $httpCode . ' - ' . $response);
                }

                return $decodedResponse;

            } catch (ShopifyException $e) {
                Log::error('Shopify service error', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'method' => $method,
                    'path' => $path,
                    'url' => $url ?? 'unknown'
                ]);

                if ($attempt === $maxRetries) {
                    throw $e;
                }
            }
        }

        throw new ShopifyException('Max retries exceeded for REST API call');
    }

    /**
     * Get all locations from Shopify
     */
    public function getLocations(): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::LOCATIONS_QUERY);

            return $response['data']['locations']['edges'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch locations from Shopify', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get inventory for a specific variant at a specific location
     */
    public function getInventoryForVariantAtLocation(string $variantGid, string $locationGid): ?int
    {
        try {
            // Step 1: Get inventoryItemId from variant
            $variantResponse = $this->executeGraphQLQuery(self::VARIANT_TO_INVENTORY_ITEM_QUERY, [
                'variantId' => $variantGid
            ]);

            $inventoryItem = $variantResponse['data']['productVariant']['inventoryItem'] ?? null;

            if (!$inventoryItem || !isset($inventoryItem['id'])) {
                Log::warning('Missing inventory item ID for variant', ['variantGid' => $variantGid]);
                return null;
            }

            // Step 2: Query all inventory levels for this inventory item
            $levelsResponse = $this->executeGraphQLQuery(self::INVENTORY_LEVELS_FROM_ITEM_QUERY, [
                'inventoryItemId' => $inventoryItem['id']
            ]);


            $edges = $levelsResponse['data']['inventoryItem']['inventoryLevels']['edges'] ?? [];

            foreach ($edges as $edge) {
                $node = $edge['node'] ?? null;
                if (!$node || !isset($node['location']['id'])) {
                    continue;
                }
                if ($node['location']['id'] === $locationGid) {
                    // Find the “available” quantity
                    if (!empty($node['quantities'])) {
                        foreach ($node['quantities'] as $qtyObj) {
                            if ($qtyObj['name'] === 'available') {
                                return (int)$qtyObj['quantity'];
                            }
                        }
                    }
                    // If no “available” record, fallback to 0
                    return 0;
                }
            }


            return 0; // Not available at the given location
        } catch (\Exception $e) {
            Log::error('Failed to fetch inventory for variant at location', [
                'variantGid' => $variantGid,
                'locationGid' => $locationGid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    /**
     * Get product variants for a specific product
     */
    public function getProductVariants(string $productGid): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::PRODUCT_VARIANTS_QUERY, [
                'productId' => $productGid
            ]);

            $product = $response['data']['product'] ?? null;

            if (!$product) {
                return [];
            }

            return $product['variants']['edges'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch product variants from Shopify', [
                'product_gid' => $productGid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a webhook
     */
    public function createWebhook(string $topic, string $callbackUrl): array
    {
        try {
            $webhookData = [
                'webhook' => [
                    'topic' => $topic,
                    'address' => $callbackUrl,
                    'format' => 'json'
                ]
            ];

            $response = $this->executeRestCall('post', 'webhooks.json', $webhookData);

            return $response['webhook'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to create webhook in Shopify', [
                'topic' => $topic,
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if inventory is available for a service at a location
     */
    public function checkServiceInventoryAvailability(array $serviceParts, string $locationGid): bool
    {
        foreach ($serviceParts as $part) {
            $variantGid = $part['shopify_variant_gid'];
            $requiredQty = $part['qty_per_service'];

            $availableQty = $this->getInventoryForVariantAtLocation($variantGid, $locationGid);

            if ($availableQty === null || $availableQty < $requiredQty) {
                Log::info('Insufficient inventory for service part', [
                    'variant_gid' => $variantGid,
                    'required_qty' => $requiredQty,
                    'available_qty' => $availableQty,
                    'location_gid' => $locationGid
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Get shop information
     */
    public function getShopInfo(): array
    {
        try {
            $response = $this->executeRestCall('get', 'shop.json');
            return $response['shop'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch shop info from Shopify', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a draft order in Shopify
     */
    public function createDraftOrder(array $draftOrderData): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::CREATE_DRAFT_ORDER_QUERY, [
                'input' => $draftOrderData
            ]);
            Log::info("draftOrder" . json_encode($response['data']));
            $data = $response['data']['draftOrderCreate'] ?? [];

            if (isset($data['draftOrder']) && empty($data['userErrors'])) {
                return [
                    'success' => true,
                    'draftOrder' => $data['draftOrder']
                ];
            }

            return [
                'success' => false,
                'errors' => $data['userErrors'] ?? ['Unknown error creating draft order']
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create draft order in Shopify', [
                'draftOrderData' => $draftOrderData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Send invoice for a draft order
     */
    public function sendDraftOrderInvoice(string $draftOrderId): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::SEND_DRAFT_ORDER_INVOICE_QUERY, [
                'id' => $draftOrderId
            ]);

            $data = $response['data']['draftOrderInvoiceSend'] ?? [];

            if (isset($data['draftOrder']) && empty($data['userErrors'])) {
                return [
                    'success' => true,
                    'draftOrder' => $data['draftOrder']
                ];
            }

            return [
                'success' => false,
                'errors' => $data['userErrors'] ?? ['Unknown error sending invoice']
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send draft order invoice in Shopify', [
                'draftOrderId' => $draftOrderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Get products by tag from Shopify
     */
    public function getProductsByTag(string $tag, int $limit = 50): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::PRODUCTS_BY_TAG_QUERY, [
                'tag' => "tag:{$tag}",
                'first' => $limit
            ]);

            return $response['data']['products']['edges'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch products by tag from Shopify', [
                'tag' => $tag,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get all products with their variants from Shopify
     */
    public function getAllProductsWithVariants(int $limit = 100): array
    {
        try {
            $response = $this->executeGraphQLQuery(self::ALL_PRODUCTS_WITH_VARIANTS_QUERY, [
                'first' => $limit
            ]);

            return $response['data']['products']['edges'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch all products with variants from Shopify', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get products from Shopify (alias for getAllProductsWithVariants)
     */
    public function getProducts(int $limit = 100): array
    {
        return $this->getAllProductsWithVariants($limit);
    }


    /**
     * Get frequently bought together products for a specific product
     *
     * Logic:
     * 1. Find all orders containing the given product
     * 2. Collect all other products from those same orders
     * 3. Count how many times each product appears together
     * 4. Return products sorted by frequency (excluding services tag)
     *
     * @param string $productId Product ID (can be numeric ID or GraphQL GID)
     * @param int $limit Maximum number of recommendations (default: 5)
     * @return array Array of complementary products sorted by frequency
     */
    public function getFrequentlyBoughtTogether(string $productId, int $limit = 5): array
    {
        try {
            // Use credentials from .env
            $shopDomain = $this->config['shop'];
            $accessToken = $this->config['token'];
            $version = $this->config['api_version'];

            if (!$shopDomain || !$accessToken) {
                throw new ShopifyException('Shopify credentials not found in .env file.');
            }

            // Normalize product ID (handle both numeric and GraphQL GID)
            $targetProductId = $productId;
            if (strpos($productId, 'gid://') === 0) {
                $targetProductId = basename($productId);
            }

            // Fetch all orders without time restriction
            $response = $this->executeGraphQLQuery(self::ORDERS_QUERY, [
                'first' => 250,
                'query' => null
            ]);

            $orders = $response['data']['orders']['edges'] ?? [];

            // Step 1: Find orders containing the target product
            $ordersWithTargetProduct = [];

            foreach ($orders as $orderEdge) {
                $order = $orderEdge['node'] ?? null;
                if (!$order) continue;

                $lineItems = $order['lineItems']['edges'] ?? [];
                $hasTargetProduct = false;

                // Check if this order contains the target product
                foreach ($lineItems as $itemEdge) {
                    $item = $itemEdge['node'] ?? null;
                    if (!$item) continue;

                    $productGid = $item['variant']['product']['id'] ?? null;
                    if (!$productGid) continue;

                    $itemProductId = basename($productGid);

                    if ($itemProductId === $targetProductId) {
                        $hasTargetProduct = true;
                        break;
                    }
                }

                if ($hasTargetProduct) {
                    $ordersWithTargetProduct[] = $order;
                }
            }

            // Step 2: Collect all other products from those orders (complementary products)
            // Logic: For each order containing target product, find which other products
            // were purchased in the SAME order, and count how many orders had that combination
            $complementaryProducts = [];

            foreach ($ordersWithTargetProduct as $order) {
                $lineItems = $order['lineItems']['edges'] ?? [];

                // Track unique products in this order (to count once per order)
                $productsInThisOrder = [];

                foreach ($lineItems as $itemEdge) {
                    $item = $itemEdge['node'] ?? null;
                    if (!$item) continue;

                    // Get product tags
                    $productTags = $item['variant']['product']['tags'] ?? [];

                    // Skip products with "services" tag
                    $hasServicesTag = false;
                    foreach ($productTags as $tag) {
                        if (strtolower($tag) === 'services') {
                            $hasServicesTag = true;
                            break;
                        }
                    }
                    if ($hasServicesTag) continue;

                    // Extract product ID
                    $productGid = $item['variant']['product']['id'] ?? null;
                    if (!$productGid) continue;

                    $itemProductId = basename($productGid);

                    // Skip the target product itself
                    if ($itemProductId === $targetProductId) continue;

                    // Track this product in current order
                    if (!isset($productsInThisOrder[$itemProductId])) {
                        $productHandle = $item['variant']['product']['handle'] ?? '';
                        $featuredImage = $item['variant']['product']['featuredImage'] ?? null;
                        $variantPrice = $item['variant']['price'] ?? '0.00';
                        $compareAtPrice = $item['variant']['compareAtPrice'] ?? null;

                        // Build product URL
                        $productUrl = $productHandle ? "https://{$shopDomain}/products/{$productHandle}" : null;

                        $productsInThisOrder[$itemProductId] = [
                            'product_id' => $itemProductId,
                            'product_gid' => $productGid,
                            'title' => $item['variant']['product']['title'] ?? 'Unknown Product',
                            'handle' => $productHandle,
                            'url' => $productUrl,
                            'price' => $variantPrice,
                            'compare_at_price' => $compareAtPrice,
                            'vendor' => $item['variant']['product']['vendor'] ?? '',
                            'variant_id' => basename($item['variant']['id'] ?? ''),
                            'variant_gid' => $item['variant']['id'] ?? null,
                            'variant_title' => $item['variant']['title'] ?? null,
                            'featured_image' => $featuredImage ? [
                                'id' => $featuredImage['id'] ?? null,
                                'url' => $featuredImage['url'] ?? null,
                                'alt_text' => $featuredImage['altText'] ?? null,
                            ] : null,
                            'tags' => $productTags,
                            'quantity' => 0,
                            'revenue' => 0.0,
                        ];
                    }

                    // Accumulate quantity and revenue for this product in this order
                    $quantity = $item['quantity'] ?? 0;
                    $price = floatval($item['originalUnitPriceSet']['shopMoney']['amount'] ?? 0);

                    $productsInThisOrder[$itemProductId]['quantity'] += $quantity;
                    $productsInThisOrder[$itemProductId]['revenue'] += $price * $quantity;
                }

                // Now process each unique product found in this order
                foreach ($productsInThisOrder as $itemProductId => $productData) {
                    // Initialize complementary product if not exists
                    if (!isset($complementaryProducts[$itemProductId])) {
                        $complementaryProducts[$itemProductId] = [
                            'product_id' => $productData['product_id'],
                            'product_gid' => $productData['product_gid'],
                            'title' => $productData['title'],
                            'handle' => $productData['handle'] ?? '',
                            'url' => $productData['url'] ?? null,
                            'price' => $productData['price'] ?? '0.00',
                            'compare_at_price' => $productData['compare_at_price'] ?? null,
                            'vendor' => $productData['vendor'] ?? '',
                            'variant_id' => $productData['variant_id'],
                            'variant_gid' => $productData['variant_gid'],
                            'variant_title' => $productData['variant_title'],
                            'featured_image' => $productData['featured_image'] ?? null,
                            'times_bought_together' => 0, // Count of orders where both appeared
                            'total_quantity' => 0,
                            'total_revenue' => 0.0,
                            'tags' => $productData['tags'],
                        ];
                    }

                    // Count this order (once per order, not per line item)
                    $complementaryProducts[$itemProductId]['times_bought_together']++;

                    // Add quantities and revenue from this order
                    $complementaryProducts[$itemProductId]['total_quantity'] += $productData['quantity'];
                    $complementaryProducts[$itemProductId]['total_revenue'] += $productData['revenue'];
                }
            }

            // Step 3: Sort by frequency (times_bought_together)
            uasort($complementaryProducts, function($a, $b) {
                // Primary sort: times bought together (descending)
                if ($b['times_bought_together'] !== $a['times_bought_together']) {
                    return $b['times_bought_together'] <=> $a['times_bought_together'];
                }
                // Secondary sort: total quantity (descending)
                return $b['total_quantity'] <=> $a['total_quantity'];
            });

            // Step 4: Limit results and return
            $result = array_values($complementaryProducts);
            return array_slice($result, 0, $limit);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch frequently bought together products', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

}
