"""Runtime validation for the RideConnect ML service.

This script boots the FastAPI app, exercises the real /health and
/predict/match-driver routes, and prints a JSON summary that can be used in
release validation.
"""

from __future__ import annotations

import json
import sys
from dataclasses import dataclass, asdict
from typing import Any

from fastapi.testclient import TestClient

from app.main import app


@dataclass
class CheckResult:
    name: str
    passed: bool
    details: dict[str, Any]


def build_match_payload() -> dict[str, Any]:
    return {
        "ride_request": {
            "pickup_latitude": -1.9441,
            "pickup_longitude": 30.0619,
            "destination_latitude": -1.9536,
            "destination_longitude": 30.1044,
            "requested_vehicle_type": "car",
            "required_seats": 3,
        },
        "candidate_drivers": [
            {
                "driver_id": 101,
                "distance_km": 1.2,
                "driver_rating": 4.8,
                "acceptance_rate": 92,
                "cancellation_rate": 2,
                "behavior_score": 88,
                "available_seats": 4,
                "traffic_level": 0.3,
                "direction_similarity": 0.9,
            },
            {
                "driver_id": 102,
                "distance_km": 2.1,
                "driver_rating": 4.4,
                "acceptance_rate": 87,
                "cancellation_rate": 4,
                "behavior_score": 80,
                "available_seats": 4,
                "traffic_level": 0.2,
                "direction_similarity": 0.7,
            },
        ],
    }


def main() -> int:
    results: list[CheckResult] = []

    with TestClient(app) as client:
        health_response = client.get("/health")
        health_ok = health_response.status_code == 200
        health_payload = {}
        if health_ok:
            health_payload = health_response.json()
        results.append(
            CheckResult(
                name="health_endpoint",
                passed=health_ok,
                details={
                    "status_code": health_response.status_code,
                    "payload": health_payload,
                },
            )
        )

        request_id = "runtime-validation-001"
        match_response = client.post(
            "/predict/match-driver",
            headers={"X-Request-ID": request_id},
            json=build_match_payload(),
        )
        match_ok = match_response.status_code == 200
        match_payload = {}
        if match_ok:
            match_payload = match_response.json()

        results.append(
            CheckResult(
                name="match_driver_endpoint",
                passed=match_ok,
                details={
                    "status_code": match_response.status_code,
                    "request_id_header": match_response.headers.get("X-Request-ID"),
                    "payload": match_payload,
                },
            )
        )

        openapi_response = client.get("/docs/openapi.json")
        openapi_ok = openapi_response.status_code == 200
        openapi_paths = []
        if openapi_ok:
            openapi_paths = sorted(openapi_response.json().get("paths", {}).keys())
        results.append(
            CheckResult(
                name="openapi_documentation",
                passed=openapi_ok and "/predict/match-driver" in openapi_paths,
                details={
                    "status_code": openapi_response.status_code,
                    "paths": openapi_paths,
                },
            )
        )

        startup_validation = getattr(client.app.state, "startup_validation", None)
        results.append(
            CheckResult(
                name="startup_validation_state",
                passed=startup_validation is not None,
                details={
                    "present": startup_validation is not None,
                    "value": startup_validation,
                },
            )
        )

    report = {
        "overall_passed": all(result.passed for result in results),
        "results": [asdict(result) for result in results],
    }
    print(json.dumps(report, indent=2, default=str))
    return 0 if report["overall_passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
