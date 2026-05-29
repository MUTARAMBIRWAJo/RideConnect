import os
import time
from typing import List

import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

MODEL_PATH = os.environ.get(
    "MODEL_PATH",
    os.path.join(os.path.dirname(__file__), "Matching_Modal_tflite_learn_1013157_3.tflite"),
)

try:
    import tflite_runtime.interpreter as tflite

    interpreter = tflite.Interpreter(model_path=MODEL_PATH)
    _backend = "tflite_runtime"
except ImportError:
    import tensorflow as tf

    interpreter = tf.lite.Interpreter(model_path=MODEL_PATH)
    _backend = "tensorflow"

interpreter.allocate_tensors()
input_details = interpreter.get_input_details()
output_details = interpreter.get_output_details()

MODEL_VERSION = "Matching_Modal_tflite_learn_1013157_3"

print(f"[RideConnect ML] Model loaded via {_backend}")
print(f"[RideConnect ML] Input  shape: {input_details[0]['shape']}")
print(f"[RideConnect ML] Output shape: {output_details[0]['shape']}")


class CandidateDriver(BaseModel):
    driver_id: int
    distance_km: float
    rating: float
    total_rides: int
    acceptance_rate: float
    cancellation_rate: float


class RankRequest(BaseModel):
    trip_id: int
    transport_type: str
    pickup_lat: float
    pickup_lng: float
    candidates: List[CandidateDriver]


class RankedDriver(BaseModel):
    driver_id: int
    score: float
    score_breakdown: dict


class RankResponse(BaseModel):
    ranked_drivers: List[RankedDriver]
    model_version: str
    backend: str
    latency_ms: int


TRANSPORT_MAP = {"moto": 0, "car": 1, "bus": 2}


def build_feature_vector(c: CandidateDriver, req: RankRequest) -> np.ndarray:
    """
    Feature order MUST match training. Current order:
      [distance_km_norm, rating_norm, total_rides_norm,
       acceptance_rate, inverted_cancellation_rate, transport_type_norm]
    If model was trained with a different column order, adjust here.
    """
    return np.array(
        [
            min(c.distance_km / 5.0, 1.0),
            c.rating / 5.0,
            min(c.total_rides / 1000.0, 1.0),
            c.acceptance_rate,
            1.0 - c.cancellation_rate,
            TRANSPORT_MAP.get(req.transport_type, 0) / 2.0,
        ],
        dtype=np.float32,
    )


def run_single_inference(features: np.ndarray) -> float:
    expected_shape = input_details[0]["shape"]
    input_data = features.reshape(expected_shape)
    interpreter.set_tensor(input_details[0]["index"], input_data)
    interpreter.invoke()
    output = interpreter.get_tensor(output_details[0]["index"])
    return float(np.squeeze(output))


app = FastAPI(title="RideConnect Matching Service", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model": MODEL_VERSION,
        "backend": _backend,
        "input_shape": input_details[0]["shape"].tolist(),
    }


@app.post("/rank-drivers", response_model=RankResponse)
def rank_drivers(req: RankRequest):
    if not req.candidates:
        raise HTTPException(status_code=422, detail="No candidates provided")

    t_start = time.time()
    ranked = []

    for c in req.candidates:
        features = build_feature_vector(c, req)
        score = run_single_inference(features)
        ranked.append(
            RankedDriver(
                driver_id=c.driver_id,
                score=round(score, 6),
                score_breakdown={
                    "distance_km": c.distance_km,
                    "rating": c.rating,
                    "total_rides": c.total_rides,
                    "acceptance_rate": c.acceptance_rate,
                    "cancellation_rate": c.cancellation_rate,
                    "transport_type": req.transport_type,
                    "raw_score": score,
                },
            )
        )

    ranked.sort(key=lambda d: d.score, reverse=True)
    latency_ms = int((time.time() - t_start) * 1000)

    return RankResponse(
        ranked_drivers=ranked,
        model_version=MODEL_VERSION,
        backend=_backend,
        latency_ms=latency_ms,
    )
