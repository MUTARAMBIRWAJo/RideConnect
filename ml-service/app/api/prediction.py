"""Additional prediction endpoints"""
from datetime import datetime, timezone

from fastapi import APIRouter, BackgroundTasks, HTTPException
from pydantic import BaseModel, Field

from app.core.logging import get_logger
from app.services.behavior_detector_loader import get_behavior_detector_loader
from app.services.demand_model import DemandModel
from app.services.eta_model import ETAModel

logger = get_logger(__name__)
router = APIRouter(prefix="/predict", tags=["predictions"])

_demand_model = DemandModel()
_eta_model = ETAModel()


class DemandPredictionRequest(BaseModel):
    """Schema for demand prediction request"""
    latitude: float = Field(..., description="Location latitude")
    longitude: float = Field(..., description="Location longitude")
    hour: int = Field(..., ge=0, le=23, description="Hour of day")
    day_of_week: int = Field(..., ge=0, le=6, description="Day of week (0=Monday)")
    
    class Config:
        json_schema_extra = {
            "example": {
                "latitude": -1.9441,
                "longitude": 30.0619,
                "hour": 14,
                "day_of_week": 2,
            }
        }


class DemandPredictionResponse(BaseModel):
    """Schema for demand prediction response"""
    demand_level: float = Field(..., ge=0.0, le=1.0, description="Predicted demand level")
    expected_wait_time_minutes: int = Field(..., description="Expected wait time in minutes")
    confidence: float = Field(..., ge=0.0, le=1.0, description="Confidence score")
    
    class Config:
        json_schema_extra = {
            "example": {
                "demand_level": 0.75,
                "expected_wait_time_minutes": 8,
                "confidence": 0.92,
            }
        }


class ETAPredictionRequest(BaseModel):
    """Schema for ETA prediction request"""
    pickup_latitude: float = Field(..., description="Pickup latitude")
    pickup_longitude: float = Field(..., description="Pickup longitude")
    destination_latitude: float = Field(..., description="Destination latitude")
    destination_longitude: float = Field(..., description="Destination longitude")
    traffic_level: float = Field(..., ge=0.0, le=1.0, description="Traffic level")
    distance_km: float = Field(..., ge=0.0, description="Distance in km")
    
    class Config:
        json_schema_extra = {
            "example": {
                "pickup_latitude": -1.9441,
                "pickup_longitude": 30.0619,
                "destination_latitude": -1.9536,
                "destination_longitude": 30.1044,
                "traffic_level": 0.3,
                "distance_km": 2.5,
            }
        }


class ETAPredictionResponse(BaseModel):
    """Schema for ETA prediction response"""
    estimated_time_minutes: float = Field(..., ge=0.0, description="Estimated time in minutes")
    distance_km: float = Field(..., description="Distance in km")
    confidence: float = Field(..., ge=0.0, le=1.0, description="Confidence score")

    class Config:
        json_schema_extra = {
            "example": {
                "estimated_time_minutes": 12.5,
                "distance_km": 2.5,
                "confidence": 0.88,
            }
        }


class GPSReading(BaseModel):
    """GPS reading for anomaly detection"""
    speed_kmh: float = Field(..., ge=0, le=300, description="Speed in km/h")
    acceleration_ms2: float = Field(..., ge=-15, le=15, description="Acceleration in m/s²")
    heading_change_degrees: float = Field(..., ge=0, le=360, description="Heading change in degrees")
    route_deviation_meters: float = Field(..., ge=0, le=5000, description="Route deviation in meters")
    stop_duration_seconds: float = Field(..., ge=0, le=7200, description="Stop duration in seconds")


class AnomalyDetectionRequest(BaseModel):
    """Request schema for driver behavior anomaly detection"""
    gps_reading: GPSReading


class AnomalyDetectionResponse(BaseModel):
    """Response schema for driver behavior anomaly detection"""
    is_anomaly: bool = Field(..., description="Whether the reading is anomalous")
    anomaly_score: float = Field(..., description="Raw anomaly score (lower = more anomalous)")
    severity: str = Field(..., description="Severity level: low, medium, or high")
    model_version: str = Field(..., description="Model version identifier")
    detected_at: str = Field(..., description="ISO 8601 UTC timestamp of detection")

    class Config:
        json_schema_extra = {
            "example": {
                "is_anomaly": True,
                "anomaly_score": -0.25,
                "severity": "high",
                "model_version": "behavior_isolation_forest_v1",
                "detected_at": "2026-05-15T10:30:45Z",
            }
        }


