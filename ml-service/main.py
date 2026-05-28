from __future__ import annotations

import glob
import os
from pathlib import Path
from typing import Any

import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field, model_validator

FEATURE_ORDER = [
    "distance_km",
    "predicted_eta_min",
    "driver_rating",
    "acceptance_rate",
    "cancellation_rate",
    "on_time_rate",
    "behavior_score",
    "total_rides",
    "driver_idle_minutes",
    "vehicle_match",
    "same_zone",
    "is_rush_hour",
    "traffic_level",
    "weather_severity",
    "request_hour",
]

try:
    from tflite_runtime.interpreter import Interpreter as TFLiteInterpreter
except ImportError:
    try:
        from ai_edge_litert.interpreter import Interpreter as TFLiteInterpreter
    except ImportError:
        try:
            from tensorflow.lite import Interpreter as TFLiteInterpreter
        except ImportError:
            TFLiteInterpreter = None

app = FastAPI(title="RideConnect TFLite Matching Service", version="1.0.0")

MODEL_PATH: Path | None = None
INTERPRETER = None
INPUT_INDEX = None
OUTPUT_INDEX = None
INPUT_SHAPE = None
MODEL_LOADED = False


class CandidateFeatures(BaseModel):
    distance_km: float = Field(..., ge=0)
    predicted_eta_min: float = Field(..., ge=0)
    driver_rating: float = Field(..., ge=0)
    acceptance_rate: float = Field(..., ge=0)
    cancellation_rate: float = Field(..., ge=0)
    on_time_rate: float = Field(..., ge=0)
    behavior_score: float = Field(..., ge=0)
    total_rides: float = Field(..., ge=0)
    driver_idle_minutes: float = Field(..., ge=0)
    vehicle_match: float = Field(..., ge=0)
    same_zone: float = Field(..., ge=0)
    is_rush_hour: float = Field(..., ge=0)
    traffic_level: float = Field(..., ge=0)
    weather_severity: float = Field(..., ge=0)
    request_hour: float = Field(..., ge=0)


class MatchingCandidate(BaseModel):
    driver_id: int | str
    features: dict[str, float]

    @model_validator(mode="after")
    def validate_required_features(self) -> "MatchingCandidate":
        missing = [feature for feature in FEATURE_ORDER if feature not in self.features]
        if missing:
            raise ValueError(f"Missing required features: {', '.join(missing)}")
        return self


class MatchingRequest(BaseModel):
    trip_id: int | str
    matching_session_id: str | None = None
    candidates: list[MatchingCandidate]

    @model_validator(mode="after")
    def validate_candidates(self) -> "MatchingRequest":
        if not self.candidates:
            raise ValueError("At least one candidate is required")
        return self


class MatchingResponse(BaseModel):
    selected_driver_id: int | str
    ranked: list[dict[str, Any]]


def _discover_model_path() -> Path | None:
    env_model_path = os.getenv("MODEL_PATH")
    if env_model_path:
        model_path = Path(env_model_path)
        if not model_path.exists():
            raise FileNotFoundError(f"MODEL_PATH not found: {model_path}")
        return model_path

    model_dir = Path(os.getenv("MODEL_DIR", "model"))
    model_dir.mkdir(parents=True, exist_ok=True)
    matches = sorted(model_dir.glob("*.tflite"))
    if not matches:
        return None
    return matches[0]


@app.on_event("startup")
def startup() -> None:
    global MODEL_PATH, INTERPRETER, INPUT_INDEX, OUTPUT_INDEX, INPUT_SHAPE, MODEL_LOADED

    try:
        MODEL_PATH = _discover_model_path()
    except FileNotFoundError as exc:
        print(f"MODEL discovery failed: {exc}")
        MODEL_PATH = None

    if MODEL_PATH is None:
        print("No .tflite model found. The service will keep running, but ranking requests will return 503 until a model is available.")
        MODEL_LOADED = False
        INTERPRETER = None
        INPUT_INDEX = None
        OUTPUT_INDEX = None
        INPUT_SHAPE = None
        return

    if TFLiteInterpreter is None:
        raise RuntimeError(
            "No TFLite interpreter available. Install tflite-runtime, ai-edge-litert, or tensorflow.lite."
        )

    try:
        interpreter = TFLiteInterpreter(str(MODEL_PATH))
        interpreter.allocate_tensors()
        input_details = interpreter.get_input_details()
        output_details = interpreter.get_output_details()

        INPUT_INDEX = input_details[0]["index"]
        OUTPUT_INDEX = output_details[0]["index"]
        INPUT_SHAPE = tuple(input_details[0]["shape"])

        INTERPRETER = interpreter
        MODEL_LOADED = True
        print(f"Loaded TFLite model from {MODEL_PATH} with input shape {INPUT_SHAPE}")
    except Exception as exc:
        MODEL_LOADED = False
        INTERPRETER = None
        INPUT_INDEX = None
        OUTPUT_INDEX = None
        INPUT_SHAPE = None
        raise RuntimeError(f"Failed to load TFLite model: {exc}") from exc


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/rank-drivers")
@app.post("/ml/rank-drivers")
def rank_drivers(payload: MatchingRequest) -> MatchingResponse:
    if not MODEL_LOADED or INTERPRETER is None or INPUT_INDEX is None or OUTPUT_INDEX is None:
        raise HTTPException(status_code=503, detail="TFLite model is not loaded")

    accept_index = int(os.getenv("ACCEPT_INDEX", "0"))
    ranked_results: list[dict[str, Any]] = []

    for candidate in payload.candidates:
        feature_values = [float(candidate.features[feature]) for feature in FEATURE_ORDER]
        input_array = np.asarray(feature_values, dtype=np.float32).reshape(1, len(FEATURE_ORDER))

        INTERPRETER.set_tensor(INPUT_INDEX, input_array)
        INTERPRETER.invoke()

        output = np.asarray(INTERPRETER.get_tensor(OUTPUT_INDEX))
        if output.ndim == 0:
            probabilities = np.asarray([float(output)], dtype=np.float32)
        else:
            probabilities = np.asarray(output[0], dtype=np.float32)

        # Edge Impulse sorts classes alphabetically. For this 2-class export,
        # the accepted class is the alphabetically first class, typically index 0.
        # Verify the correct class index on the Edge Impulse Classifier page if
        # the ranking looks inverted in production.
        if accept_index < 0 or accept_index >= len(probabilities):
            raise HTTPException(
                status_code=500,
                detail=f"ACCEPT_INDEX {accept_index} is out of range for model output length {len(probabilities)}",
            )

        score = float(probabilities[accept_index])
        ranked_results.append(
            {
                "driver_id": candidate.driver_id,
                "score": round(score, 4),
            }
        )

    ranked_results.sort(key=lambda item: item["score"], reverse=True)
    for rank, item in enumerate(ranked_results, start=1):
        item["rank"] = rank

    top_driver = ranked_results[0]
    return MatchingResponse(selected_driver_id=top_driver["driver_id"], ranked=ranked_results)
