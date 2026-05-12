"""Additional prediction endpoints"""
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from app.core.logging import get_logger
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
