# Flutter Passenger Registration - Complete Integration Guide

## Overview
This document provides Flutter mobile app developers with all necessary APIs, input requirements, and response specifications for implementing passenger registration and authentication.

---

## 1. Registration API Endpoints

### Endpoint: Passenger Registration
**URL:** `POST https://<your-backend>/api/v1/auth/register/passenger`

**Description:** Registers a new passenger account through the mobile app. Only PASSENGER role is allowed.

#### Request Headers
```
Content-Type: application/json
Accept: application/json
```

#### Request Body (JSON)
```json
{
  "full_name": "John Doe",
  "email": "john.passenger@example.com",
  "phone_number": "+250788000222",
  "password": "SecurePassword@123",
  "password_confirmation": "SecurePassword@123"
}
```

#### Input Field Specifications

| Field | Type | Validation | Notes |
|-------|------|-----------|-------|
| `full_name` or `name` | String | Required, max 255 chars | First and last name separated by space |
| `email` | String (Email) | Required, unique, valid email format | Must not already exist in database |
| `phone_number` or `phone` | String | Required, max 20 chars | International format: `+250788000222` |
| `password` | String | Required, min 8 chars, must contain uppercase, lowercase, number, special char | Example: `SecurePassword@123` |
| `password_confirmation` | String | Required, must match password | For validation on backend |

#### Success Response (201 Created)
```json
{
  "success": true,
  "message": "Registration successful. Your account is pending approval.",
  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john.passenger@example.com",
      "role": "PASSENGER",
      "phone": "+250788000222",
      "is_approved": false
    }
  }
}
```

#### Error Responses

**400 Bad Request - Validation Error**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["This email is already registered."],
    "phone": ["Phone number is required."],
    "password": ["Password must contain at least one uppercase letter."]
  }
}
```

**422 Unprocessable Entity - Invalid Input**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password_confirmation": ["Password confirmation does not match."]
  }
}
```

---

## 2. Login API Endpoints

### Endpoint A: Mobile Login (Recommended for Flutter)
**URL:** `POST https://<your-backend>/api/v1/auth/mobile/login`

**Description:** Login using email OR phone number. More flexible for mobile users.

#### Request Body
```json
{
  "login": "+250788000222",
  "password": "SecurePassword@123",
  "device_name": "flutter-android"
}
```

#### Input Field Specifications

| Field | Type | Validation | Notes |
|-------|------|-----------|-------|
| `login` | String | Required | Either email or phone number |
| `password` | String | Required | User's registered password |
| `device_name` | String | Optional | Device identifier (default: `flutter-mobile`) |

#### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john.passenger@example.com",
      "role": "PASSENGER",
      "phone": "+250788000222"
    },
    "token": "1|Y5Z8X9W7V6U5T4S3R2Q1P0...",
    "token_type": "Bearer"
  }
}
```

#### Error Response (Unapproved Account - 403)
```json
{
  "success": false,
  "message": "Your account is pending approval. Please contact administrator.",
  "status_code": 403
}
```

#### Error Response (Invalid Credentials - 401)
```json
{
  "success": false,
  "message": "Invalid credentials",
  "status_code": 401
}
```

### Endpoint B: Email Login (Legacy)
**URL:** `POST https://<your-backend>/api/v1/auth/login`

```json
{
  "email": "john.passenger@example.com",
  "password": "SecurePassword@123"
}
```

---

## 3. Session Management APIs

### Endpoint: Logout
**URL:** `POST https://<your-backend>/api/v1/auth/logout`

**Auth:** Bearer token required

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Endpoint: Clear Session (All Devices)
**URL:** `POST https://<your-backend>/api/v1/auth/session/clear`

**Auth:** Bearer token required

**Request Body (Optional):**
```json
{
  "all_devices": true
}
```

**Description:** Clears current session or all sessions across devices. Use when user taps logout.

**Response:**
```json
{
  "success": true,
  "message": "All sessions cleared"
}
```

### Endpoint: Validate Token
**URL:** `GET https://<your-backend>/api/v1/auth/token/validate`

**Auth:** Bearer token required

