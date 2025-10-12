<?php

namespace App\Http\Controllers\Api;

use App\DTOs\BookingRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateBookingRequest;
use App\Models\Booking;
use App\Models\DraftOrder;
use App\Models\Service;
use App\Models\ServicePart;
use App\Services\AvailabilityService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BookingsController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private ShopifyService $shopifyService
    ) {}

    /**
     * Create a new booking
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        try {
            // Convert ISO datetime to UTC
            $slotStartUtc = Carbon::parse($request->input('slot_start_iso'))->utc();

            // Default seats to 1 if not provided
            $seats = $request->input('seats', 1);

            // Sanitize phone and email
            $phone = $this->sanitizePhone($request->input('customer.phone'));
            $email = $this->sanitizeEmail($request->input('customer.email'));
            $variantId = $request->input('product_variant_id');
            Log::info("Received variant ID", ['variantId' => $variantId, 'type' => gettype($variantId)]);

            // Validate variant ID format
            if (!$variantId || !is_string($variantId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid product variant ID format',
                    'message' => 'Product variant ID must be a valid string.'
                ], 422);
            }

            // Validate GID format
            if (!preg_match('/^gid:\/\/shopify\/ProductVariant\/\d+$/', $variantId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid product variant ID format',
                    'message' => 'Product variant ID must be a valid Shopify GID format (gid://shopify/ProductVariant/123456789).'
                ], 422);
            }

            // Fetch serviceId using product variant ID from service parts table
            $serviceId = 0; // Initialize with 0 instead of null
            $usedVariantId = null;
            $servicePartTitle = null;
            if ($variantId) {
                Log::info("Searching for service part", ['variantId' => $variantId]);
                $servicePart = ServicePart::where('shopify_variant_gid', $variantId)->first();
                Log::info("Service part query result", [
                    'servicePart' => $servicePart ? $servicePart->toArray() : null,
                    'variantId' => $variantId
                ]);
                if ($servicePart) {
                    $serviceId = $servicePart->service_id;
                    $servicePartTitle = $servicePart->product_title; // Get service part title
                    Log::info("Service ID found", ['serviceId' => $serviceId, 'variantId' => $variantId, 'servicePartTitle' => $servicePartTitle]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid product variant ID',
                        'message' => 'The provided product variant ID does not exist in our system.'
                    ], 422);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Product variant ID required',
                    'message' => 'A product variant ID is required to create a booking.'
                ], 422);
            }
            $bookingRequest = new BookingRequest(
                $request->input('location_id'),
                $serviceId,
                $slotStartUtc,
                $seats,
                $request->input('customer.name'),
                $phone,
                $email
            );

            $result = $this->availabilityService->lockAndBook($bookingRequest);

            if (!$result['success']) {
                // Handle specific error cases
                if ($result['error'] === 'Insufficient inventory') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Insufficient inventory',
                        'message' => 'Some required parts are not available in sufficient quantity for this booking.'
                    ], 422);
                }

                return response()->json($result, $result['status']);
            }

            // Get the created booking with timezone conversion
            $booking = Booking::with(['location', 'service'])->find($result['booking_id']);

            // Create Shopify draft order
            $draftOrderResult = $this->createShopifyDraftOrder($booking, $variantId);

            // Store draft order in database if successful
            if ($draftOrderResult['success']) {
                $draftOrder = $this->storeDraftOrder($booking, $draftOrderResult['draftOrder']);

                // Update booking with draft order ID
                $booking->update(['draft_order_id' => $draftOrder->draft_order_id]);
            }

            // Send confirmation email
            $this->sendConfirmationEmail($booking);

            $responseData = [
                'id' => $booking->id,
                'status' => $booking->status->value,
                'slot_start' => $this->convertToLocationTimezone($booking->slot_start_utc, $booking->location->timezone),
                'slot_end' => $this->convertToLocationTimezone($booking->slot_end_utc, $booking->location->timezone),
            ];

            // Add Shopify draft order info if successful
            if ($draftOrderResult['success']) {
                $responseData['shopify'] = [
                    'draft_order_id' => $draftOrderResult['draftOrder']['id'],
                    'invoice_url' => $draftOrderResult['draftOrder']['invoiceUrl'],
                    'total_price' => $draftOrderResult['draftOrder']['totalPrice'],
                    'currency_code' => $draftOrderResult['draftOrder']['currencyCode'],
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Booking failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific booking
     */
    public function show(int $id): JsonResponse
    {
        try {
            $booking = Booking::with(['location', 'service'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'status' => $booking->status->value,
                    'slot_start' => $this->convertToLocationTimezone($booking->slot_start_utc, $booking->location->timezone),
                    'slot_end' => $this->convertToLocationTimezone($booking->slot_end_utc, $booking->location->timezone),
                    'seats' => $booking->seats,
                    'customer' => [
                        'name' => $booking->customer_name,
                        'phone' => $booking->phone,
                        'email' => $booking->email,
                    ],
                    'service' => [
                        'id' => $booking->service->id,
                        'title' => $booking->service->title,
                        'duration_minutes' => $booking->service->duration_minutes,
                        'price' => $booking->service->price_cents / 100,
                    ],
                    'location' => [
                        'id' => $booking->location->id,
                        'name' => $booking->location->name,
                        'timezone' => $booking->location->timezone,
                    ],
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Booking not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert UTC datetime to location timezone
     */
    private function convertToLocationTimezone(Carbon $utcDateTime, string $timezone): string
    {
        return $utcDateTime->setTimezone($timezone)->toISOString();
    }

    /**
     * Sanitize phone number
     */
    private function sanitizePhone(string $phone): string
    {
        // Remove all non-numeric characters except + at the beginning
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // If it doesn't start with +, add +1 for US numbers
        if (!str_starts_with($phone, '+')) {
            $phone = '+1' . $phone;
        }

        return $phone;
    }

    /**
     * Sanitize email address
     */
    private function sanitizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Send confirmation email with calendar ICS
     */
    private function sendConfirmationEmail(Booking $booking): void
    {
        try {
            // Create ICS content
            $icsContent = $this->generateICS($booking);

            // Send email (you'll need to implement this based on your mail setup)
            \Mail::send('emails.booking-confirmation', [
                'booking' => $booking,
                'icsContent' => $icsContent
            ], function ($message) use ($booking) {
                $message->to($booking->email)
                    ->subject('Booking Confirmation - ' . $booking->service->title)
                    ->attachData($this->generateICS($booking), 'booking.ics', [
                        'mime' => 'text/calendar'
                    ]);
            });
        } catch (\Exception $e) {
            // Log error but don't fail the booking
            \Log::error('Failed to send confirmation email', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate ICS calendar content
     */
    private function generateICS(Booking $booking): string
    {
        $start = $booking->slot_start_utc->setTimezone($booking->location->timezone);
        $end = $booking->slot_end_utc->setTimezone($booking->location->timezone);

        $ics = "BEGIN:VCALENDAR\n";
        $ics .= "VERSION:2.0\n";
        $ics .= "PRODID:-//8Ball Tires//Booking System//EN\n";
        $ics .= "BEGIN:VEVENT\n";
        $ics .= "UID:" . $booking->id . "@8balltires.com\n";
        $ics .= "DTSTART:" . $start->format('Ymd\THis\Z') . "\n";
        $ics .= "DTEND:" . $end->format('Ymd\THis\Z') . "\n";
        $ics .= "SUMMARY:" . $booking->service->title . " - " . $booking->location->name . "\n";
        $ics .= "DESCRIPTION:Booking ID: " . $booking->id . "\\n";
        $ics .= "Seats: " . $booking->seats . "\\n";
        $ics .= "Location: " . $booking->location->name . "\n";
        $ics .= "LOCATION:" . $booking->location->name . "\n";
        $ics .= "STATUS:CONFIRMED\n";
        $ics .= "END:VEVENT\n";
        $ics .= "END:VCALENDAR\n";

        return $ics;
    }

    /**
     * Create Shopify draft order for the booking
     */
    private function createShopifyDraftOrder(Booking $booking, string $servicePartVariantId): array
    {
        try {
            // Get service part details from the variant ID
            $servicePart = ServicePart::where('shopify_variant_gid', $servicePartVariantId)->first();
            if (!$servicePart) {
                throw new \Exception('Service part not found for variant ID: ' . $servicePartVariantId);
            }

            // Get service variant price from Shopify
            $serviceVariantGid = $booking->service->shopify_variant_gid;
            $serviceVariantPrice = $this->getVariantPrice($serviceVariantGid);

            // Get service part variant price from Shopify
            $servicePartVariantPrice = $this->getVariantPrice($servicePartVariantId);

            // Build line items for the draft order
            $lineItems = [];

            // Add only the specific variant ID as a line item
            $lineItems[] = [
                'title' => $servicePart->product_title, // Use service part title
                'variantId' => $serviceVariantGid, // Use GID format for GraphQL API
                'quantity' => $booking->seats,
                'priceOverride' => [
                    'amount' => (string) ($serviceVariantPrice + $servicePartVariantPrice), // Service variant price + service part variant price
                    'currencyCode' => 'USD'
                ],
                'customAttributes' => [
                    [
                        'key' => 'booking_id',
                        'value' => (string) $booking->id
                    ],
                    [
                        'key' => 'service_type',
                        'value' => 'motorcycle_service'
                    ],
                    [
                        'key' => 'service_product_id',
                        'value' => $booking->service->shopify_product_id
                    ],
                    [
                        'key' => 'service_variant_id',
                        'value' => $booking->service->shopify_variant_gid
                    ],
                    [
                        'key' => 'service_part_variant_id',
                        'value' => $servicePartVariantId
                    ],
                    [
                        'key' => 'service_price',
                        'value' => '$' . number_format($serviceVariantPrice, 2)
                    ],
                    [
                        'key' => 'service_part_price',
                        'value' => '$' . number_format($servicePartVariantPrice, 2)
                    ],
                    [
                        'key' => 'product_title',
                        'value' => $servicePart->product_title
                    ],
                    [
                        'key' => 'location_name',
                        'value' => $booking->location->name
                    ],
                    [
                        'key' => 'slot_date',
                        'value' => $booking->slot_start_utc->setTimezone($booking->location->timezone)->format('Y-m-d')
                    ],
                    [
                        'key' => 'slot_time',
                        'value' => $booking->slot_start_utc->setTimezone($booking->location->timezone)->format('H:i')
                    ]
                ]
            ];

            // Build draft order data
            $draftOrderData = [
                'note' => "Motorcycle Service Booking #{$booking->id}",
                'email' => $booking->email,
                'phone' => $booking->phone,
                'lineItems' => $lineItems,
                'tags' => ['motorcycle-service', 'booking', "booking-{$booking->id}"]
            ];

            // Log the draft order data for debugging
            \Log::info('Creating draft order with data:', [
                'draftOrderData' => $draftOrderData,
                'booking_id' => $booking->id,
                'service_variant_gid' => $booking->service->shopify_variant_gid
            ]);

            return $this->shopifyService->createDraftOrder($draftOrderData);
        } catch (\Exception $e) {
            \Log::error('Failed to create Shopify draft order', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Parse variant ID from Shopify GID
     */
    private function parseVariantIdFromGid(string $gid): string
    {
        // Extract numeric ID from GID format: gid://shopify/ProductVariant/45583280308423
        if (preg_match('/gid:\/\/shopify\/ProductVariant\/(\d+)/', $gid, $matches)) {
            return $matches[1];
        }

        // If it's already a numeric ID, return as is
        if (is_numeric($gid)) {
            return $gid;
        }

        throw new \Exception('Invalid variant GID format: ' . $gid);
    }

    /**
     * Get variant price from Shopify
     */
    private function getVariantPrice(string $variantGid): float
    {
        try {
            // This is a simplified version - you might want to implement proper caching
            $response = $this->shopifyService->executeGraphQLQuery('
                query getVariant($id: ID!) {
                    productVariant(id: $id) {
                        id
                        price
                    }
                }
            ', ['id' => $variantGid]);

            $variant = $response['data']['productVariant'] ?? null;
            return $variant ? (float) $variant['price'] : 0.0;
        } catch (\Exception $e) {
            \Log::warning('Failed to get variant price, using default', [
                'variant_gid' => $variantGid,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Store draft order in database
     */
    private function storeDraftOrder(Booking $booking, array $shopifyDraftOrder): DraftOrder
    {
        return DraftOrder::create([
            'shopify_draft_order_id' => $shopifyDraftOrder['id'],
            'invoice_url' => $shopifyDraftOrder['invoiceUrl'] ?? null,
            'total_price' => $shopifyDraftOrder['totalPrice'] ?? null,
            'currency_code' => $shopifyDraftOrder['currencyCode'] ?? 'USD',
            'status' => 'draft',
            'payload' => $shopifyDraftOrder, // Store the full Shopify response
        ]);
    }
}
