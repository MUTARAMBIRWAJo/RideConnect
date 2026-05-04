# RideConnect Mobile App - Development Guide

**Platform:** Flutter (iOS & Android)  
**Backend:** Laravel with Supabase Realtime  
**Version:** 2.0  
**Last Updated:** May 2026

---

## Quick Navigation

- **[Complete API Reference](MOBILE_APP_COMPLETE_API_REFERENCE.md)** - All endpoints for Passengers and Drivers
- **[Design & Flows](MOBILE_APP_DESIGN_FLOWS.md)** - UI screens, navigation, and user flows  
- **[UI Implementation](MOBILE_APP_UI_IMPLEMENTATION.md)** - Flutter code examples and widgets

---

## Project Overview

RideConnect is a comprehensive ride-sharing platform with support for:

- **Passengers:** Browse rides, book scheduled trips, request on-demand rides, track drivers, rate experiences
- **Drivers:** Manage availability, accept trip requests, execute trips, track earnings, withdraw funds
- **Transport Types:** Cars, Motorcycles, Buses
- **Travel Modes:** Scheduled (advance booking) and On-Demand (immediate requests)
- **Realtime Features:** Live location tracking, instant notifications, real-time trip updates

---

## Key Functionalities

### Passenger App

#### 1. Authentication & Onboarding
- Email/phone registration
- Social login (Google, Apple, Facebook)
- Email/SMS verification
- Profile completion

#### 2. Ride Discovery
- Search available rides by location and date
- Filter by transport type, price, driver rating
- View detailed ride information
- See driver profile and vehicle details

#### 3. Booking Management (Scheduled)
- Book seats on scheduled rides
- Specify custom pickup/dropoff locations
- Add special requests
- Manage multiple bookings
- Cancel with reason tracking

#### 4. Trip Management (On-Demand)
- Request immediate trips with custom locations
- Real-time driver location tracking
- Live ETA to pickup and destination
- Driver-to-passenger communication
- One-tap cancellation with feedback

#### 5. Payment & Wallet
- Multiple payment methods (Mobile Money, Card, Cash, Wallet)
- Secure payment processing
- Payment history and receipts
- Wallet top-up and balance management
- Automatic refunds on cancellation

#### 6. Support & Feedback
- Create support tickets with attachments
- Rate trips by category (cleanliness, safety, communication, driving)
- View trip history and statistics
- Access support chat

#### 7. Safety & Verification
- ID verification for new users
- Emergency contact management
- Trip receipt sharing
- Driver background checks visibility

### Driver App

#### 1. Registration & Verification
- Driver registration with license information
- Vehicle details and photos
- Document upload (license, national ID, insurance, registration)
- Background check initiation
- Bank account setup for earnings

#### 2. Availability & Status
- Toggle online/offline status
- Real-time location sharing
- Automatic availability radius configuration
- Night mode and busy status

#### 3. Trip Management
- Incoming trip request notifications (with 30-second response window)
- Accept/reject with reasoning
- Navigate to pickup location with turn-by-turn directions
- Wait timers with auto-cancel after 5 minutes
- Live trip navigation with passenger tracking
- Trip completion with actual distance/fare adjustments
- Trip cancellation with penalty assessment

#### 4. Earnings & Analytics
- Real-time earnings dashboard
- Daily/weekly/monthly earnings breakdown
- Trip statistics and completion rate
- Earnings by trip type
- Fuel cost estimation
- Performance metrics (rating, acceptance rate, cancellation rate)

#### 5. Withdrawal System
- Multiple withdrawal methods (Bank, Mobile Money, Crypto)
- Instant/scheduled withdrawals
- Transaction history and tracking
- Withdrawal limits and verification

#### 6. Document Management
- Upload and track document status
- Expiry date alerts
- Automatic re-upload reminders
- Verification history

#### 7. Performance & Rating
- Real-time rating updates from passengers
- Driver profile statistics
- Acceptance/cancellation rates
- Performance leaderboards

---

## API Endpoints Summary

### Authentication
- `POST /auth/register` - User registration
- `POST /auth/login` - User login
- `POST /auth/logout` - User logout
- `POST /auth/refresh` - Refresh token
- `POST /auth/verify-email` - Email verification
- `POST /auth/mfa/enable` - Enable multi-factor authentication