**Response (Valid Token):**
```json
{
  "success": true,
  "message": "Token is valid",
  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "role": "PASSENGER"
    }
  }
}
```

**Response (Invalid/Expired Token - 401):**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

---

## 4. Profile APIs

### Endpoint: Get Profile
**URL:** `GET https://<your-backend>/api/v1/auth/profile`

**Auth:** Bearer token required

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "John Doe",
    "email": "john.passenger@example.com",
    "phone": "+250788000222",
    "role": "PASSENGER",
    "is_approved": true,
    "profile_photo": null
  }
}
```

### Endpoint: Update Profile
**URL:** `PUT https://<your-backend>/api/v1/auth/profile`

**Auth:** Bearer token required

**Request Body:**
```json
{
  "name": "John Updated",
  "phone": "+250788000333",
  "profile_photo": "base64_encoded_image_data"
}
```

---

## 5. Passenger-Specific APIs

### Endpoint: Get Passenger Profile
**URL:** `GET https://<your-backend>/api/v1/passenger/profile`

**Auth:** Bearer token required

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "John Doe",
    "email": "john.passenger@example.com",
    "phone": "+250788000222",
    "total_rides": 15,
    "rating": 4.8,
    "profile_photo": null
  }
}
```

### Endpoint: Get Available Corridors (Public Transport Routes)
**URL:** `GET https://<your-backend>/api/v1/passenger/public-transport/corridors`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "A",
      "name": "Corridor A",
      "routes_count": 7
    },
    {
      "id": 2,
      "code": "B",
      "name": "Corridor B",
      "routes_count": 11
    }
  ]
}
```

### Endpoint: Get Routes by Corridor
**URL:** `GET https://<your-backend>/api/v1/passenger/public-transport/routes?corridor_id=1`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "route_code": "102",
      "name": "KABUGA - MURINDI - NYABUGOGO",
      "origin": "KABUGA",
      "destination": "NYABUGOGO",
      "corridor_id": 1
    }
  ]
}
```

### Endpoint: Get Available Rides
**URL:** `GET https://<your-backend>/api/v1/passenger/rides/available?route_id=1`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "bus_number": "102",
      "departure_time": "2026-05-04 08:00:00",
      "arrival_time_estimated": "2026-05-04 09:00:00",
      "available_seats": 20,
      "price_per_seat": 200,
      "currency": "RWF",
      "status": "available"
    }
  ]
}
```

---

## 6. Flutter Integration - Code Example (Dart)

### Passenger Registration
```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class PassengerAuthService {
  final String baseUrl = 'https://your-backend.com/api/v1';

  Future<Map<String, dynamic>> registerPassenger({
    required String fullName,
    required String email,
    required String phoneNumber,
    required String password,
    required String passwordConfirmation,
  }) async {
    final url = Uri.parse('$baseUrl/auth/register/passenger');
    
    final response = await http.post(
      url,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'full_name': fullName,
        'email': email,
        'phone_number': phoneNumber,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    if (response.statusCode == 201) {
      return jsonDecode(response.body);
    } else if (response.statusCode == 422) {
      throw Exception('Validation Error: ${response.body}');
    } else {
      throw Exception('Registration failed: ${response.body}');
    }
  }

  Future<Map<String, dynamic>> loginPassenger({
    required String login, // email or phone
    required String password,
    String deviceName = 'flutter-mobile',
  }) async {
    final url = Uri.parse('$baseUrl/auth/mobile/login');
    
    final response = await http.post(
      url,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'login': login,
        'password': password,
        'device_name': deviceName,
      }),
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else if (response.statusCode == 403) {
      throw Exception('Account pending approval');
    } else if (response.statusCode == 401) {
      throw Exception('Invalid credentials');
    } else {
      throw Exception('Login failed: ${response.body}');
    }
  }

  Future<void> logout(String token) async {
    final url = Uri.parse('$baseUrl/auth/logout');
    
    await http.post(
      url,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );
  }
}
```

---

## 7. Input Validation - Short Notes for Mobile

### Registration Form Inputs

| Input | Label | Rules | Example | Error Message |
|-------|-------|-------|---------|----------------|
| **Full Name** | "Full Name" | Required, 2-255 chars, text only | "John Doe" | "Please enter your full name" |
| **Email** | "Email Address" | Required, valid email, unique | "john@example.com" | "Please enter a valid email" |
| **Phone** | "Phone Number" | Required, international format, max 20 chars | "+250788000222" | "Please enter a valid phone number" |
| **Password** | "Password" | Min 8 chars, uppercase + lowercase + number + special char | "SecurePass@123" | "Password must contain uppercase, lowercase, number, and special character" |
| **Confirm Password** | "Confirm Password" | Must match password field | "SecurePass@123" | "Passwords do not match" |

### Login Form Inputs

| Input | Label | Rules | Example | Error Message |
|-------|-------|-------|---------|----------------|
| **Login** | "Email or Phone" | Required, valid email OR phone | "john@example.com" or "+250788000222" | "Enter email or phone number" |
| **Password** | "Password" | Required, min 8 chars | "SecurePass@123" | "Password is required" |

---

## 8. Security Best Practices for Flutter

1. **Store Token Securely**
   - Use Flutter Secure Storage package
   - Never store token in SharedPreferences (unencrypted)
   ```dart
   final storage = FlutterSecureStorage();
   await storage.write(key: 'auth_token', value: token);
   ```

2. **Use HTTPS Only**
   - All API calls must use `https://` not `http://`
   - Validate SSL certificates

