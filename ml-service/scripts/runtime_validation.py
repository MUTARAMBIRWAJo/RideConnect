"""Deep runtime validation for the RideConnect ML service."""

from __future__ import annotations

import asyncio
import json
import math
import os
import sys
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any
from unittest.mock import patch

import numpy as np
import pandas as pd
from fastapi.testclient import TestClient

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
MODEL_PATH = ROOT / "models" / "trained" / "rideconnect_v2_best.keras"
SCALER_PATH = ROOT / "models" / "trained" / "feature_scaler.pkl"


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
                "driver_id": 1,
                "distance_km": 1.2,
                "driver_rating": 4.8,
                "acceptance_rate": 92,
                "cancellation_rate": 2,
                "behavior_score": 88,
                "available_seats": 4,
                "traffic_level": 0.3,
                "direction_similarity": 0.9,
            }
        ],
    }


def record(results: list[CheckResult], name: str, passed: bool, **details: Any) -> None:
    results.append(CheckResult(name=name, passed=passed, details=details))


def json_safe(value: Any) -> Any:
    if isinstance(value, np.ndarray):
        return value.tolist()
    if isinstance(value, tuple):
        return [json_safe(item) for item in value]
    if isinstance(value, list):
        return [json_safe(item) for item in value]
    if isinstance(value, dict):
        return {key: json_safe(item) for key, item in value.items()}
    if isinstance(value, (np.integer, np.floating)):
        return value.item()
    return value


def validate_model(results: list[CheckResult]) -> dict[str, Any]:
    import tensorflow as tf

    from app.core.feature_config import EXPECTED_FEATURE_COUNT
    from app.services.model_loader import ModelLoader

    loader = ModelLoader()
    loader.model_path = str(MODEL_PATH)
    started = time.perf_counter()
    asyncio.run(loader.initialize())
    latency_ms = (time.perf_counter() - started) * 1000

    model = loader.model
    assert model is not None
    summary_lines: list[str] = []
    model.summary(print_fn=summary_lines.append)

    if loader._is_dual_input():
        temporal = np.zeros((1, 16, 17), dtype=np.float32)
        zone = np.zeros((1, 1), dtype=np.int32)
        prediction = loader.predict_dual_input(temporal, zone)
        dummy_input_shape: Any = [temporal.shape, zone.shape]
    else:
        dummy = np.zeros((1, EXPECTED_FEATURE_COUNT), dtype=np.float32)
        prediction = loader.predict(dummy)
        dummy_input_shape = dummy.shape

    prediction_valid = (
        isinstance(prediction, np.ndarray)
        and prediction.size > 0
        and not np.isnan(prediction).any()
        and not np.isinf(prediction).any()
    )

    details = {
        "model_path": str(MODEL_PATH),
        "input_shape": loader.get_model_info().get("input_shape"),
        "output_shape": loader.get_model_info().get("output_shape"),
        "summary_callable": len(summary_lines) > 0,
        "dummy_input_shape": dummy_input_shape,
        "dummy_prediction_shape": prediction.shape,
        "dummy_prediction_valid": prediction_valid,
        "load_and_warmup_latency_ms": round(latency_ms, 2),
        "tensorflow_version": tf.__version__,
        "available_devices": [str(device) for device in tf.config.list_physical_devices()],
    }
    record(results, "real_model_load_and_dummy_prediction", prediction_valid, **details)
    return details


def validate_scaler(results: list[CheckResult]) -> dict[str, Any]:
    from app.core.feature_config import EXPECTED_FEATURE_COUNT
    from app.core.scaler_manager import ScalerManager

    dummy = np.zeros((1, EXPECTED_FEATURE_COUNT), dtype=np.float32)
    strict_manager = ScalerManager()

    if not SCALER_PATH.exists():
        try:
            strict_manager.load_scaler(str(SCALER_PATH))
            strict_error = None
        except Exception as exc:
            strict_error = str(exc)
        passed = strict_error is not None
        details = {
            "scaler_path": str(SCALER_PATH),
            "exists": False,
            "strict_failure_verified": passed,
            "strict_error": strict_error,
        }
        record(results, "real_scaler_load", False, **details)
        record(results, "missing_scaler_strict_failure", passed, **details)
        return details

    manager = ScalerManager()
    manager.load_scaler(str(SCALER_PATH))
    scaled = manager.transform(dummy)
    compatible = scaled.shape == dummy.shape
    finite = not np.isnan(scaled).any() and not np.isinf(scaled).any()
    details = {
        "scaler_path": str(SCALER_PATH),
        "exists": True,
        "info": manager.get_scaler_info(),
        "input_shape": dummy.shape,
        "scaled_shape": scaled.shape,
        "compatible_with_feature_count": compatible,
        "finite_output": finite,
    }
    record(results, "real_scaler_load", compatible and finite, **details)
    return details


