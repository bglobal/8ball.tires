# 🏍️ 8Ball Tires - Motorcycle tires service booking system

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-13+-316192?style=for-the-badge&logo=postgresql&logoColor=white)
![Shopify](https://img.shields.io/badge/Shopify-API-96BF48?style=for-the-badge&logo=shopify&logoColor=white)

**A comprehensive, enterprise-grade motorcycle service booking platform with real-time inventory management and seamless Shopify integration.**

[🚀 Quick Start](#-quick-start) • [📚 API Documentation](#-api-documentation) • [🛠️ Admin Panel](#️-admin-panel) • [🔧 Configuration](#-configuration)

</div>

---

## 🌟 Overview

8Ball Tires is a sophisticated motorcycle service booking system designed for high-traffic environments. Built with Laravel 12 and featuring real-time inventory management through Shopify integration, it provides a seamless experience for both customers and administrators.

### ✨ Key Features

- **🎯 Real-Time Booking System** - Live availability checking with race condition protection
- **📦 Inventory Management** - Shopify-powered real-time stock tracking and validation
- **🏢 Multi-Location Support** - Manage multiple service centers with individual capacities
- **📊 Advanced Admin Dashboard** - Filament-powered management interface
- **🔌 API-First Architecture** - RESTful APIs for seamless frontend integration
- **⚡ Concurrency Protection** - High-performance booking with database-level locking
- **💳 Shopify Checkout Integration** - Direct payment processing through draft orders
- **📧 Automated Notifications** - Email confirmations with calendar attachments

---

## 🚀 Quick Start

### Prerequisites

- **PHP** 8.2 or higher
- **Composer** 2.0+
- **PostgreSQL** 13 or higher
- **Node.js** 18+ (for frontend assets)
- **Redis** (recommended for production)

### Installation

1. **Clone and Setup**
   ```bash
   git clone <repository-url>
   cd 8balltires/server
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup**
   ```bash
   # Configure your .env file
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=8balltires
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Run migrations and seed data
   php artisan migrate --seed
   ```

4. **Start Development Server**
   ```bash
   php artisan serve
   ```

### 🎯 Admin Access

After seeding, access the admin panel:
- **URL**: `http://localhost:8000/admin`
- **Email**: `admin@example.com`
- **Password**: `secret`

---

## 📚 API Documentation

### Base Configuration

```http
Base URL: http://localhost:8000/api
Content-Type: application/json
```

### 🔐 Authentication

Currently open for development. In production, implement JWT or OAuth2 authentication.

---

### 📍 Locations API

#### Get All Active Locations

```http
GET /api/locations
```

**Sample Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Downtown Service Center",
      "timezone": "America/New_York"
    },
    {
      "id": 2,
      "name": "Westside Motorcycle Shop",
      "timezone": "America/Los_Angeles"
    }
  ]
}
```

#### Get Location Details with Settings

```http
GET /api/location/{id}
```

**Purpose:** Get detailed information about a specific location including its operational settings

**Path Parameters:**
- `id` (required): Location ID

**Sample Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Downtown Service Center",
    "timezone": "America/New_York",
    "is_active": true,
    "settings": {
      "slot_duration_minutes": 60,
      "open_time": "08:00",
      "close_time": "18:00",
      "is_weekend_open": true,
      "weekend_open_time": "09:00",
      "weekend_close_time": "17:00",
      "capacity_per_slot": 3
    }
  }
}
```

**Error Responses:**
- `404 Not Found` - Location not found or inactive
- `422 Unprocessable Entity` - Invalid location ID format

**Error Response:**
```json
{
  "success": false,
  "error": "No active locations found"
}
```

---

### 🔧 Services API

#### Get All Active Services

```http
GET /api/services
```

