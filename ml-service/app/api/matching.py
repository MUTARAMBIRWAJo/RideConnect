"""Driver matching endpoint"""
from fastapi import APIRouter, HTTPException, Query

from app.core.logging import get_logger
from app.schemas.match_request import MatchRequestPayload
from app.schemas.match_response import ErrorResponse, MatchDriverResponse
from app.services.matching_service import MatchingService

logger = get_logger(__name__)
router = APIRouter(prefix="/predict", tags=["predictions"])

# Initialize service
matching_service = MatchingService()


@router.post(
    "/match-driver",
    response_model=MatchDriverResponse,
    responses={
        400: {"model": ErrorResponse},
        422: {"model": ErrorResponse},
        500: {"model": ErrorResponse},
    },
    summary="Match Driver to Ride",
    description="Find the best matching driver for a ride request using ML model"
)
async def match_driver(
    request: MatchRequestPayload,
    enable_timing: bool = Query(False, description="Enable timing metrics in logs")
) -> MatchDriverResponse:
    """
    Match drivers to a ride request
    
    Args:
        request: MatchRequestPayload with ride request and candidate drivers
        enable_timing: Whether to log timing metrics
    
    Returns:
        MatchDriverResponse with best driver and ranking
    
    Raises:
        HTTPException: If validation or prediction fails
    """
    try:
        logger.info(
            f"Matching request for {len(request.candidate_drivers)} drivers"
        )
        
        # Perform matching
        response = matching_service.match_drivers(
            request,
            enable_timing=enable_timing
        )
        
        logger.info(
            f"Matching complete. Best driver: {response.best_driver.driver_id} "
            f"(score: {response.best_driver.score:.4f})"
        )
        
        return response
    
    except ValueError as e:
        logger.warning(f"Validation error: {str(e)}")
        raise HTTPException(
            status_code=400,
            detail=str(e)
        )
    
    except RuntimeError as e:
        logger.error(f"Model error: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail="Prediction service error"
        )
    
    except Exception as e:
        logger.error(f"Unexpected error in matching: {str(e)}")
        raise HTTPException(
            status_code=500,
            detail="Internal server error"
        )
