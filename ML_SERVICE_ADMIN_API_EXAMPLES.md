# ML Service - Admin API Examples

## Overview

This guide provides practical examples for interacting with the admin API endpoints, including weight management and audit logging.

---

## Authentication

All admin endpoints require the `X-Admin-Token` header. The token is your `APP_KEY` from `.env`:

```bash
# Extract APP_KEY from .env
APP_KEY=$(grep "^APP_KEY=" ../../.env | cut -d= -f2 | sed 's/base64://')
echo $APP_KEY
# Output: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M=
```

---

## 1. Viewing Current Weights

### Request

```bash
curl -X GET "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M=" \
  -H "Content-Type: application/json"
```

### Response

```json
{
  "distance": 0.35,
  "rating": 0.2,
  "acceptance": 0.15,
  "cancellation": 0.1,
  "behavior": 0.1,
  "direction": 0.1
}
```

### In Code (Python)

```python
import httpx
import json

async def get_weights():
    async with httpx.AsyncClient() as client:
        response = await client.get(
            "http://localhost:8000/api/admin/weights",
            headers={"X-Admin-Token": "YOUR_APP_KEY"}
        )
        return response.json()

# Usage
weights = await get_weights()
print(json.dumps(weights, indent=2))
```

---

## 2. Updating Weights

### Request

```bash
curl -X POST "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M=" \
  -H "Content-Type: application/json" \
  -d '{
    "distance": 0.40,
    "rating": 0.25,
    "acceptance": 0.15,
    "cancellation": 0.10,
    "behavior": 0.05,
    "direction": 0.05
  }'
```

### Response

```json
{
  "status": "updated",
  "weights": {
    "distance": 0.40,
    "rating": 0.25,
    "acceptance": 0.15,
    "cancellation": 0.10,
    "behavior": 0.05,
    "direction": 0.05
  }
}
```

### In Code (Python)

```python
import httpx

async def update_weights(new_weights: dict):
    async with httpx.AsyncClient() as client:
        response = await client.post(
            "http://localhost:8000/api/admin/weights",
            json=new_weights,
            headers={"X-Admin-Token": "YOUR_APP_KEY"}
        )
        return response.json()

# Usage
new_weights = {
    "distance": 0.40,
    "rating": 0.25,
    "acceptance": 0.15,
    "cancellation": 0.10,
    "behavior": 0.05,
    "direction": 0.05
}

result = await update_weights(new_weights)
print(result)
```

---

## 3. Viewing Audit Logs

### Basic Request

```bash
curl -X GET "http://localhost:8000/api/admin/weights/audit" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M=" \
  -H "Content-Type: application/json" \
  | python3 -m json.tool
```

### Response

```json
{
  "items": [
    {
      "id": 1,
      "actor": "admin",
      "payload": {
        "distance": 0.40,
        "rating": 0.25,
        "acceptance": 0.15,
        "cancellation": 0.10,
        "behavior": 0.05,
        "direction": 0.05
      },
      "created_at": "2026-05-11T12:30:45.123456+00:00"
    },
    {
      "id": 2,
      "actor": "admin",
      "payload": {
        "distance": 0.35,
        "rating": 0.2
      },
      "created_at": "2026-05-11T10:15:30.654321+00:00"
    }
  ],
  "total": 2,
  "limit": 50,
  "offset": 0
}
```

### With Pagination

```bash
# Get second page (50 items per page)
curl -X GET "http://localhost:8000/api/admin/weights/audit?limit=50&offset=50" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M="

# Get latest 10 changes
curl -X GET "http://localhost:8000/api/admin/weights/audit?limit=10&offset=0" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M="

# Get maximum results
curl -X GET "http://localhost:8000/api/admin/weights/audit?limit=200&offset=0" \
  -H "X-Admin-Token: KjlbW8W5Ed5UR4NES/iw/8aOHBcsUl9t6WQdRaKmI5M="
```