**Sample Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Premium Oil Change",
      "duration_minutes": 60,
      "price": 89.99
    },
    {
      "id": 2,
      "title": "Brake Pad Replacement",
      "duration_minutes": 120,
      "price": 149.99
    },
    {
      "id": 3,
      "title": "Chain & Sprocket Service",
      "duration_minutes": 90,
      "price": 199.99
    }
  ]
}
```

---

### 📅 Availability API

#### Check Service Availability

```http
GET /api/availability?location_id=1&service_id=1&date=2025-01-30
```

**Query Parameters:**
- `location_id` (required): Location ID
- `service_id` (required): Service ID  
- `date` (required): Date in YYYY-MM-DD format

**Sample Response:**
```json
{
  "success": true,
  "data": [
    {
      "slotStart": "2025-01-30T09:00:00Z",
      "slotEnd": "2025-01-30T10:00:00Z",
      "seatsLeft": 3,
      "inventoryOk": true
    },
    {
      "slotStart": "2025-01-30T10:00:00Z",
      "slotEnd": "2025-01-30T11:00:00Z",
      "seatsLeft": 2,
      "inventoryOk": true
    },
    {
      "slotStart": "2025-01-30T11:00:00Z",
      "slotEnd": "2025-01-30T12:00:00Z",
      "seatsLeft": 0,
      "inventoryOk": false
    }
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Invalid date provided",
  "message": "Date must be today or in the future"
}
```

---

### 🚫 Blackouts API

#### Get Blackout Dates

```http
GET /api/blackout/{id}
```

**Purpose:** List all blackout dates for the selected location

**Path Parameters:**
- `id` (required): Location ID

**Sample Response:**
```json
["2025-10-05", "2025-10-11", "2025-12-25"]
```

**Error Responses:**
- `422 Unprocessable Entity` - Invalid location ID format
- `500 Internal Server Error` - Server error

---

### 📝 Bookings API

#### Create New Booking

```http
POST /api/bookings
```

**Sample Request:**
```json
{
  "location_id": 1,
  "service_id": 1,
  "slot_start_iso": "2025-01-30T10:00:00-05:00",
  "seats": 1,
  "customer": {
    "name": "John Smith",
    "phone": "+15551234567",
    "email": "john.smith@example.com"
  }
}
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "confirmed",
    "slot_start": "2025-01-30T10:00:00-05:00",
    "slot_end": "2025-01-30T11:00:00-05:00",
    "shopify": {
      "draft_order_id": "gid://shopify/DraftOrder/1069920508",
      "invoice_url": "https://checkout.shopify.com/c/abc123def456",
      "total_price": "89.99",
      "currency_code": "USD"
    }
  }
}
```

**Validation Error Response:**
```json
{
  "success": false,
  "error": "Validation failed",
  "errors": {
    "location_id": ["The selected location does not exist."],
    "customer.email": ["The customer email must be a valid email address."]
  }
}
```

**Capacity Error Response:**
```json
{
  "success": false,
  "error": "Insufficient capacity",
  "message": "No seats available for the selected time slot"
}
```

**Inventory Error Response:**
```json
{
  "success": false,
  "error": "Insufficient inventory",
  "message": "Some required parts are not available in sufficient quantity for this booking."
}
```

#### Get Booking Details

```http
GET /api/bookings/123
```

**Sample Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "confirmed",
    "slot_start": "2025-01-30T10:00:00-05:00",
    "slot_end": "2025-01-30T11:00:00-05:00",
    "seats": 1,
    "customer": {
      "name": "John Smith",
      "phone": "+15551234567",
      "email": "john.smith@example.com"
    },
    "service": {
      "id": 1,
      "title": "Premium Oil Change",
      "duration_minutes": 60,
      "price": 89.99
    },
    "location": {
      "id": 1,
      "name": "Downtown Service Center",
      "timezone": "America/New_York"
    }
  }
}
```

**Not Found Response:**
```json
{
  "success": false,
  "error": "Booking not found"
}
```

---

### 📊 API Documentation Viewer

Access the interactive API documentation:
- **Swagger UI**: `http://localhost:8000/api/docs`
- **OpenAPI Spec**: `http://localhost:8000/api/docs/api.yaml`

---

## 🛠️ Admin Panel

### Dashboard Features

- **📈 Real-time Analytics** - Booking trends and revenue metrics
- **📅 Upcoming Bookings** - Next 10 scheduled appointments
- **🏢 Location Utilization** - Seats usage across all locations
- **📊 Performance Charts** - Daily booking statistics

### Management Modules

#### Services Management
- Create and edit service types
- Set pricing and duration
- Configure service parts (BOM)
- Manage active/inactive status

