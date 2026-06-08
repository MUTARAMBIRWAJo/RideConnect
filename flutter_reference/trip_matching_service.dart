// REFERENCE FILE for the Flutter app.
// Drop into: lib/features/trips/services/trip_matching_service.dart
//
// Polls GET /passenger/motor-vehicle/trip-requests/{id} on an exponential
// backoff and emits TripLifecyclePhase transitions until a terminal state.
//
// Depends on:
//   - PassengerApi.getMotorVehicleTrip(int tripId)  -> Map<String,dynamic> (the `data` object)
//   - MotorVehicleTripStatus (motor_vehicle_trip_status.dart)
//
// Framework-agnostic: exposes a broadcast Stream<MotorVehicleTripStatus>. Wrap it
// in whatever the project already uses (Riverpod StreamProvider / Bloc / setState).

import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../services/passenger_api.dart';
import '../models/motor_vehicle_trip_status.dart';

class TripMatchingService {
  TripMatchingService({PassengerApi? api}) : _api = api ?? PassengerApi.instance;

  final PassengerApi _api;

  // Backoff schedule (ms): 2s, 3s, 4.5s, 6.7s, then capped at 8s.
  static const int _minIntervalMs = 2000;
  static const int _maxIntervalMs = 8000;
  static const double _factor = 1.5;

  // Stop searching after this long with no driver (client-side safety net).
  static const Duration _searchTimeout = Duration(seconds: 120);

  final _controller = StreamController<MotorVehicleTripStatus>.broadcast();
  Stream<MotorVehicleTripStatus> get stream => _controller.stream;

  Timer? _timer;
  int _intervalMs = _minIntervalMs;
  bool _inFlight = false; // prevents overlapping requests
  bool _paused = false;
  bool _stopped = false;
  DateTime? _startedAt;
  TripLifecyclePhase? _lastPhase;
  int? _tripId;

  MotorVehicleTripStatus? latest;

  /// Begin polling immediately (call right after the trip is created).
  void startPolling(int tripId) {
    stop(); // reset any prior session
    _tripId = tripId;
    _stopped = false;
    _paused = false;
    _intervalMs = _minIntervalMs;
    _startedAt = DateTime.now();
    _lastPhase = null;
    _tick(); // fire first poll right away
  }

  /// Pause when the app is backgrounded.
  void pause() {
    _paused = true;
    _timer?.cancel();
    _timer = null;
  }

  /// Resume when the app is foregrounded.
  void resume() {
    if (_stopped || _tripId == null || !_paused) return;
    _paused = false;
    _intervalMs = _minIntervalMs; // re-sync quickly
    _tick();
  }

  void stop() {
    _stopped = true;
    _timer?.cancel();
    _timer = null;
    _tripId = null;
  }

  Future<void> dispose() async {
    stop();
    await _controller.close();
  }

  Future<void> _tick() async {
    if (_stopped || _paused || _tripId == null) return;
    if (_inFlight) {
      _scheduleNext();
      return;
    }

    // Client-side timeout while still searching.
    if (_lastPhase == TripLifecyclePhase.searching &&
        _startedAt != null &&
        DateTime.now().difference(_startedAt!) > _searchTimeout) {
      _emitSynthetic(TripLifecyclePhase.expired);
      stop();
      return;
    }

    _inFlight = true;
    try {
      final data = await _api.getMotorVehicleTrip(_tripId!);
      final status = MotorVehicleTripStatus.fromJson(data);
      latest = status;

      // Reset backoff whenever the phase changes; otherwise grow it.
      if (status.phase != _lastPhase) {
        _intervalMs = _minIntervalMs;
        _lastPhase = status.phase;
      } else {
        _growInterval();
      }

      if (kDebugMode) {
        debugPrint('[TripMatchingService] poll trip=${status.tripId} '
            'status=${status.status} matching=${status.matchingStatus} '
            'phase=${status.phase}');
      }

      if (!_controller.isClosed) _controller.add(status);

      if (status.phase.isTerminal) {
        stop();
        return;
      }
    } catch (e) {
      // Network/backend error: keep last known state, keep polling with backoff.
      if (kDebugMode) debugPrint('[TripMatchingService] poll error: $e');
      _growInterval();
    } finally {
      _inFlight = false;
    }

    _scheduleNext();
  }

  void _scheduleNext() {
    if (_stopped || _paused) return;
    _timer?.cancel();
    _timer = Timer(Duration(milliseconds: _intervalMs), _tick);
  }

  void _growInterval() {
    _intervalMs = (_intervalMs * _factor).round();
    if (_intervalMs > _maxIntervalMs) _intervalMs = _maxIntervalMs;
  }

  void _emitSynthetic(TripLifecyclePhase phase) {
    if (_controller.isClosed || _tripId == null) return;
    _controller.add(MotorVehicleTripStatus(
      tripId: _tripId!,
      status: 'EXPIRED',
      matchingStatus: 'FAILED_MAX_RETRIES',
      phase: phase,
    ));
  }
}