### Passenger API
- `GET /user/profile` - Get profile
- `PUT /user/profile` - Update profile
- `GET /mobile/rides` - Search available rides
- `GET /mobile/rides/{id}` - Get ride details
- `POST /mobile/bookings` - Create booking
- `GET /mobile/bookings` - Get my bookings
- `PUT /mobile/bookings/{id}/confirm` - Confirm booking
- `PUT /mobile/bookings/{id}/cancel` - Cancel booking
- `POST /mobile/trips/request` - Request on-demand trip
- `GET /mobile/trips` - Get my trips
- `GET /mobile/trips/{id}` - Get trip details
- `GET /mobile/trips/{id}/track` - Track trip
- `PUT /mobile/trips/{id}/cancel` - Cancel trip
- `POST /mobile/trips/{id}/rate` - Rate trip
- `POST /mobile/payments` - Create payment
- `GET /mobile/payments/history` - Payment history
- `POST /mobile/support/tickets` - Create support ticket

### Driver API
- `GET /user/profile` - Get profile
- `PUT /user/profile` - Update profile
- `POST /mobile/drivers/status` - Update online status
- `GET /mobile/drivers/status` - Get current status
- `GET /mobile/drivers/trips` - Get available trip requests
- `POST /mobile/drivers/trips/{id}/accept` - Accept trip request
- `PUT /mobile/drivers/trips/{id}/reject` - Reject trip request
- `GET /mobile/drivers/trips/active` - Get active trips
- `GET /mobile/drivers/trips/{id}` - Get trip details
- `PUT /mobile/drivers/trips/{id}/start` - Start trip
- `POST /mobile/drivers/location` - Update location
- `PUT /mobile/drivers/trips/{id}/complete` - Complete trip
- `PUT /mobile/drivers/trips/{id}/cancel` - Cancel trip
- `GET /mobile/drivers/trips/history` - Trip history
- `GET /mobile/drivers/earnings` - Get earnings summary
- `GET /mobile/drivers/earnings/monthly` - Monthly earnings breakdown
- `POST /mobile/drivers/earnings/withdraw` - Withdraw earnings
- `POST /mobile/drivers/documents` - Upload document
- `GET /mobile/drivers/documents` - Get documents

---

## Data Models

### Trip
```json
{
  "id": 1001,
  "passenger_id": 1,
  "driver_id": 2,
  "booking_id": null,
  "ride_id": null,
  "pickup_location": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_location": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "fare": 2500,
  "actual_fare": 2500,
  "status": "PENDING|ACCEPTED|STARTED|COMPLETED|CANCELLED",
  "requested_at": "2026-05-04T14:30:00Z",
  "accepted_at": "2026-05-04T14:31:00Z",
  "started_at": "2026-05-04T14:32:00Z",
  "completed_at": "2026-05-04T14:45:00Z"
}
```

### Booking
```json
{
  "id": 501,
  "user_id": 1,
  "ride_id": 101,
  "seats_booked": 2,
  "total_price": 5000,
  "currency": "RWF",
  "status": "PENDING|CONFIRMED|CANCELLED|COMPLETED|NO_SHOW",
  "pickup_address": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_address": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "special_requests": "Please wait at main entrance",
  "confirmation_code": "RIDE-101-5001"
}
```

### Ride
```json
{
  "id": 101,
  "driver_id": 2,
  "vehicle_id": 5,
  "origin_address": "Kimihurura Roundabout, Kigali",
  "origin_lat": -1.9536,
  "origin_lng": 30.0606,
  "destination_address": "Kigali City Tower, Kigali",
  "destination_lat": -1.9441,
  "destination_lng": 30.0619,
  "departure_time": "2026-05-04T14:30:00Z",
  "distance_km": 2.5,
  "base_fare": 1500,
  "price_per_km": 400,
  "price_per_seat": 2500,
  "available_seats": 3,
  "transport_type": "CAR|MOTORCYCLE|BUS",
  "travel_mode": "SCHEDULED|ON_DEMAND",
  "status": "DRAFT|PUBLISHED|IN_PROGRESS|COMPLETED|CANCELLED"
}
```

---

## Realtime Features

### Supabase Channels

The app uses Supabase Realtime for live updates on three channel types:

