# GET /v1/passenger/profile - Complete Endpoint Documentation

## Endpoint Overview
- **URL:** `GET /api/v1/passenger/profile`
- **Authentication:** Required (Bearer Token)
- **Response Format:** JSON
- **HTTP Status:** 200 OK

## Purpose
Retrieves comprehensive passenger profile information including statistics, ratings, preferences, and verification status.

## Request

### Headers
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

### Example Request
```bash
curl -X GET http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  -H "Content-Type: application/json"
```

## Response

### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Jane Passenger",
    "email": "test.passenger@example.com",
    "phone": "+250788123456",
    "role": "PASSENGER",
    "profile_photo": "https://api.example.com/photos/jane.jpg",
    "is_approved": true,
    "is_verified": true,
    "member_since": "2026-01-15T08:30:00.000000Z",
    "statistics": {
      "total_trips": 42,
      "total_bookings": 35,
      "completed_bookings": 33,
      "total_spent": 875500.00,
      "average_spent_per_trip": 25014.29,
      "rating": 4.8,
      "reliability_score": 0.95,
      "cancellation_rate": 0.05
    },
    "preferences": {
      "preferred_payment_method": "card",
      "emergency_contact_name": "John Passenger",
      "emergency_contact_phone": "+250788654321",
      "saved_locations_count": 5
    },
    "verification": {
      "verified": true,
      "approved": true,
      "verified_at": "2026-01-15T08:35:00.000000Z",
      "approved_at": "2026-01-16T10:00:00.000000Z"
    }
  }
}
```

### Error Response - Not a Passenger (403)
```json
{
  "success": false,
  "message": "Only passengers can access this resource"
}
```

### Error Response - Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

## Response Fields Description

### Basic Information
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | User ID |
| `name` | string | Passenger's full name |
| `email` | string | Email address |
| `phone` | string | Phone number with country code |
| `role` | string | User role (always "PASSENGER") |
| `profile_photo` | string \| null | URL to profile photo |
| `is_approved` | boolean | Account approval status |
| `is_verified` | boolean | Email verification status |
| `member_since` | ISO8601 datetime | Account creation timestamp |

### Statistics
| Field | Type | Description |
|-------|------|-------------|
| `total_trips` | integer | Total number of completed trips |
| `total_bookings` | integer | Total number of bookings made |
| `completed_bookings` | integer | Bookings with COMPLETED status |
| `total_spent` | float | Total amount spent on all rides |
| `average_spent_per_trip` | float | Average cost per booking |
| `rating` | float | Passenger rating (0-5 scale) |
| `reliability_score` | float | Reliability metric (0-1 scale) |
| `cancellation_rate` | float | Percentage of cancelled bookings (0-1 scale) |

### Preferences
| Field | Type | Description |
|-------|------|-------------|
| `preferred_payment_method` | string | Default payment method (e.g., "card", "mobile_money") |
| `emergency_contact_name` | string \| null | Emergency contact person's name |
| `emergency_contact_phone` | string \| null | Emergency contact phone number |
| `saved_locations_count` | integer | Number of saved favorite locations |

### Verification
| Field | Type | Description |
|-------|------|-------------|
| `verified` | boolean | Email verification status |
| `approved` | boolean | Admin approval status |
| `verified_at` | ISO8601 datetime \| null | When email was verified |
| `approved_at` | ISO8601 datetime \| null | When account was approved |

## Real Data Calculation

### Statistics Calculation
- **total_trips**: `COUNT(trips WHERE passenger_id = user.id)`
- **total_bookings**: `COUNT(bookings WHERE user_id = user.id)`
- **completed_bookings**: `COUNT(bookings WHERE user_id = user.id AND status = 'COMPLETED')`
- **total_spent**: `SUM(booking.total_price WHERE user_id = user.id AND status != 'CANCELLED')`
- **average_spent_per_trip**: `total_spent / total_bookings`
- **rating**: From `PassengerBehavior.rating` or default 5.0
- **reliability_score**: From `PassengerBehavior.reliability_score` or default 1.0
- **cancellation_rate**: From `PassengerBehavior.cancellation_rate` or default 0.0

### Data Sources
- **Basic Info**: `users` table
- **Statistics**: Aggregated from `bookings` and `trips` tables
- **Rating Data**: `passenger_behaviors` table
- **Preferences**: `users` table (new fields)
- **Verification**: `users` table timestamps

## Postman Setup

### 1. Create Authorization Token
```bash
POST /api/v1/auth/mobile/login
{
  "email_or_phone": "test.passenger@example.com",
  "password": "Password123!"
}
```

### 2. Extract Token from Response
Copy the `token` value from the login response

### 3. Set Environment Variable in Postman
```
{{auth_token}} = <copied_token>
```

### 4. Call Profile Endpoint
```
GET {{base_url}}/api/v1/passenger/profile
Authorization: Bearer {{auth_token}}
```

## Usage Examples

### Get Full Profile Data
```bash
curl -X GET "http://localhost:8000/api/v1/passenger/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Process Response in Flutter
```dart
Future<PassengerProfile> getPassengerProfile(String token) async {
  final response = await http.get(
    Uri.parse('$baseUrl/api/v1/passenger/profile'),
    headers: {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
    },
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    return PassengerProfile.fromJson(json['data']);
  } else {
    throw Exception('Failed to load profile');
  }
}
```

## Key Features

✅ **Real-time Statistics**: All metrics calculated from actual database records
✅ **Behavioral Metrics**: Includes reliability score and cancellation rate
✅ **Payment Preferences**: Stores and returns preferred payment method
✅ **Emergency Contact**: Critical safety information
✅ **Verification Status**: Both email verification and admin approval tracked
✅ **Audit Trail**: Timestamps for all critical actions
✅ **Comprehensive Data**: Single endpoint provides all necessary profile information

## Integration Notes

1. **Token Refresh**: Token may expire; implement token refresh logic in your app
2. **Caching**: Consider caching profile data locally for offline access
3. **Updates**: Use `PUT /v1/passenger/profile` to update basic information
4. **Statistics**: Statistics are read-only and auto-calculated from bookings
5. **Error Handling**: Always handle 401 (unauthorized) and 403 (forbidden) responses
