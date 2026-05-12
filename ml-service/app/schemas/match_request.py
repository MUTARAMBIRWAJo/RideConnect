"""Pydantic schemas for API requests and responses"""
from typing import Optional

from pydantic import BaseModel, Field


class CandidateDriver(BaseModel):
    """Schema for candidate driver in matching request"""
    
    driver_id: int = Field(..., gt=0, description="Unique driver identifier")
    distance_km: float = Field(..., ge=0, description="Distance from pickup location in km")
    driver_rating: float = Field(..., ge=1.0, le=5.0, description="Driver rating (1.0-5.0)")
    acceptance_rate: float = Field(..., ge=0, le=100, description="Driver acceptance rate (0-100)")
    cancellation_rate: float = Field(..., ge=0, le=100, description="Driver cancellation rate (0-100)")
    behavior_score: float = Field(..., ge=0, le=100, description="Driver behavior score (0-100)")
    available_seats: int = Field(..., ge=1, description="Available seats in vehicle")
    traffic_level: float = Field(..., ge=0.0, le=1.0, description="Traffic level on driver's route (0.0-1.0)")
    direction_similarity: float = Field(..., ge=0.0, le=1.0, description="Similarity to trip direction (0.0-1.0)")
    
    class Config:
        json_schema_extra = {
            "example": {
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
        }


class RideRequest(BaseModel):
    """Schema for ride request in matching request"""
    
    pickup_latitude: float = Field(..., ge=-90, le=90, description="Pickup location latitude")
    pickup_longitude: float = Field(..., ge=-180, le=180, description="Pickup location longitude")
    destination_latitude: float = Field(..., ge=-90, le=90, description="Destination latitude")
    destination_longitude: float = Field(..., ge=-180, le=180, description="Destination longitude")
    requested_vehicle_type: str = Field(..., description="Required vehicle type (car, motorbike, etc.)")
    required_seats: int = Field(..., ge=1, le=8, description="Required number of seats")
    
    class Config:
        json_schema_extra = {
            "example": {
                "pickup_latitude": -1.9441,
                "pickup_longitude": 30.0619,
                "destination_latitude": -1.9536,
                "destination_longitude": 30.1044,
                "requested_vehicle_type": "car",
                "required_seats": 3,
            }
        }


class MatchRequestPayload(BaseModel):
    """Schema for driver matching request"""
    
    ride_request: RideRequest = Field(..., description="Ride request details")
    candidate_drivers: list[CandidateDriver] = Field(..., min_items=1, description="List of candidate drivers")
    
    class Config:
        json_schema_extra = {
            "example": {
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
        }