@router.post(
    "/demand",
    deprecated=True,
    summary="Predict Demand (Deprecated)",
    description="DEPRECATED: Use POST /ml/predict-demand instead"
)
async def predict_demand(request: DemandPredictionRequest) -> dict[str, str]:
    """DEPRECATED: Replaced by POST /ml/predict-demand on root ML service."""
    logger.warning("Deprecated /predict/demand endpoint called. Please migrate to /ml/predict-demand.")
    raise HTTPException(
        status_code=410,
        detail="This endpoint is deprecated. Use POST /ml/predict-demand instead."
    )


@router.post(
    "/eta",
    response_model=ETAPredictionResponse,
    summary="Predict ETA",
    description="Predict estimated time of arrival"
)
async def predict_eta(request: ETAPredictionRequest) -> ETAPredictionResponse:
    """
    Predict estimated time of arrival

    Args:
        request: ETAPredictionRequest

    Returns:
        ETAPredictionResponse with predicted ETA
    """
    try:
        logger.info(
            f"Predicting ETA for route ({request.pickup_latitude}, {request.pickup_longitude}) "
            f"-> ({request.destination_latitude}, {request.destination_longitude})"
        )

        eta_seconds = await _eta_model.predict(request)
        estimated_time_minutes = max(0.5, float(eta_seconds) / 60.0)
        confidence = max(0.5, min(1.0, 1.0 - (request.traffic_level * 0.3)))

        return ETAPredictionResponse(
            estimated_time_minutes=estimated_time_minutes,
            distance_km=request.distance_km,
            confidence=confidence,
        )

    except Exception as e:
        logger.error(f"ETA prediction error: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail="Prediction service error"
        )


@router.post(
    "/anomaly",
    response_model=AnomalyDetectionResponse,
    summary="Detect Driver Behavior Anomalies",
    description="Detect anomalous driver behavior from GPS readings using Isolation Forest"
)
async def detect_anomaly(
    request: AnomalyDetectionRequest,
    background_tasks: BackgroundTasks,
) -> AnomalyDetectionResponse:
    """
    Detect anomalous driver behavior from GPS readings.

    Returns severity levels based on anomaly scores:
    - high: score < -0.20
    - medium: -0.20 <= score < -0.05
    - low: score >= -0.05 (only when is_anomaly=true)
    """
    try:
        loader = get_behavior_detector_loader()
    except RuntimeError:
        logger.error("Behavior detector not initialized")
        raise HTTPException(
            status_code=503,
            detail="behavior detector is not loaded"
        )

    if not loader.is_loaded():
        raise HTTPException(
            status_code=503,
            detail="behavior detector is not loaded"
        )

    try:
        # Call behavior detector with feature dict
        is_anomaly, anomaly_score = loader.predict(request.gps_reading.model_dump())

        # Map score to severity
        if is_anomaly:
            if anomaly_score < -0.20:
                severity = "high"
            elif anomaly_score < -0.05:
                severity = "medium"
            else:
                severity = "low"
        else:
            severity = "low"

        # Build response
        detected_at = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
        response = AnomalyDetectionResponse(
            is_anomaly=is_anomaly,
            anomaly_score=anomaly_score,
            severity=severity,
            model_version="behavior_isolation_forest_v1",
            detected_at=detected_at,
        )

        # Log prediction in background
        background_tasks.add_task(
            _log_anomaly_prediction,
            request.model_dump(),
            response.model_dump(),
        )

        return response

    except ValueError as e:
        logger.error(f"Invalid request for anomaly detection: {str(e)}")
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.error(f"Anomaly detection failed: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail="anomaly detection failed")


def _log_anomaly_prediction(input_payload: dict, output_payload: dict) -> None:
    """Log anomaly prediction for monitoring."""
    try:
        logger.info(
            f"Anomaly detection: is_anomaly={output_payload.get('is_anomaly')}, "
            f"severity={output_payload.get('severity')}, "
            f"score={output_payload.get('anomaly_score'):.4f}"
        )
    except Exception as e:
        logger.warning(f"Failed to log anomaly prediction: {str(e)}")
