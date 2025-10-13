<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Confirmation - 8Ball Tires</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #1f2937;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .booking-details {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-label {
            font-weight: bold;
            color: #374151;
        }
        .detail-value {
            color: #6b7280;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>8Ball Tires</h1>
        <h2>Booking Confirmation</h2>
    </div>
    
    <div class="content">
        <p>Hello {{ $booking->customer_name }},</p>
        
        <p>Your booking has been confirmed! Here are the details:</p>
        
        <div class="booking-details">
            <div class="detail-row">
                <span class="detail-label">Booking ID:</span>
                <span class="detail-value">#{{ $booking->id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service:</span>
                <span class="detail-value">{{ $booking->service->title }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location:</span>
                <span class="detail-value">{{ $booking->location->name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time:</span>
                <span class="detail-value">
                    {{ $booking->slot_start_utc->setTimezone($booking->location->timezone)->format('M j, Y \a\t g:i A') }}
                    - 
                    {{ $booking->slot_end_utc->setTimezone($booking->location->timezone)->format('g:i A') }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duration:</span>
                <span class="detail-value">{{ $booking->service->duration_minutes }} minutes</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Seats:</span>
                <span class="detail-value">{{ $booking->seats }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Price:</span>
                <span class="detail-value">${{ number_format($booking->service->price_cents / 100, 2) }}</span>
            </div>
        </div>
        
        <p>We've attached a calendar file (.ics) to this email that you can add to your calendar.</p>
        
        <p><strong>Important Notes:</strong></p>
        <ul>
            <li>Please arrive 10 minutes before your scheduled time</li>
            <li>Bring a valid ID and any required documents</li>
            <li>If you need to reschedule or cancel, please contact us at least 24 hours in advance</li>
        </ul>
        
        <p>If you have any questions, please don't hesitate to contact us.</p>
        
        <p>Thank you for choosing 8Ball Tires!</p>
        
        <div class="footer">
            <p>8Ball Tires Service Center</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