#### Locations Management
- Multi-location configuration
- Operating hours and timezone settings
- Capacity and resource management
- Blackout date scheduling

#### Bookings Management
- View all bookings with filters
- Status management (pending, confirmed, cancelled)
- Customer information management
- Export capabilities

#### Calendar Preview
- Visual availability calendar
- Real-time slot generation
- Inventory status indicators
- Location-specific views

---

## 🔧 Configuration

### Shopify Integration

#### 1. Create Shopify Custom App

1. Navigate to **Shopify Admin** → **Apps** → **App and sales channel settings**
2. Click **Develop apps** → **Create an app**
3. Configure **API access scopes**:
   ```
   ✓ read_products
   ✓ read_inventory  
   ✓ read_locations
   ✓ write_webhooks
   ✓ write_draft_orders
   ```

#### 2. Environment Variables

Add to your `.env` file:
```env
# Shopify Configuration
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret
SHOPIFY_ADMIN_TOKEN=your_admin_token
SHOPIFY_API_VERSION=2025-07
```

#### 3. Webhook Registration

```bash
php artisan shopify:webhook:register
```

**Registered Webhooks:**
- `inventory_levels/update` → `/api/webhooks/shopify/inventory-updated`
- `products/update` → `/api/webhooks/shopify/product-updated`
- `orders/create` → `/api/webhooks/shopify/order-created`

### Database Configuration

#### PostgreSQL Setup
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=8balltires
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

#### Redis Configuration (Recommended)
```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## 🧪 Development

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=BookingsControllerTest

# Run with coverage
php artisan test --coverage
```

### Code Quality

```bash
# Code formatting
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Security scanning
./vendor/bin/security-checker security:check
```

### Database Management

```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Seed specific data
php artisan db:seed --class=SampleDataSeeder

# Reset specific migration
php artisan migrate:rollback --step=1
```

---

## 🚀 Production Deployment

### Environment Setup

1. **Production Configuration**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   LOG_LEVEL=error
   ```

2. **Database Optimization**
   ```bash
   # Enable query logging
   DB_LOG_QUERIES=true
   
   # Configure connection pooling
   DB_POOL_SIZE=20
   ```

3. **Caching Strategy**
   ```bash
   # Enable route caching
   php artisan route:cache
   
   # Enable config caching
   php artisan config:cache
   
   # Enable view caching
   php artisan view:cache
   ```

### Security Considerations

- **HTTPS Enforcement** - All webhook endpoints require HTTPS
- **HMAC Verification** - Shopify webhook signature validation
- **Rate Limiting** - API endpoints protected with throttling
- **Input Validation** - Comprehensive request validation
- **SQL Injection Protection** - Eloquent ORM with parameter binding

### Performance Optimization

- **Database Indexing** - Optimized indexes for booking queries
- **Query Optimization** - Eager loading and query caching
- **Redis Caching** - Availability and inventory caching
- **CDN Integration** - Static asset delivery
- **Queue Processing** - Background job processing

---

## 📈 Monitoring & Logging

### Application Logs
```bash
# View application logs
tail -f storage/logs/laravel.log

# View specific log levels
grep "ERROR" storage/logs/laravel.log
```

### Performance Monitoring
- **Database Query Logging** - Monitor slow queries
- **API Response Times** - Track endpoint performance
- **Error Tracking** - Comprehensive error logging
- **Webhook Processing** - Monitor Shopify integration

---

## 🤝 Support & Contributing

### Getting Help

- **Documentation**: Check this README and inline code comments
- **Issues**: Report bugs and feature requests via GitHub issues
- **Discussions**: Use GitHub discussions for questions

### Development Guidelines

1. **Code Style** - Follow PSR-12 standards
2. **Testing** - Write tests for new features
3. **Documentation** - Update README for API changes
4. **Security** - Follow security best practices

---

## 📄 License

This project is proprietary software developed for 8Ball Tires. All rights reserved.

---

<div align="center">

**Built with ❤️ for the motorcycle community**

[🏠 Home](#-8ball-tires---motorcycle-service-booking-system) • [📚 API Docs](#-api-documentation) • [🛠️ Admin Panel](#️-admin-panel)

</div>
