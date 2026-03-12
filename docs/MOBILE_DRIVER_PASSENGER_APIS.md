# Mobile Driver + Passenger APIs (Authenticated)

This document lists the APIs for logged-in mobile users to fetch actual data from the database.

Base URL:
- `https://<host>/api/v1`

Authentication:
- Laravel Sanctum bearer token
- Header: `Authorization: Bearer <token>`

## Driver APIs

### Profile and status
- `GET /driver/profile`
- `PUT /driver/profile`
- `GET /driver/stats`
- `PUT /driver/status`

### Driver operations
- `GET /driver/rides`
- `POST /driver/rides`
- `PUT /driver/rides/{id}`
- `DELETE /driver/rides/{id}`

### Requests and trips
- `GET /driver/requests`
- `PUT /driver/requests/{id}/accept`
- `PUT /driver/requests/{id}/reject`
- `PUT /driver/requests/{id}/complete`
- `GET /driver/trips`
- `GET /driver/bookings`

### Earnings and documents
- `GET /driver/earnings`
- `GET /driver/earnings/monthly`
- `POST /driver/documents`
- `GET /driver/documents`

## Passenger APIs

### Profile and summary
- `GET /passenger/profile`
- `PUT /passenger/profile`
- `GET /passenger/stats`

### Ride discovery and booking
- `GET /passenger/rides/available`
- `POST /passenger/rides`
- `GET /passenger/rides`
- `GET /passenger/rides/history`
- `GET /passenger/rides/{id}`
- `PUT /passenger/rides/{id}/cancel`

### Bookings and payments
- `GET /passenger/bookings`
- `GET /passenger/bookings/{id}`
- `POST /passenger/payments`
- `GET /passenger/payments/history`

## Notes

- Driver and passenger role checks are enforced in controllers.
- APIs return JSON with `success`, `message` (where relevant), and `data` fields.
- For paginated endpoints, pagination metadata is included in the response.
