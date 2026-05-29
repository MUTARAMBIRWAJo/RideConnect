import time
from typing import List

import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

try:
    import tflite_runtime.interpreter as tflite
except ImportError:
    import tensorflow as tf

    tflite = tf.lite

MODEL_PATH = "Matching_Modal_tflite_learn_1013157_3.tflite"

interpreter = tflite.Interpreter(model_path=MODEL_PATH)
interpreter.allocate_tensors()
input_details = interpreter.get_input_details()
output_details = interpreter.get_output_details()

print("TFLite input  details:", input_details)
print("TFLite output details:", output_details)


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
    latency_ms: int


TRANSPORT_MAP = {"moto": 0, "car": 1, "bus": 2}


def build_feature_vector(candidate: CandidateDriver, req: RankRequest) -> np.ndarray:
    transport_enc = TRANSPORT_MAP.get(req.transport_type, 0)
    total_rides_norm = min(candidate.total_rides / 1000.0, 1.0)

    return np.array(
        [
            candidate.distance_km / 5.0,
            candidate.rating / 5.0,
            total_rides_norm,
            candidate.acceptance_rate,
            1.0 - candidate.cancellation_rate,
            transport_enc / 2.0,
        ],
        dtype=np.float32,
    )


def run_inference(features: np.ndarray) -> float:
    input_shape = input_details[0]["shape"]
    input_data = features.reshape(input_shape).astype(np.float32)

    interpreter.set_tensor(input_details[0]["index"], input_data)
    interpreter.invoke()

    output = interpreter.get_tensor(output_details[0]["index"])
    return float(np.squeeze(output))


app = FastAPI(title="RideConnect TFLite Matching Service")


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_PATH}


@app.post("/rank-drivers", response_model=RankResponse)
def rank_drivers(req: RankRequest):
    if not req.candidates:
        raise HTTPException(status_code=422, detail="No candidates provided")

    t_start = time.time()
    ranked = []

    for candidate in req.candidates:
        features = build_feature_vector(candidate, req)
        score = run_inference(features)

        ranked.append(
            RankedDriver(
                driver_id=candidate.driver_id,
                score=round(score, 6),
                score_breakdown={
                    "distance_km": candidate.distance_km,
                    "rating": candidate.rating,
                    "total_rides": candidate.total_rides,
                    "acceptance_rate": candidate.acceptance_rate,
                    "cancellation_rate": candidate.cancellation_rate,
                    "transport_type": req.transport_type,
                    "raw_score": score,
                },
            )
        )

    ranked.sort(key=lambda driver: driver.score, reverse=True)

    latency_ms = int((time.time() - t_start) * 1000)
    return RankResponse(
        ranked_drivers=ranked,
        model_version="Matching_Modal_tflite_learn_1013157_3",
        latency_ms=latency_ms,
    )
