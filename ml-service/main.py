"""
ml/main.py — RideConnect ML microservice v2 (dynamic demand prediction)
"""
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from datetime import datetime
from demand_model import predict_demand, predict_all_zones

app = FastAPI(title="RideConnect ML Service", version="2.0")

class DemandRequest(BaseModel):
    zone_id: str = "ALL"
    timestamp: str
    features: dict = {}

class DemandBulkRequest(BaseModel):
    timestamp: str
    features: dict = {}

@app.get("/health")
def health():
    return {"status": "ok", "version": "2.0-dynamic"}

@app.post("/predict-demand")
def predict(req: DemandRequest):
    try:
        ts = datetime.fromisoformat(req.timestamp)
    except ValueError:
        raise HTTPException(400, "timestamp must be ISO format: 2025-06-16T08:00:00")
    if req.zone_id == "ALL":
        zones = predict_all_zones(ts, req.features)
        return {"status": "success", "data": {"zones": zones, "predicted_at": req.timestamp}}
    result = predict_demand(req.zone_id, ts, req.features)
    return {"status": "success", "data": result}

@app.post("/predict-demand/all")
def predict_all(req: DemandBulkRequest):
    try:
        ts = datetime.fromisoformat(req.timestamp)
    except ValueError:
        raise HTTPException(400, "Invalid timestamp")
    zones = predict_all_zones(ts, req.features)
    return {"status": "success", "data": {"zones": zones, "predicted_at": req.timestamp}}

@app.post("/rank-drivers")
def rank_drivers(body: dict):
    pickup = body.get("pickup", {})
    candidates = body.get("candidates", [])
    px, py = pickup.get("lat", 0), pickup.get("lng", 0)
    def dist(c):
        return (c.get("lat", 0) - px)**2 + (c.get("lng", 0) - py)**2
    ranked = sorted(candidates, key=dist)
    return {"status": "success", "data": ranked}

@app.post("/detect-anomaly")
def detect_anomaly(body: dict):
    return {"status": "success", "data": {"anomaly_score": 0.1, "is_anomalous": False}}

@app.post("/retrain")
def retrain_model(body: dict):
    # Simulated retraining job
    import time
    time.sleep(2)
    models = body.get("models", ["demand_model"])
    return {
        "status": "success",
        "data": {
            "retrained_models": models,
            "metrics": {"accuracy": 0.95, "loss": 0.05},
            "finished_at": datetime.now().isoformat()
        }
    }
