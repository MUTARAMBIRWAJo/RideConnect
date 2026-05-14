"""Health check endpoint"""
from fastapi import APIRouter, HTTPException

from app.core.config import settings
from app.core.logging import get_logger
from app.core.startup import get_model_loader
from app.core.scaler_manager import get_scaler_manager
from app.schemas.match_response import HealthResponse
from app.services.behavior_detector_loader import get_behavior_detector_loader

logger = get_logger(__name__)
router = APIRouter(tags=["health"])


@router.get(
    "/health",
    response_model=HealthResponse,
    summary="Health Check",
    description="Check service health and model status"
)
async def health_check() -> HealthResponse:
    """
    Health check endpoint

    Returns:
        HealthResponse with service status
    """
    try:
        model_loader = get_model_loader()
        model_info = model_loader.get_model_info()
        try:
            scaler_manager = get_scaler_manager()
            scaler_loaded = scaler_manager.is_loaded
        except Exception:
            scaler_loaded = False

        try:
            behavior_loader = get_behavior_detector_loader()
            behavior_model_loaded = behavior_loader.is_loaded()
        except Exception:
            behavior_model_loaded = False

        return HealthResponse(
            status="healthy",
            version=settings.APP_VERSION,
            model_loaded=model_info.get("loaded", False),
            model_input_shape=model_info.get("input_shape"),
            model_output_shape=model_info.get("output_shape"),
            scaler_loaded=scaler_loaded,
            behavior_model_loaded=behavior_model_loaded,
            tensorflow_version=model_info.get("tensorflow_version"),
            uptime_seconds=model_info.get("uptime_seconds", 0.0),
            available_devices=model_info.get("available_devices", []),
        )

    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        raise HTTPException(
            status_code=503,
            detail="Service unavailable"
        )
