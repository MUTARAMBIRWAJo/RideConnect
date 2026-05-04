# Flutter Passenger App - API Quick Reference Card

## Base URL
```
Production: https://api.rideconnect.rw/api/v1
Development: http://localhost:8000/api/v1
```

---

## 1️⃣ REGISTRATION

### Endpoint: Register Passenger
```
POST /auth/register/passenger
Content-Type: application/json
```

### Request
```json
{
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone_number": "+250788000222",
  "password": "SecurePass@123",
  "password_confirmation": "SecurePass@123"
}
```

### Success Response (201)
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "PASSENGER",
      "phone": "+250788000222",
      "is_approved": false
    }
  }
}
```

### Error Response (422)
```json
{
  "success": false,
  "errors": {
    "email": ["Email already exists"]
  }
}
```

---

## 2️⃣ LOGIN

### Endpoint: Mobile Login (Recommended)
```
POST /auth/mobile/login
Content-Type: application/json
```

### Request
```json
{
  "login": "+250788000222",
  "password": "SecurePass@123",
  "device_name": "flutter-mobile"
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 42,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "PASSENGER",
      "phone": "+250788000222"
    },
    "token": "1|Y5Z8X9W7V6U5T4S3R2Q1P0...",
    "token_type": "Bearer"
  }
}
```

### Error Responses
```json
// Invalid credentials (401)
{
  "success": false,
  "message": "Invalid credentials"
}

// Account not approved (403)
{
  "success": false,
  "message": "Your account is pending approval"
}
```

---

## 3️⃣ SESSION MANAGEMENT

### Logout
```
POST /auth/logout
Authorization: Bearer <token>
```

### Clear Session
```
POST /auth/session/clear
Authorization: Bearer <token>
Content-Type: application/json

{
  "all_devices": true
}
```

### Validate Token
```
GET /auth/token/validate
Authorization: Bearer <token>
```

---

## 4️⃣ PROFILE APIs

### Get Profile
```
GET /auth/profile
Authorization: Bearer <token>
```

### Update Profile
```
PUT /auth/profile
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "John Updated",
  "phone": "+250788000333"
}
```

### Get Passenger Profile
```
GET /passenger/profile
Authorization: Bearer <token>
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+250788000222",
    "total_rides": 15,
    "rating": 4.8
  }
}
```

---

## 5️⃣ PUBLIC TRANSPORT - CORRIDORS & ROUTES

### Get Corridors (No Auth)
```
GET /passenger/public-transport/corridors
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "A",
      "name": "Corridor A",
      "routes_count": 7
    }
  ]
}
```

### Get Routes
```
GET /passenger/public-transport/routes?corridor_id=1
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "route_code": "102",
      "name": "KABUGA - NYABUGOGO",
      "origin": "KABUGA",
      "destination": "NYABUGOGO",
      "corridor_id": 1
    }
  ]
}
```

---

## 6️⃣ RIDES & BOOKINGS

### Get Available Rides
```
GET /passenger/rides/available?route_id=1
Authorization: Bearer <token>
```

### Book Ride (ON_DEMAND)
```
POST /passenger/rides
Authorization: Bearer <token>
Content-Type: application/json

{
  "ride_id": 1,
  "pickup_location": "Kigali Downtown",
  "dropoff_location": "Kigali Airport"
}
```

### Get Ride History
```
GET /passenger/rides/history
Authorization: Bearer <token>
```

### Create Booking (SCHEDULED)
```
POST /passenger/bookings
Authorization: Bearer <token>
Content-Type: application/json

{
  "ride_id": 1,
  "pickup_location": "KABUGA",
  "dropoff_location": "NYABUGOGO",
  "seat_count": 1
}
```

### Convert Booking to Trip
```
POST /passenger/trips/create-from-booking
Authorization: Bearer <token>
Content-Type: application/json

{
  "booking_id": 1
}
```

---

## 🔑 INPUT VALIDATION RULES

| Field | Type | Min | Max | Required | Rules |
|-------|------|-----|-----|----------|-------|
| Full Name | Text | 2 | 255 | ✓ | Letters & spaces only |
| Email | Email | - | 255 | ✓ | Valid email, unique |
| Phone | Phone | 8 | 20 | ✓ | E.164 format (+...) |
| Password | Text | 8 | - | ✓ | Uppercase + lowercase + number + special |
| Confirm Password | Text | 8 | - | ✓ | Must match password |
| Login | Email/Phone | - | - | ✓ | Valid email or phone |

---

## ✅ PHONE NUMBER FORMATTING

### Accepted Formats
- `+250788000222` ✓
- `0788000222` → `+250788000222` ✓
- `00250788000222` → `+250788000222` ✓
- `+250 788 000 222` → `+250788000222` ✓

### Validation
- Must start with `+` after formatting
- Rwanda: `+250` + 9 digits
- Total: 13 characters

### Display Format
- Input: `+250788000222`
- Display: `+250 (78) 800-0222`

---

## 🔐 HTTP HEADERS

### Without Authentication
```
Content-Type: application/json
Accept: application/json
```

### With Authentication
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>
```