def validate_feature_pipeline(results: list[CheckResult]) -> dict[str, Any]:
    from app.core.feature_config import FEATURE_COLUMNS, validate_feature_order
    from app.schemas.match_request import MatchRequestPayload
    from app.services.preprocessing_service import FeatureEngineeringService

    payload = MatchRequestPayload(**build_match_payload())
    engineer = FeatureEngineeringService()
    features = [
        engineer.engineer_features(driver, payload.ride_request)
        for driver in payload.candidate_drivers
    ]
    feature_batch = np.vstack(features).astype(np.float32)
    frame = pd.DataFrame(feature_batch, columns=FEATURE_COLUMNS)
    validate_feature_order(list(frame.columns))

    details = {
        "feature_names": FEATURE_COLUMNS,
        "feature_tensor_shape": feature_batch.shape,
        "dataframe_columns": list(frame.columns),
        "dataframe_shape": frame.shape,
        "feature_values": frame.iloc[0].to_dict(),
    }
    record(
        results,
        "feature_pipeline",
        feature_batch.shape == (1, len(FEATURE_COLUMNS)) and list(frame.columns) == FEATURE_COLUMNS,
        **details,
    )
    return details


def validate_app_runtime(results: list[CheckResult]) -> dict[str, Any]:
    from app.core.config import settings
    from app.main import app

    if not SCALER_PATH.exists():
        settings.ALLOW_SCALER_FALLBACK = True

    with TestClient(app) as client:
        health_response = client.get("/health")
        health_payload = health_response.json() if health_response.headers.get("content-type", "").startswith("application/json") else {}
        required_health_fields = {
            "status",
            "model_loaded",
            "scaler_loaded",
            "model_input_shape",
            "model_name",
            "uptime_seconds",
            "tensorflow_version",
            "available_devices",
        }
        health_passed = health_response.status_code == 200 and required_health_fields.issubset(health_payload)
        record(
            results,
            "health_endpoint",
            health_passed,
            status_code=health_response.status_code,
            required_fields=sorted(required_health_fields),
            payload=health_payload,
        )

        request_id = "runtime-validation-001"
        started = time.perf_counter()
        match_response = client.post(
            "/predict/match-driver",
            headers={"X-Request-ID": request_id},
            json=build_match_payload(),
        )
        latency_ms = (time.perf_counter() - started) * 1000
        match_payload = match_response.json() if match_response.headers.get("content-type", "").startswith("application/json") else {}
        scores = [
            item.get("score")
            for item in match_payload.get("ranked_drivers", [])
            if isinstance(item, dict)
        ]
        score_ok = bool(scores) and all(
            isinstance(score, (int, float)) and math.isfinite(score)
            for score in scores
        )
        record(
            results,
            "real_match_inference_endpoint",
            match_response.status_code == 200 and score_ok,
            status_code=match_response.status_code,
            request_id_header=match_response.headers.get("X-Request-ID"),
            request_id_propagated=match_response.headers.get("X-Request-ID") == request_id,
            latency_ms=round(latency_ms, 2),
            payload=match_payload,
        )

        demand_response = client.post(
            "/predict/demand",
            json={"latitude": -1.9441, "longitude": 30.0619, "hour": 14, "day_of_week": 2},
        )
        eta_response = client.post(
            "/predict/eta",
            json={
                "pickup_latitude": -1.9441,
                "pickup_longitude": 30.0619,
                "destination_latitude": -1.9536,
                "destination_longitude": 30.1044,
                "traffic_level": 0.3,
                "distance_km": 2.5,
            },
        )
        openapi_response = client.get("/docs/openapi.json")
        openapi_paths = sorted(openapi_response.json().get("paths", {}).keys()) if openapi_response.status_code == 200 else []
        expected_paths = {"/health", "/predict/match-driver", "/predict/demand", "/predict/eta"}
        record(
            results,
            "fastapi_routes_and_openapi",
            openapi_response.status_code == 200
            and expected_paths.issubset(openapi_paths)
            and demand_response.status_code == 200
            and eta_response.status_code == 200,
            openapi_status_code=openapi_response.status_code,
            demand_status_code=demand_response.status_code,
            eta_status_code=eta_response.status_code,
            expected_paths=sorted(expected_paths),
            registered_paths=openapi_paths,
        )

        startup_validation = getattr(client.app.state, "startup_validation", None)
        monitoring = getattr(client.app.state, "monitoring", None)
        record(
            results,
            "startup_lifecycle_and_monitoring",
            startup_validation is not None and monitoring is not None,
            startup_validation=startup_validation,
            monitoring=monitoring,
        )

        from app.core.startup import get_model_loader

        original_timeout = settings.INFERENCE_TIMEOUT
        original_predict = get_model_loader().predict

        def slow_predict(features: np.ndarray) -> np.ndarray:
            time.sleep(0.2)
            return original_predict(features)

        timeout_request_id = "runtime-validation-timeout"
        settings.INFERENCE_TIMEOUT = 0.01
        try:
            with patch.object(get_model_loader(), "predict", side_effect=slow_predict):
                timeout_response = client.post(
                    "/predict/match-driver",
                    headers={"X-Request-ID": timeout_request_id},
                    json=build_match_payload(),
                )
        finally:
            settings.INFERENCE_TIMEOUT = original_timeout

        timeout_payload = timeout_response.json() if timeout_response.headers.get("content-type", "").startswith("application/json") else {}
        record(
            results,
            "timeout_protection_and_request_tracing",
            timeout_response.status_code == 500
            and timeout_response.headers.get("X-Request-ID") == timeout_request_id
            and timeout_request_id in json.dumps(timeout_payload),
            status_code=timeout_response.status_code,
            request_id_header=timeout_response.headers.get("X-Request-ID"),
            payload=timeout_payload,
        )

        return {"health": health_payload, "match_latency_ms": latency_ms}