### In Code (Python)

```python
import httpx
from datetime import datetime

async def get_audit_logs(limit: int = 50, offset: int = 0):
    async with httpx.AsyncClient() as client:
        response = await client.get(
            "http://localhost:8000/api/admin/weights/audit",
            params={"limit": limit, "offset": offset},
            headers={"X-Admin-Token": "YOUR_APP_KEY"}
        )
        return response.json()

async def print_audit_history():
    logs = await get_audit_logs(limit=20)
    
    print(f"Total changes: {logs['total']}")
    print(f"Showing {len(logs['items'])} of {logs['total']}\n")
    
    for entry in logs['items']:
        timestamp = entry['created_at']
        actor = entry['actor']
        changes = entry['payload']
        
        print(f"[{timestamp}] by {actor}")
        print(f"  Changes: {changes}")
        print()

# Usage
await print_audit_history()
```

---

## 4. Complete Workflow Example

### Scenario: Adjust weights based on A/B testing results

```bash
#!/bin/bash

# Extract credentials
APP_KEY=$(grep "^APP_KEY=" ../../.env | cut -d= -f2 | sed 's/base64://')
TOKEN_HEADER="X-Admin-Token: $APP_KEY"
API_BASE="http://localhost:8000/api/admin"

echo "=== Weight Adjustment Workflow ==="
echo ""

# 1. View current weights
echo "1. Current weights:"
curl -s -X GET "$API_BASE/weights" -H "$TOKEN_HEADER" | python3 -m json.tool
echo ""

# 2. View recent audit history
echo "2. Recent changes (last 5):"
curl -s -X GET "$API_BASE/weights/audit?limit=5" -H "$TOKEN_HEADER" | \
  python3 -c "
import sys, json
data = json.load(sys.stdin)
for item in data['items']:
    print(f'  [{item[\"created_at\"]}] {item[\"actor\"]}: {item[\"payload\"]}')
"
echo ""

# 3. Update weights (e.g., increase rating importance)
echo "3. Updating weights (increase rating from 0.2 to 0.25)..."
curl -s -X POST "$API_BASE/weights" \
  -H "$TOKEN_HEADER" \
  -H "Content-Type: application/json" \
  -d '{
    "distance": 0.35,
    "rating": 0.25,
    "acceptance": 0.15,
    "cancellation": 0.1,
    "behavior": 0.1,
    "direction": 0.05
  }' | python3 -m json.tool
echo ""

# 4. Verify new weights
echo "4. Verification - New weights:"
curl -s -X GET "$API_BASE/weights" -H "$TOKEN_HEADER" | python3 -m json.tool
echo ""

# 5. View updated audit log
echo "5. Audit log (latest 3):"
curl -s -X GET "$API_BASE/weights/audit?limit=3" -H "$TOKEN_HEADER" | \
  python3 -c "
import sys, json
data = json.load(sys.stdin)
for item in data['items']:
    print(f'  ID {item[\"id\"]}: {item[\"actor\"]} at {item[\"created_at\"]}')
    for k, v in item['payload'].items():
        print(f'    {k}: {v}')
"
echo ""
echo "=== Workflow Complete ==="
```

---

## 5. Error Handling

### Missing Authentication Token

```bash
curl -X GET "http://localhost:8000/api/admin/weights"
```

**Response:**
```json
{
  "detail": "invalid admin token",
  "error_code": "UNAUTHORIZED"
}
```

**HTTP Status:** 403 Forbidden

### Invalid Token

```bash
curl -X GET "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: wrong-token"
```

**Response:**
```json
{
  "detail": "invalid admin token",
  "error_code": "UNAUTHORIZED"
}
```

**HTTP Status:** 403 Forbidden

### Database Error

```json
{
  "detail": "Failed to query audit logs",
  "error_code": "DATABASE_ERROR"
}
```

**HTTP Status:** 500 Internal Server Error