#### Trip Channel: `trip:{trip_id}`
Used for trip lifecycle and location updates:

- **Event:** `driver.location.updated`
  ```json
  {
    "lat": -1.9540,
    "lng": 30.0610,
    "accuracy": 15,
    "heading": 45,
    "speed_kmh": 42,
    "timestamp": "2026-05-04T14:32:00Z"
  }
  ```

- **Event:** `trip.started`
  ```json
  {
    "trip_id": 1001,
    "started_at": "2026-05-04T14:32:00Z"
  }
  ```

- **Event:** `trip.completed`
  ```json
  {
    "trip_id": 1001,
    "actual_fare": 2500,
    "completed_at": "2026-05-04T14:45:00Z"
  }
  ```

#### Driver Channel: `driver:{driver_id}`
Used for driver-specific notifications:

- **Event:** `trip.request`
  ```json
  {
    "trip_id": 1001,
    "passenger_id": 1,
    "pickup": "Kimihurura Roundabout",
    "dropoff": "Kigali City Tower",
    "estimated_fare": 2500,
    "expires_at": "2026-05-04T14:35:00Z"
  }
  ```

#### Passenger Channel: `passenger:{passenger_id}`
Used for passenger-specific updates:

- **Event:** `driver.accepted`
  - Driver has accepted the trip request

- **Event:** `driver.arrived`
  - Driver has arrived at pickup location

- **Event:** `trip.cancelled`
  - Trip has been cancelled with reason

---

## Development Setup

### Prerequisites
- Flutter 3.10+
- Dart 3.0+
- Xcode 14+ (for iOS)
- Android Studio (for Android)
- Supabase account

### Dependencies

```yaml
dependencies:
  # Core
  flutter: sdk: flutter
  supabase_flutter: ^1.10.0
  get_it: ^7.6.0
  
  # State Management
  flutter_bloc: ^8.1.0
  bloc: ^8.1.0
  equatable: ^2.0.0
  
  # Networking
  dio: ^5.0.0
  
  # Storage
  flutter_secure_storage: ^9.0.0
  shared_preferences: ^2.2.0
  
  # Maps & Location
  google_maps_flutter: ^2.5.0
  geolocator: ^9.0.0
  
  # UI
  flutter_svg: ^2.0.0
  shimmer: ^3.0.0
  
  # Utilities
  intl: ^0.18.0
  connectivity_plus: ^5.0.0
  permission_handler: ^11.0.0

dev_dependencies:
  flutter_test: sdk: flutter
  mockito: ^5.4.0
  bloc_test: ^9.1.0
```

### Installation & Running

```bash
# Clone repository
git clone https://github.com/rideconnect/mobile.git
cd mobile

# Install dependencies
flutter pub get

# Run code generation
flutter pub run build_runner build

# Run on device/emulator
flutter run
```

---

## Navigation Architecture

### Passenger Navigation
```
Splash Screen
    ↓
Login/Register
    ↓
Home Tab (Default)
├── Home
├── Rides (Browse & Search)
├── Bookings (Scheduled Rides)
└── Profile
    ├── Settings
    ├── Payment Methods
    ├── Support
    └── Logout

Trip Flow:
├── Ride Details → Booking → Payment → Confirmation
└── Trip Tracking → Rating → Receipt
```

### Driver Navigation
```
Splash Screen
    ↓
Login/Register (Driver)
    ↓
Documents & Verification
    ↓
Dashboard (Main)
├── Dashboard (Home)
├── Trip Requests
├── Active Trips
├── Earnings
├── Documents
├── Profile
    ├── Vehicle Info
    ├── Bank Account
    ├── Settings
    └── Logout
```

---

## Error Handling Strategy

### Common Errors & Solutions

| Error | HTTP | Handling |
|-------|------|----------|
| Invalid Credentials | 401 | Redirect to login |
| Account Not Approved | 403 | Show approval pending dialog |
| Ride Not Found | 404 | Show empty state |
| Insufficient Seats | 422 | Enable seat reducer |
| Payment Failed | 402 | Retry or try different method |
| No Drivers Available | 503 | Show retry button |
| Network Error | - | Show offline indicator |
| Server Error | 500 | Show error with retry |

### Offline Support
- Cache ride listings and recent trips
- Queue API calls while offline
- Show cached data with offline indicator
- Sync when connection restored