def write_markdown_report(results: list[CheckResult], runtime_details: dict[str, Any]) -> None:
    passed = [result for result in results if result.passed]
    failed = [result for result in results if not result.passed]
    report_path = ROOT / "ML_RUNTIME_VALIDATION_REPORT.md"

    lines = [
        "# ML Runtime Validation Report",
        "",
        f"Generated: {time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())}",
        "",
        "## Summary",
        "",
        f"- Overall status: {'PASS' if not failed else 'FAIL'}",
        f"- Passed checks: {len(passed)}",
        f"- Failed checks: {len(failed)}",
        f"- Model: `{MODEL_PATH}`",
        f"- Scaler: `{SCALER_PATH}`",
        "",
        "## Runtime Metrics",
        "",
        f"- Inference latency: {runtime_details.get('match_latency_ms', 0):.2f} ms",
        f"- TensorFlow version: {runtime_details.get('model', {}).get('tensorflow_version', 'unknown')}",
        f"- TensorFlow devices: {runtime_details.get('model', {}).get('available_devices', [])}",
        "",
        "## Validation Steps",
        "",
    ]
    for result in results:
        status = "PASS" if result.passed else "FAIL"
        lines.append(f"- {status}: {result.name}")

    lines.extend(["", "## Docker Validation", ""])
    lines.append("- Docker validation is performed separately with `docker compose build ml-service` and `docker compose up ml-service`.")
    lines.append("- This run could not append Docker output unless those commands are executed in a Docker-enabled environment.")

    lines.extend(["", "## Unresolved Issues", ""])
    if failed:
        for result in failed:
            details = json.dumps(json_safe(result.details), default=str)[:1000]
            lines.append(f"- `{result.name}`: {details}")
    else:
        lines.append("- None.")

    lines.extend(["", "## Raw Results", "", "```json"])
    lines.append(json.dumps(json_safe({"results": [asdict(result) for result in results]}), indent=2, default=str))
    lines.append("```")
    report_path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    os.environ.setdefault("MODEL_PATH", str(MODEL_PATH))
    os.environ.setdefault("MODEL_DIR", str(MODEL_PATH.parent))
    os.environ.setdefault("SCALER_PATH", str(SCALER_PATH))

    results: list[CheckResult] = []
    runtime_details: dict[str, Any] = {}

    try:
        runtime_details["model"] = validate_model(results)
        runtime_details["scaler"] = validate_scaler(results)
        runtime_details["features"] = validate_feature_pipeline(results)
        runtime_details.update(validate_app_runtime(results))
    except Exception as exc:
        record(results, "runtime_validation_script", False, error=str(exc), error_type=exc.__class__.__name__)
    finally:
        write_markdown_report(results, runtime_details)

    report = {
        "overall_passed": all(result.passed for result in results),
        "results": [asdict(result) for result in results],
        "report_path": str(ROOT / "ML_RUNTIME_VALIDATION_REPORT.md"),
    }
    print(json.dumps(json_safe(report), indent=2, default=str))
    return 0 if report["overall_passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
