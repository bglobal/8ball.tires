# Shopify Integration Setup

This document explains how to set up the Shopify integration for the 8Ball Tires booking system.

## Required Environment Variables

Add the following environment variables to your `.env` file:

```env
# Shopify Integration
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret
SHOPIFY_ADMIN_TOKEN=your_admin_token
SHOPIFY_API_VERSION=2024-10
SHOPIFY_VERIFY_WEBHOOK_HMAC=true
SHOPIFY_WEBHOOK_SECRET=your_webhook_secret
```

## Setting up a Shopify Custom App

1. **Log into your Shopify Admin**
   - Go to your Shopify admin panel
   - Navigate to Apps > App and sales channel settings

2. **Create a Custom App**
   - Click "Develop apps for your store"
   - Click "Create an app"
   - Give your app a name (e.g., "8Ball Tires Booking System")

3. **Configure API Access**
   - In your app settings, go to "Configuration"
   - Set the app URL to your Laravel application URL
   - Set the allowed redirection URLs

4. **Generate Admin API Access Token**
   - Go to "API credentials" tab
   - Click "Generate admin API access token"
   - Copy the generated token - this is your `SHOPIFY_ADMIN_TOKEN`

5. **Configure API Scopes**
   - Your app needs the following scopes:
     - `read_products` - To fetch product variants
     - `read_inventory` - To check inventory levels
     - `read_locations` - To fetch store locations
     - `write_webhooks` - To create webhooks (optional)

6. **Get API Key and Secret**
   - Copy the API key and API secret from the "API credentials" tab
   - These are your `SHOPIFY_API_KEY` and `SHOPIFY_API_SECRET`

## Configuration

The Shopify integration is configured in `config/shopify.php`. The service automatically:

- Initializes the Shopify SDK with your credentials
- Handles rate limiting with automatic retry logic
- Provides methods for fetching locations, inventory, and product data
- Supports webhook creation for real-time updates

## Usage

### Basic Usage

```php
use App\Services\ShopifyService;

$shopify = new ShopifyService();

// Get all locations
$locations = $shopify->getLocations();

// Check inventory for a variant at a location
$available = $shopify->getInventoryForVariantAtLocation(
    'gid://shopify/ProductVariant/123456789',
    'gid://shopify/Location/987654321'
);

// Get product variants
$variants = $shopify->getProductVariants('gid://shopify/Product/123456789');

// Check if service parts are available
$isAvailable = $shopify->checkServiceInventoryAvailability($serviceParts, $locationGid);
```

### Inventory Checking

The service includes a method to check if all required parts for a service are available at a specific location:

```php
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

$isAvailable = $shopify->checkServiceInventoryAvailability($serviceParts, $locationGid);
```

## Testing

Run the Shopify integration tests:

```bash
php artisan test tests/Feature/ShopifyServiceTest.php
```

## Rate Limiting

The service automatically handles Shopify's rate limits with exponential backoff:

- Maximum 3 retries per request
- 2-second delay between retries
- Configurable in `config/shopify.php`

## Webhooks

The application includes comprehensive webhook support for real-time inventory and product updates.

### Registering Webhooks

Use the provided Artisan command to register webhooks:

```bash
# Register webhooks with your app URL
php artisan shopify:webhook:register

# Or specify a custom URL
php artisan shopify:webhook:register --url=https://your-app.com
```

This will automatically register webhooks for:
- `inventory_levels/update` - Triggers when inventory levels change
- `products/update` - Triggers when products are updated

### Webhook Endpoints

The application provides secure webhook endpoints:

- **POST** `/api/webhooks/shopify/inventory-updated`
- **POST** `/api/webhooks/shopify/product-updated`

All webhooks are protected with HMAC verification using your Shopify API secret.

### Webhook Processing

When webhooks are received:

1. **HMAC Verification** - Validates the webhook signature
2. **Logging** - Stores payload in `webhook_logs` table
3. **Background Processing** - Dispatches `RecomputeAvailability` job
4. **Inventory Update** - Fetches latest inventory from Shopify
5. **Cache Update** - Updates availability cache for real-time booking

### Manual Webhook Creation

You can also create webhooks programmatically:

```php
$webhook = $shopify->createWebhook(
    'inventory_levels/update',
    'https://your-app.com/api/webhooks/shopify/inventory-updated'
);
```

## Troubleshooting

### Common Issues

1. **Invalid API credentials**
   - Verify your `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, and `SHOPIFY_ADMIN_TOKEN`
   - Ensure the token has the required scopes

2. **Rate limiting**
   - The service automatically retries on 429 errors
   - Check logs for rate limit warnings

3. **GraphQL errors**
   - Verify your `SHOPIFY_API_VERSION` is supported
   - Check that the GIDs are valid Shopify resource identifiers

4. **Webhook issues**
   - Check webhook logs: `SELECT * FROM webhook_logs ORDER BY created_at DESC`
   - Verify HMAC signature matches Shopify's expectations
   - Ensure your app URL is accessible from Shopify's servers
   - Check queue workers are running: `php artisan queue:work`

### Logs

Check the Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

## Security Notes

- Never commit your `.env` file to version control
- Use environment-specific tokens for different environments
- Regularly rotate your API tokens
- Monitor API usage in your Shopify admin panel
