# RideConnect API - Swagger/OpenAPI Integration

This document provides instructions for integrating Swagger UI with the RideConnect Laravel API.

## Installation

### Step 1: Install L5-Swagger Package

```bash
composer require l5-swagger/L5-swagger
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

### Step 3: Configure OpenAPI

Update `config/l5-swagger.php`:

```php
' swagger_versions' => ['v1'],
'default_api_version' => 'v1',

'doc_versions' => [
    'v1' => [
        'api_title' => 'RideConnect API',
        'api_description' => 'RideConnect is a comprehensive ride-sharing and booking platform...',
    ],
],
```

### Step 4: Add Annotations

Add OpenAPI annotations to your controllers. Example:

```php
<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

/**
 * @OA\Info(
 *     title="RideConnect API",
 *     version="1.0.0",
 *     description="RideConnect Backend API Documentation"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="role", type="string", enum={"PASSENGER", "DRIVER"})
     *         )
     *     ),
     *     @OA\Response(response="201", description="User registered"),
     *     @OA\Response(response="422", description="Validation error")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // ...
    }
}
```

## Accessing Swagger UI

After installation, access Swagger UI at:

- Local: http://localhost:8000/api/documentation
- API v1: http://localhost:8000/api/v1/documentation

## Generating Documentation

### Generate on every request (Development)

In `config/l5-swagger.php`:

```php
'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true),
```

### Generate manually (Production)

```bash
php artisan l5-swagger:generate
```

## Security

### Protect Swagger UI in Production

Add to `routes/api.php`:

```php
Route::get('/documentation', function () {
    if (app()->isProduction()) {
        abort(403, 'Documentation not available in production');
    }
    return view('l5-swagger::index');
})->name('l5-swagger');
```

Or create middleware:

```php
// app/Http/Middleware/SwaggerAuth.php
public function handle($request, Closure $next)
{
    if (app()-> !auth()->check()) {
        abortisProduction() &&(403, 'Documentation protected');
    }
    return $next($request);
}
```

## Postman Collection

### Export from Swagger

1. Open Swagger UI at `/api/documentation`
2. Click "Export" button
3. Select "Postman Collection v2.1"
4. Import into Postman

### Manual Postman Setup

Create collection with environment variables:

```json
{
  "info": {
    "name": "RideConnect API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "baseUrl",
      "value": "http://localhost:8000/api/v1"
    },
    {
      "key": "token",
      "value": ""
    }
  ]
}
```

### Authentication Header

For all protected endpoints, add header:

```
Authorization: Bearer {{token}}
```

## Example Request Bodies

### Register User

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securePassword123",
  "password_confirmation": "securePassword123",
  "phone": "+1234567890",
  "role": "PASSENGER"
}
```

### Login

```json
{
  "email": "john@example.com",
  "password": "securePassword123"
}
```

### Book Ride

```json
{
  "ride_id": 1,
  "seats": 2,
  "pickup_address": "123 Main St, City",
  "dropoff_address": "456 Oak Ave, City"
}
```

### Create Payment

```json
{
  "type": "trip",
  "trip_id": 1,
  "amount": 25.00,
  "payment_method": "card"
}
```

### Create Support Ticket

```json
{
  "title": "Issue with ride",
  "description": "Detailed description of the issue",
  "ticket_type": "ride",
  "priority": "high"
}
```

## Testing Endpoints

### Using cURL

```bash
# Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@test.com","password":"password123","password_confirmation":"password123","role":"PASSENGER"}'

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@test.com","password":"password123"}'

# Get Profile
curl -X GET http://localhost:8000/api/v1/auth/profile \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Troubleshooting

### OpenAPI YAML Not Found

```bash
php artisan config:clear
php artisan cache:clear
php artisan l5-swagger:generate
```

### Route Not Found

Ensure routes are registered:

```bash
php artisan route:list | grep documentation
```

### Blank Page

Check logs:

```bash
tail -f storage/logs/laravel.log
```