### Handling in Python

```python
import httpx
from fastapi import HTTPException

async def safe_update_weights(weights: dict):
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                "http://localhost:8000/api/admin/weights",
                json=weights,
                headers={"X-Admin-Token": "YOUR_APP_KEY"},
                timeout=10.0
            )
            response.raise_for_status()  # Raise on 4xx/5xx
            return response.json()
    
    except httpx.HTTPStatusError as e:
        if e.response.status_code == 403:
            raise ValueError("Invalid admin token")
        elif e.response.status_code == 400:
            raise ValueError(f"Invalid weights: {e.response.json()}")
        elif e.response.status_code >= 500:
            raise RuntimeError("ML service error")
    
    except httpx.ConnectError:
        raise RuntimeError("Cannot connect to ML service")
    
    except Exception as e:
        raise RuntimeError(f"Unexpected error: {e}")
```

---

## 6. Integration Examples

### Laravel Integration

```php
<?php

// app/Services/MLWeightService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class MLWeightService
{
    protected $baseUrl;
    protected $adminToken;

    public function __construct()
    {
        $this->baseUrl = config('services.ml.url');
        $this->adminToken = config('services.ml.admin_token');
    }

    public function getWeights(): array
    {
        $response = Http::withHeaders([
            'X-Admin-Token' => $this->adminToken,
        ])->get("{$this->baseUrl}/api/admin/weights");

        return $response->json();
    }

    public function updateWeights(array $weights): array
    {
        $response = Http::withHeaders([
            'X-Admin-Token' => $this->adminToken,
        ])->post("{$this->baseUrl}/api/admin/weights", $weights);

        if ($response->failed()) {
            throw new Exception("Failed to update weights: " . $response->body());
        }

        return $response->json();
    }

    public function getAuditLogs(int $limit = 50, int $offset = 0): array
    {
        $response = Http::withHeaders([
            'X-Admin-Token' => $this->adminToken,
        ])->get("{$this->baseUrl}/api/admin/weights/audit", [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $response->json();
    }
}

// Usage in controller
class WeightManagementController extends Controller
{
    public function index(MLWeightService $service)
    {
        $weights = $service->getWeights();
        $logs = $service->getAuditLogs(limit: 20);

        return view('weights.index', [
            'weights' => $weights,
            'recentChanges' => $logs['items'],
        ]);
    }

    public function update(Request $request, MLWeightService $service)
    {
        $validated = $request->validate([
            'distance' => 'required|numeric|between:0,1',
            'rating' => 'required|numeric|between:0,1',
            // ... other validations
        ]);

        $result = $service->updateWeights($validated);

        return response()->json([
            'message' => 'Weights updated successfully',
            'weights' => $result['weights'],
        ]);
    }
}
```

### React Frontend

