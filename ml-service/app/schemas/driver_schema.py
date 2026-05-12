"""Driver schema"""
from pydantic import BaseModel, Field


class DriverProfile(BaseModel):
    """Schema for driver profile"""
    
    driver_id: int = Field(..., description="Driver identifier")
    name: str = Field(..., description="Driver name")
    rating: float = Field(..., description="Driver rating")
    accepted_rides: int = Field(..., description="Number of accepted rides")
    completed_rides: int = Field(..., description="Number of completed rides")
    
    class Config:
        json_schema_extra = {
            "example": {
                "driver_id": 1,
                "name": "John Doe",
                "rating": 4.8,
                "accepted_rides": 250,
                "completed_rides": 248,
            }
        }