---

## Performance Optimization

### Image Handling
- Use image caching with proper sizing
- Lazy load images in lists
- Compress images before upload
- Use placeholders for missing images

### List Performance
- Implement pagination (20 items per page)
- Use ListView.builder instead of ListView
- Add repaint boundaries for complex widgets
- Optimize rebuild frequency

### Network Optimization
- Bundle multiple requests when possible
- Cancel requests on page close
- Implement connection monitoring
- Batch location updates (max 1 per second)

### Memory Management
- Dispose resources properly (controllers, subscriptions)
- Use WeakReferences for large data
- Monitor memory leaks with DevTools
- Profile with Flutter Performance Tools

---

## Security Considerations

### Authentication
- Store tokens in Secure Storage, not SharedPreferences
- Implement token refresh before expiry
- Clear tokens on logout
- Validate JWT before accepting

### Data Protection
- Encrypt sensitive data in storage
- Use HTTPS for all API calls
- Validate SSL certificates
- Implement certificate pinning

### Input Validation
- Validate all user inputs before sending
- Sanitize strings to prevent injection
- Check location permissions
- Verify phone numbers and emails

### API Security
- Add rate limiting
- Implement CORS restrictions
- Use API keys securely
- Monitor suspicious activities

---

## Testing Strategy

### Unit Tests
```dart
// Example: RideRepository test
void main() {
  group('RideRepository', () {
    test('searchRides returns list of rides', () async {
      final repository = RideRepository(mockApiService);
      
      when(mockApiService.searchRides(any))
          .thenAnswer((_) async => [mockRide1, mockRide2]);
      
      final result = await repository.searchRides(mockFilter);
      
      expect(result, [mockRide1, mockRide2]);
      verify(mockApiService.searchRides(mockFilter)).called(1);
    });
  });
}
```

### Widget Tests
- Test individual widgets in isolation
- Mock dependencies
- Verify UI rendering
- Check user interactions

### Integration Tests
- Test complete user flows
- Run on real device
- Test realtime features
- Verify payment flows

---

## Deployment

### Release Build
```bash
# Android
flutter build apk --release
flutter build app-bundle --release

# iOS
flutter build ipa --release
```

### App Store Deployment
- **iOS:** Follow Apple App Store guidelines
- **Android:** Follow Google Play Store guidelines
- **Staging:** Deploy to beta channels first
- **Monitoring:** Track crashes and performance

### Version Management
- Semantic versioning (Major.Minor.Patch)
- Changelog per release
- API version compatibility checks
- Forced updates for critical fixes

---

## Monitoring & Analytics

### Event Tracking
- User signup/login flows
- Trip request creation
- Payment completions
- Support ticket creation
- Driver acceptance rate

### Performance Metrics
- App startup time
- Screen load times
- API response times
- Crash rates
- Network errors

### Business Metrics
- Daily active users
- Bookings per user
- Payment success rate
- Driver utilization
- Customer satisfaction

---

## Support & Maintenance

### Known Issues
- None currently documented

### Planned Features (v3.0)
- Scheduled trip reminders
- Carpooling feature
- Driver referral program
- Advanced analytics dashboard
- Multi-language support

### Contact & Support
- Email: mobile-support@rideconnect.rw
- Slack: #mobile-development
- Issue Tracker: GitHub Issues

---

## Document Index

1. **[Complete API Reference](MOBILE_APP_COMPLETE_API_REFERENCE.md)** - 500+ lines
   - All endpoints with request/response examples
   - Error handling and rate limiting
   - Complete data models

2. **[Design & Flows](MOBILE_APP_DESIGN_FLOWS.md)** - 400+ lines
   - Screen wireframes and ASCII mockups
   - Complete user flows (passenger & driver)
   - Design system and components
   - Data display patterns

3. **[UI Implementation](MOBILE_APP_UI_IMPLEMENTATION.md)** - 600+ lines
   - Flutter project structure
   - Screen implementations with code
   - Widget library examples
   - State management (BLoC)
   - Realtime integration examples
   - API service implementation

---

**Document Version:** 2.0  
**Last Updated:** May 2026  
**Maintained By:** RideConnect Product & Engineering Teams

For questions or clarifications, refer to the specific documentation files or contact the development team.
