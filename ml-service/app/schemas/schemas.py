from pydantic import BaseModel
from typing import Optional, List


class RideRequest(BaseModel):
    pickup_latitude: float
    pickup_longitude: float
    destination_latitude: Optional[float] = None
    destination_longitude: Optional[float] = None
    pickup_heading: Optional[float] = 0.0
    requested_vehicle_type: Optional[str] = None
    requested_seats: Optional[int] = 1


class DriverCandidate(BaseModel):
    driver_id: int
    current_latitude: float
    current_longitude: float
    current_heading: Optional[float] = 0.0
    driver_rating: Optional[float] = 4.5
    acceptance_rate: Optional[float] = 0.9
    cancellation_rate: Optional[float] = 0.01
    behavior_score: Optional[float] = 0.8
    available_seats: Optional[int] = 4
    vehicle_type: Optional[str] = "car"


class RankedDriver(BaseModel):
    driver_id: int
    score: float
    eta_seconds: int
    distance_km: float


class MatchResponse(BaseModel):
    best_driver_id: int
    ranked_drivers: List[RankedDriver]


class DemandRequest(BaseModel):
    zone: str
    timestamp: str


class DemandResponse(BaseModel):
    predicted_demand: float
    surge_probability: float


class ETARequest(BaseModel):
    pickup_latitude: float
    pickup_longitude: float
    destination_latitude: float
    destination_longitude: float
    traffic_level: Optional[float] = 0.0


class ETAResponse(BaseModel):
    eta_seconds: int