---

## 📊 HTTP STATUS CODES

| Code | Meaning | Action |
|------|---------|--------|
| 200 | OK | Success |
| 201 | Created | Registration success |
| 400 | Bad Request | Check request format |
| 401 | Unauthorized | Invalid token/credentials |
| 403 | Forbidden | Account not approved |
| 404 | Not Found | Resource not found |
| 422 | Validation Error | Check field validation |
| 500 | Server Error | Retry later |

---

## 💾 TOKEN STORAGE (Flutter)

```dart
// Store securely
final storage = FlutterSecureStorage();
await storage.write(key: 'auth_token', value: token);

// Retrieve
final token = await storage.read(key: 'auth_token');

// Delete
await storage.delete(key: 'auth_token');

// Never use SharedPreferences (unencrypted!)
// ❌ DO NOT: SharedPreferences.getInstance().setString('token', token);
```

---

## 🚀 COMPLETE REGISTRATION FLOW

```
1. User fills registration form
   ↓
2. Validate all inputs on client
   - Full name (required, 2-255 chars)
   - Email (required, valid format)
   - Phone (required, E.164 format)
   - Password (required, complexity check)
   - Confirm Password (required, must match)
   ↓
3. If validation passes, POST to /auth/register/passenger
   ↓
4. Show loading indicator
   ↓
5a. SUCCESS (201):
   - Show confirmation message
   - Navigate to login screen
   
5b. VALIDATION ERROR (422):
   - Display field-level errors
   - User fixes fields
   - Return to step 2
   
5c. SERVER ERROR (500):
   - Show error message
   - Offer retry button
   ↓
6. User navigates to login
```

---

## 🔓 COMPLETE LOGIN FLOW

```
1. User fills login form
   - Email or Phone
   - Password
   ↓
2. Validate inputs on client
   - Login: must be valid email OR phone
   - Password: required
   ↓
3. Format phone to E.164 if needed
   ↓
4. POST to /auth/mobile/login
   ↓
5. Show loading indicator
   ↓
6a. SUCCESS (200):
   - Store token in secure storage
   - Navigate to home screen
   
6b. INVALID CREDENTIALS (401):
   - Show error message
   - Clear password field
   - Focus login field
   
6c. ACCOUNT PENDING (403):
   - Show "awaiting approval" dialog
   - Don't close app
   
6d. NETWORK ERROR:
   - Show retry button
   - Don't clear fields
```

---

## ⚙️ RECOMMENDED DART PACKAGES

```yaml
# HTTP requests
http: ^1.1.0

# Secure token storage
flutter_secure_storage: ^9.0.0

# Phone formatting
intl_phone_number_input: ^0.7.0

# State management (optional)
provider: ^6.0.0

# Navigation (optional)
go_router: ^12.0.0

# Local database (optional)
sqflite: ^2.3.0
```

---

## 🧪 TEST CREDENTIALS

| Role | Email | Phone | Password |
|------|-------|-------|----------|
| Passenger | test.passenger@example.com | +250788000222 | TestPass@123 |

---

## ❌ COMMON ERRORS & SOLUTIONS

| Error | Cause | Solution |
|-------|-------|----------|
| "Email already registered" | Email exists | Use different email or login |
| "Phone must start with +" | Invalid format | Use E.164 format |
| "Password must include uppercase" | Weak password | Add uppercase letter |
| "Passwords do not match" | Mismatch | Ensure both match exactly |
| "Account pending approval" | Not approved yet | Wait for admin approval |
| "Invalid credentials" | Wrong email/phone/password | Check input again |
| "Network error" | No internet | Check connection and retry |
| "Validation failed" | Missing/invalid field | Check all required fields |

---

## 📱 FLUTTER INTEGRATION CHECKLIST

- [ ] Setup HTTP client with base URL configuration
- [ ] Implement AuthService with register, login, logout methods
- [ ] Create Validators for all input fields
- [ ] Implement phone formatter for E.164 conversion
- [ ] Create RegistrationScreen with form validation
- [ ] Create LoginScreen with email/phone option
- [ ] Implement token secure storage
- [ ] Add auto-login on app startup
- [ ] Handle account approval pending response
- [ ] Implement error dialogs
- [ ] Add loading indicators
- [ ] Test all flows (success, validation error, network error)
- [ ] Implement password strength meter
- [ ] Setup deep linking for password reset
- [ ] Add retry logic with exponential backoff

---

## 🔗 DOCUMENTATION REFERENCES

- Complete API Guide: `FLUTTER_PASSENGER_REGISTRATION.md`
- Input Notes: `PASSENGER_REGISTRATION_INPUT_NOTES.md`
- Full Implementation: `FLUTTER_PASSENGER_APP_IMPLEMENTATION.md`
- Mobile Auth API: `MOBILE_AUTH_API.md`

---

**Version:** 1.0  
**Last Updated:** May 4, 2026  
**Status:** Production Ready