3. **Handle Token Expiration**
   - Check token on app launch
   - Redirect to login if token invalid
   - Implement token refresh flow

4. **Password Requirements**
   - Enforce on client-side for UX
   - Re-validate on server-side always
   - Min 8 characters, complexity required

5. **Error Handling**
   - Never expose internal server errors to user
   - Show generic "Registration failed" message
   - Log errors for debugging only

---

## 9. Response Status Codes Reference

| Code | Status | Meaning |
|------|--------|---------|
| **200** | OK | Successful request (login, profile, etc.) |
| **201** | Created | Resource created (registration successful) |
| **400** | Bad Request | Invalid request format or missing fields |
| **401** | Unauthorized | Invalid credentials or expired token |
| **403** | Forbidden | Account not approved (pending admin approval) |
| **404** | Not Found | Resource not found |
| **422** | Unprocessable Entity | Validation failed |
| **500** | Server Error | Internal server error |

---

## 10. Environment Configuration

### Development
```
Base URL: http://localhost:8000/api/v1
```

### Production
```
Base URL: https://api.rideconnect.rw/api/v1
```

### Staging
```
Base URL: https://staging-api.rideconnect.rw/api/v1
```

---

## 11. Common Implementation Checklist

- [ ] Implement passenger registration form with validation
- [ ] Implement mobile login (email or phone option)
- [ ] Store auth token securely in device storage
- [ ] Implement auto-login on app launch
- [ ] Handle account pending approval response (403)
- [ ] Show appropriate error messages to user
- [ ] Implement logout functionality
- [ ] Add token validation before API calls
- [ ] Implement password confirmation matching
- [ ] Add phone number formatting (international)
- [ ] Test all edge cases and error scenarios
- [ ] Implement retry logic for failed requests
- [ ] Add loading indicators during API calls
- [ ] Log API errors for debugging

---

## 12. Support & Troubleshooting

**Issue:** "Email already registered"
- Solution: Use unique email or login with existing account

**Issue:** "Account pending approval"
- Solution: Admin must approve account; wait for notification

**Issue:** "Invalid credentials"
- Solution: Check email/phone and password; case-sensitive

**Issue:** "Validation failed - Password must contain..."
- Solution: Password must be min 8 chars with uppercase, lowercase, number, special char

**Issue:** "Network timeout"
- Solution: Check internet connection; retry with exponential backoff

---

## 13. Testing Credentials

| Role | Email | Phone | Password |
|------|-------|-------|----------|
| Passenger | test.passenger@example.com | +250788000222 | TestPass@123 |
| Admin | admin@example.com | +250788000111 | AdminPass@123 |

---

*Last Updated: May 4, 2026*
*API Version: v1*
*Framework: Laravel 11*
