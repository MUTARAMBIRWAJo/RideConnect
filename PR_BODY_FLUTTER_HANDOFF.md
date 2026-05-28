Title: Fix location normalization, structured booking/matching errors, and Public Bus selection

Summary
- This branch (`fix/location-normalization`) normalizes client location payloads, enriches public-bus payloads, accepts an optional `bus_route_assignment_id` when booking, and returns structured JSON error codes for booking and driver-matching failures so the mobile app can display friendly, actionable UI instead of generic "Something went wrong" messages.

Backend changes (high level)
- Normalize `pickup`/`dropoff` payloads and accept camelCase / numeric-string coordinates.
- Public bus: return `display_name`, `location`, `available_seats`, `driver` snippets for assignments.
- Booking: accept `bus_route_assignment_id`, validate capacity, and throw domain errors with `error_code` values like `BUS_SELECTION_INVALID`, `INSUFFICIENT_BUS_CAPACITY`, `NO_BOOKABLE_BUS`.
- Controllers now convert Domain/Authorization/Unexpected exceptions into structured JSON with `error_code` so the client can map to friendly messages.

Why this matters for Flutter
- The app previously showed unhelpful generic snackbars because the server returned raw 500s or validation messages. Mobile must map `error_code` -> user-friendly UI and ensure it sends canonical payloads to the server.

Exact client changes requested (copy into Flutter PR)

1) Payload shape: always include top-level numeric coordinates
- Include `pickup_lat`, `pickup_lng`, `dropoff_lat`, `dropoff_lng` as numbers in every request that requires coordinates (rides, matching, bookings). If you also use nested `pickup`/`dropoff` objects, duplicate the numeric top-level fields.
- Coerce numeric strings to floats before sending. Do not send empty strings or nulls for these fields.

2) Permission and location UX
- Ensure location permission is requested and high-accuracy mode used where available.
- Fresh GPS timeout: 5000 ms. If no fresh fix, fall back to last-known location and show a small in-UI note "Using last known location — tap to refresh".
- If permission permanently denied, show modal with deep-link to Settings: "Location required to request rides. Please enable Location Services and allow RideConnect to use your location." Buttons: "Open Settings" / "Retry".

3) Map server `error_code` to friendly messages
- 403 + `PASSENGER_NOT_APPROVED` → "Your account must be approved to book a bus seat" (modal + contact support).
- 403 + `PASSENGER_ONLY` → "Only passengers can book bus seats." (modal).
- 403 + `BUS_BOOKING_FORBIDDEN` → "You are not allowed to book a bus seat." (modal).
- 422 + `BUS_SELECTION_INVALID` → "Selected bus is not available. Please choose another bus." (toast/dialog).
- 422 + `INSUFFICIENT_BUS_CAPACITY` → "Selected bus does not have enough seats. Reduce seats or pick another bus." (dialog).
- 422 + `NO_BOOKABLE_BUS` → "No bus with sufficient capacity is available on this corridor." (dialog).
- 500 + `DRIVER_MATCHING_FAILURE` → "Unable to load drivers. Please retry." (retry UI).
- Fallback: if no `error_code` present, inspect status code and server message and show a generic localized message.

4) Provider lifecycle / build-time side-effects
- Fix provider modification during build by moving side-effects into `initState`, `didChangeDependencies`, or wrap in `WidgetsBinding.instance.addPostFrameCallback((_) => ...)`.
- Example (Riverpod / StatefulWidget): call `ref.read(driverListProvider.notifier).fetch()` from `initState` or a post-frame callback.

5) Booking flow: Public bus specific
- Public buses allow TRIP REQUEST only (select + request), not arbitrary booking flows; ensure UI prevents direct booking when transport type is `bus` and uses trip request flow (server enforces this).
- When user selects a bus in the Available Buses list, include `bus_route_assignment_id` in the request payload for booking/selection flows so the server can validate seat counts.

6) Telemetry
- Log (locally or to your telemetry backend) for failed booking/matching attempts: payload keys present/missing, permission status, location source (fresh | last-known), server error_code.

Code snippets
- HTTP error handling (pseudo-Dart):

```dart
final resp = await http.post(url, body: jsonEncode(payload), headers: headers);
if (resp.statusCode >= 200 && resp.statusCode < 300) {
  // success
} else {
  final body = jsonDecode(resp.body);
  final code = body['error_code'] as String?;
  switch (code) {
    case 'PASSENGER_NOT_APPROVED':
      showModal('Your account must be approved to book a bus seat');
      break;
    case 'INSUFFICIENT_BUS_CAPACITY':
      showDialog('Not enough seats');
      break;
    case 'DRIVER_MATCHING_FAILURE':
      showRetryBanner('Unable to load drivers. Retry', onRetry: () => fetchDrivers());
      break;
    default:
      showSnackBar('We couldn’t complete the request. Please try again.');
  }
}
```

QA checklist for Flutter reviewers
- [ ] Send top-level numeric coords with requests for rides or matching.
- [ ] Map all server `error_code` values above to UI flows.
- [ ] Fix provider side-effect errors (no provider modifications during build).
- [ ] Verify Public Bus flow prevents direct booking and sends `bus_route_assignment_id` when selecting buses.
- [ ] Confirm retry/backoff for driver matching (500 + `DRIVER_MATCHING_FAILURE`).

Notes for backend reviewers
- Tests: I ran the full PHPUnit suite locally; there are a set of test failures and errors (see `phpunit` run output). A common failing area is tests expecting bus rides to be linked to routes; seeders for corridors/assignments may need to be applied in the test setup or factories updated to create route-linked bus rides.

Files changed in this branch (high level)
- `app/Services/PublicBusTransportService.php` — accept `bus_route_assignment_id`, format assignment, return display name.
- `app/Http/Controllers/Api/PassengerPublicBusController.php` — normalized location input and structured errors for booking.
- `app/Http/Controllers/Api/DriverMatchingController.php` — catches throwable and returns `DRIVER_MATCHING_FAILURE` structured JSON.

If you want I can:
- Update the GitHub PR description with this content (requires GH token/CLI), or
- Open a GitHub Issue with this checklist for the mobile team, or
- Leave this file in the branch and you can copy it into the PR body.

---
Generated: May 22, 2026
