#!/usr/bin/env bash
# RideConnect smoke test — run after: docker compose up -d --build
set -e
ML="http://localhost:8001"
LARAVEL="http://localhost:8000"

echo ""
echo "═══ 1. ML Service /health ══════════════════════════════"
HEALTH=$(curl -sf "$ML/health")
echo "$HEALTH" | python3 -m json.tool
echo "$HEALTH" | python3 -c "
import sys, json
d = json.load(sys.stdin)
assert d['status'] == 'ok', 'Health check failed'
assert 'Matching_Modal_tflite' in d['model'], 'Wrong model loaded'
print('  ✓ ML health OK, model:', d['model'])
"

echo ""
echo "═══ 2. ML /rank-drivers ════════════════════════════════"
RANK=$(curl -sf -X POST "$ML/rank-drivers" \
  -H "Content-Type: application/json" \
  -d '{
    "trip_id": 1,
    "transport_type": "moto",
    "pickup_lat": -1.9579,
    "pickup_lng": 30.1127,
    "candidates": [
      {"driver_id":1,"distance_km":0.8,"rating":4.5,"total_rides":120,"acceptance_rate":0.92,"cancellation_rate":0.03},
      {"driver_id":2,"distance_km":1.4,"rating":4.1,"total_rides":55,"acceptance_rate":0.78,"cancellation_rate":0.10},
      {"driver_id":3,"distance_km":2.1,"rating":4.8,"total_rides":300,"acceptance_rate":0.95,"cancellation_rate":0.01}
    ]
  }')
echo "$RANK" | python3 -m json.tool
echo "$RANK" | python3 -c "
import sys, json
d = json.load(sys.stdin)
assert 'ranked_drivers' in d, 'No ranked_drivers in response'
assert len(d['ranked_drivers']) == 3, 'Expected 3 ranked drivers'
assert 'Matching_Modal_tflite' in d['model_version'], 'Wrong model_version'
top = d['ranked_drivers'][0]
print(f'  ✓ Top driver_id={top[\"driver_id\"]} score={top[\"score\"]}')
print(f'  ✓ model_version={d[\"model_version\"]}')
print(f'  ✓ backend={d[\"backend\"]}')
print(f'  ✓ latency_ms={d[\"latency_ms\"]}ms')
"

echo ""
echo "═══ 3. Laravel V2 routes reachable ════════════════════"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$LARAVEL/api/v2/trips" \
  -H "Accept: application/json")
if [ "$STATUS" = "401" ]; then
  echo "  ✓ POST /api/v2/trips → 401 (auth required — route exists)"
elif [ "$STATUS" = "405" ]; then
  echo "  ✓ GET /api/v2/trips → 405 (method not allowed — route exists)"
else
  echo "  ✗ Unexpected status $STATUS for /api/v2/trips"
  exit 1
fi

echo ""
echo "═══ 4. No edge_impulse remnants in PHP ════════════════"
FOUND=$(grep -r "edge_impulse" app/ config/ --include="*.php" 2>/dev/null || true)
if [ -z "$FOUND" ]; then
  echo "  ✓ No edge_impulse references found"
else
  echo "  ✗ Found edge_impulse references:"
  echo "$FOUND"
  exit 1
fi

echo ""
echo "═══ 5. Render smoke test (production) ═════════════════"
echo "  Running against https://ml-service-j72g.onrender.com ..."
RENDER_HEALTH=$(curl -sf --max-time 35 "https://ml-service-j72g.onrender.com/health" || echo "TIMEOUT_OR_SLEEP")
if echo "$RENDER_HEALTH" | grep -q "Matching_Modal_tflite"; then
  echo "  ✓ Render ML service healthy"
  echo "  $RENDER_HEALTH"
else
  echo "  ⚠ Render ML service may be sleeping (cold start). Response:"
  echo "  $RENDER_HEALTH"
  echo "  Wait 40s and retry manually: curl https://ml-service-j72g.onrender.com/health"
fi

echo ""
echo "All local smoke tests passed ✓"
echo "════════════════════════════════════════════════════════"
