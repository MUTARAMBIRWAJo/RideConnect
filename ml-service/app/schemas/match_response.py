"""Pydantic schemas for API responses"""
from typing import Optional

from pydantic import BaseModel, Field


class RankedDriver(BaseModel):
    """Schema for ranked driver in response"""
    
    driver_id: int = Field(..., description="Driver identifier")
    score: float = Field(..., ge=0.0, le=1.0, description="Match score (0.0-1.0)")
    
    class Config:
        json_schema_extra = {
            "example": {
                "driver_id": 1,
                "score": 0.97,
            }
        }


class BestDriver(BaseModel):
    """Schema for best driver in response"""
    
    driver_id: int = Field(..., description="Best driver identifier")
    score: float = Field(..., ge=0.0, le=1.0, description="Best match score (0.0-1.0)")
    
    class Config:
        json_schema_extra = {
            "example": {
                "driver_id": 1,
                "score": 0.97,
            }
        }


class MatchDriverResponse(BaseModel):
    """Schema for driver matching response"""
    
    best_driver: BestDriver = Field(..., description="Best matching driver")
    ranked_drivers: list[RankedDriver] = Field(..., description="All drivers ranked by score")
    
    class Config:
        json_schema_extra = {
            "example": {
                "best_driver": {
                    "driver_id": 1,
                    "score": 0.97,
                },
                "ranked_drivers": [
                    {
                        "driver_id": 1,
                        "score": 0.97,
                    },
                    {
                        "driver_id": 2,
                        "score": 0.85,
                    },
                ],
            }
        }


class HealthResponse(BaseModel):
    """Schema for health check response"""
    
    status: str = Field(..., description="Service status")
    version: str = Field(..., description="Service version")
    model_loaded: bool = Field(..., description="Whether model is loaded")
    model_input_shape: Optional[list] = Field(None, description="Model input shape")
    model_output_shape: Optional[list] = Field(None, description="Model output shape")
    scaler_loaded: bool = Field(..., description="Whether scaler is loaded")
    tensorflow_version: Optional[str] = Field(None, description="TensorFlow version")
    uptime_seconds: float = Field(0.0, description="Service uptime in seconds")
    available_devices: list[str] = Field(default_factory=list, description="Available TensorFlow devices")
    
    class Config:
        json_schema_extra = {
            "example": {
                "status": "healthy",
                "version": "1.0.0",
                "model_loaded": True,
            }
        }


class ErrorResponse(BaseModel):
    """Schema for error response"""
    
    detail: str = Field(..., description="Error message")
    error_code: Optional[str] = Field(None, description="Error code")
    
    class Config:
        json_schema_extra = {
            "example": {
                "detail": "Invalid request",
                "error_code": "VALIDATION_ERROR",
            }
        }
