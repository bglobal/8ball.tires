<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip HMAC verification if disabled in config
        if (!config('shopify.webhooks.verify_hmac', true)) {
            return $next($request);
        }

        try {
            if (!$this->verifyShopifyHmac($request)) {
                Log::warning('Invalid Shopify webhook HMAC', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'headers' => $request->headers->all()
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error verifying Shopify webhook HMAC', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify Shopify HMAC signature
     */
    private function verifyShopifyHmac(Request $request): bool
    {
        $rawBody = $request->getContent();
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $apiSecret = config('shopify.api_secret');

        if (!$hmacHeader || !$apiSecret) {
            return false;
        }

        $computedHmac = base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true));

        return hash_equals($computedHmac, $hmacHeader);
    }
}
