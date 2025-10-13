<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ShopifyWebhookRegister extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:webhook:register {--url=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Shopify webhooks for inventory and product updates';

    /**
     * Execute the console command.
     */
    public function handle(ShopifyService $shopifyService)
    {
        $this->info('Registering Shopify webhooks...');

        // Get the webhook URL from option or config
        $webhookUrl = $this->option('url') ?: $this->getWebhookUrl();
        
        if (!$webhookUrl) {
            $this->error('Webhook URL is required. Use --url option or set APP_URL in .env');
            return 1;
        }

        $webhooks = [
            'inventory_levels/update' => $webhookUrl . '/api/webhooks/shopify/inventory-updated',
            'products/update' => $webhookUrl . '/api/webhooks/shopify/product-updated',
        ];

        $successCount = 0;
        $failureCount = 0;

        foreach ($webhooks as $topic => $callbackUrl) {
            try {
                $this->info("Registering webhook for topic: {$topic}");
                $this->line("Callback URL: {$callbackUrl}");

                $webhook = $shopifyService->createWebhook($topic, $callbackUrl);
                
                if (!empty($webhook)) {
                    $this->info("✅ Successfully registered webhook for {$topic}");
                    $this->line("Webhook ID: " . ($webhook['id'] ?? 'N/A'));
                    $successCount++;
                } else {
                    $this->error("❌ Failed to register webhook for {$topic}");
                    $failureCount++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error registering webhook for {$topic}: " . $e->getMessage());
                $failureCount++;
            }

            $this->line(''); // Empty line for readability
        }

        // Summary
        $this->info("Webhook registration complete!");
        $this->line("✅ Successful: {$successCount}");
        $this->line("❌ Failed: {$failureCount}");

        if ($failureCount > 0) {
            $this->warn('Some webhooks failed to register. Check your Shopify credentials and try again.');
            return 1;
        }

        return 0;
    }

    /**
     * Get webhook URL from configuration
     */
    private function getWebhookUrl(): ?string
    {
        $appUrl = Config::get('app.url');
        
        if (!$appUrl) {
            return null;
        }

        // Ensure URL doesn't end with slash
        return rtrim($appUrl, '/');
    }
}
