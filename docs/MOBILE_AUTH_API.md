# Mobile Auth API (Flutter Integration)

This document defines required inputs and endpoints for Driver/Passenger registration and login from Flutter clients.

Base URL:
- `https://<host>/api/v1`

## 1. Registration Inputs (External Flutter App)

Required fields for mobile registration:
- `name` (string) OR `full_name` (string alias)
- `email` (valid email)
- `phone` (string) OR `phone_number` (string alias)
- `password` (string)
- `password_confirmation` (string)

Role handling:
- Generic endpoint requires `role` = `DRIVER` or `PASSENGER`.
- Role-specific endpoints infer role automatically.

## 2. Endpoints

### A) Register Driver
`POST /api/v1/auth/register/driver`

Example payload:
```json
{
  "full_name": "John Driver",
  "email": "john.driver@example.com",
  "phone_number": "+250788000111",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

### B) Register Passenger
`POST /api/v1/auth/register/passenger`

Example payload:
```json
{
  "name": "Jane Passenger",
  "email": "jane.passenger@example.com",
  "phone": "+250788000222",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

### C) Register (Generic)
`POST /api/v1/auth/register`

Example payload:
```json
{
  "name": "Generic User",
  "email": "generic@example.com",
  "phone": "+250788000333",
  "role": "DRIVER",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

### D) Mobile Login (Email or Phone)
`POST /api/v1/auth/mobile/login`

Required fields:
- `login` (email or phone)
- `password`

Optional fields:
- `device_name` (default: `flutter-mobile`)

Example payload:
```json
{
  "login": "+250788000111",
  "password": "Password@123",
  "device_name": "flutter-android"
}
```

### E) Legacy Email Login
`POST /api/v1/auth/login`

## 3. Typical Success Response (Login)

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 12,
      "name": "John Driver",
      "email": "john.driver@example.com",
      "role": "DRIVER",
      "phone": "+250788000111",
      "is_approved": true
    },
    "token": "1|...",
    "token_type": "Bearer"
  }
}
```

## 4. Approval Behavior

- New mobile users are created with `is_approved = false`.
- Login returns `403` until approved by admin workflow.

## 5. Flutter Notes

- Use aliases `full_name` and `phone_number` if preferred by your app models.
- Send token in `Authorization: Bearer <token>` for authenticated endpoints.
- Keep passwords and token storage in secure storage (`flutter_secure_storage`).