```jsx
// src/hooks/useMLWeights.ts

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

const ML_API_BASE = process.env.REACT_APP_ML_API_URL;
const ADMIN_TOKEN = process.env.REACT_APP_ML_ADMIN_TOKEN;

export const useMLWeights = () => {
  const queryClient = useQueryClient();

  const weightsQuery = useQuery({
    queryKey: ['ml-weights'],
    queryFn: async () => {
      const response = await fetch(`${ML_API_BASE}/api/admin/weights`, {
        headers: { 'X-Admin-Token': ADMIN_TOKEN }
      });
      if (!response.ok) throw new Error('Failed to fetch weights');
      return response.json();
    },
  });

  const auditLogsQuery = useQuery({
    queryKey: ['ml-audit-logs'],
    queryFn: async () => {
      const response = await fetch(
        `${ML_API_BASE}/api/admin/weights/audit?limit=50`,
        { headers: { 'X-Admin-Token': ADMIN_TOKEN } }
      );
      if (!response.ok) throw new Error('Failed to fetch audit logs');
      return response.json();
    },
  });

  const updateWeightsMutation = useMutation({
    mutationFn: async (newWeights: Record<string, number>) => {
      const response = await fetch(`${ML_API_BASE}/api/admin/weights`, {
        method: 'POST',
        headers: {
          'X-Admin-Token': ADMIN_TOKEN,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(newWeights),
      });
      if (!response.ok) throw new Error('Failed to update weights');
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ml-weights'] });
      queryClient.invalidateQueries({ queryKey: ['ml-audit-logs'] });
    },
  });

  return {
    weights: weightsQuery.data,
    weightsLoading: weightsQuery.isLoading,
    auditLogs: auditLogsQuery.data?.items,
    updateWeights: updateWeightsMutation.mutate,
    isUpdating: updateWeightsMutation.isPending,
  };
};

// Usage in component
export function WeightEditor() {
  const { weights, auditLogs, updateWeights, isUpdating } = useMLWeights();

  const handleUpdate = (newWeights: Record<string, number>) => {
    updateWeights(newWeights);
  };

  return (
    <div>
      <h1>ML Weight Management</h1>
      {weights && (
        <WeightForm
          initialValues={weights}
          onSubmit={handleUpdate}
          disabled={isUpdating}
        />
      )}
      {auditLogs && <AuditLogTable logs={auditLogs} />}
    </div>
  );
}
```

---

## 7. Monitoring & Alerts

### Get Latest Changes

```bash
# Show last 3 changes with formatted output
curl -s -X GET "http://localhost:8000/api/admin/weights/audit?limit=3" \
  -H "X-Admin-Token: YOUR_APP_KEY" | \
  python3 << 'EOF'
import sys, json
from datetime import datetime

data = json.load(sys.stdin)
print(f"Latest {len(data['items'])} changes:\n")

for item in reversed(data['items']):  # Most recent first
    ts = datetime.fromisoformat(item['created_at'].replace('Z', '+00:00'))
    print(f"📝 {ts.strftime('%Y-%m-%d %H:%M:%S')} - {item['actor']}")
    for k, v in item['payload'].items():
        print(f"   {k:15} → {v}")
    print()
EOF
```

### Alert on Weight Changes

```python
# Monitoring script (monitoring/watch_weights.py)
import asyncio
import httpx
import json
from datetime import datetime, timedelta

async def check_weight_changes():
    async with httpx.AsyncClient() as client:
        response = await client.get(
            "http://localhost:8000/api/admin/weights/audit",
            params={"limit": 1},
            headers={"X-Admin-Token": "YOUR_APP_KEY"}
        )
        
        if response.status_code == 200:
            data = response.json()
            if data['items']:
                latest = data['items'][0]
                ts = datetime.fromisoformat(
                    latest['created_at'].replace('Z', '+00:00')
                )
                
                # Alert if changed in last 5 minutes
                if datetime.now(ts.tzinfo) - ts < timedelta(minutes=5):
                    print(f"⚠️  Weight change detected!")
                    print(f"   Actor: {latest['actor']}")
                    print(f"   Changes: {json.dumps(latest['payload'], indent=2)}")
                    # Send to monitoring system (DataDog, New Relic, etc.)

asyncio.run(check_weight_changes())
```

---

## Summary

| Action | Endpoint | Method | Headers |
|--------|----------|--------|---------|
| Get weights | `/api/admin/weights` | GET | `X-Admin-Token` |
| Update weights | `/api/admin/weights` | POST | `X-Admin-Token` |
| View audit logs | `/api/admin/weights/audit` | GET | `X-Admin-Token` |

**Parameters for audit logs:**
- `limit`: 1-200 (default: 50)
- `offset`: >= 0 (default: 0)

---

For more details:
- [Migration & Initialization Guide](ML_SERVICE_MIGRATION_GUIDE.md)
- [Testing Guide](ML_SERVICE_TESTING_GUIDE.md)
- [Architecture Guide](ML_SERVICE_ARCHITECTURE.md)
