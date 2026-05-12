"""Utility functions for distance calculations"""
import math
from typing import Tuple


def haversine_distance(
    lat1: float, lon1: float, lat2: float, lon2: float
) -> float:
    """
    Calculate the great circle distance between two points on the earth (in km)
    
    Args:
        lat1: Latitude of point 1
        lon1: Longitude of point 1
        lat2: Latitude of point 2
        lon2: Longitude of point 2
    
    Returns:
        Distance in kilometers
    """
    R = 6371  # Earth radius in kilometers
    
    lat1_rad = math.radians(lat1)
    lon1_rad = math.radians(lon1)
    lat2_rad = math.radians(lat2)
    lon2_rad = math.radians(lon2)
    
    dlat = lat2_rad - lat1_rad
    dlon = lon2_rad - lon1_rad
    
    a = (
        math.sin(dlat / 2) ** 2
        + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon / 2) ** 2
    )
    c = 2 * math.asin(math.sqrt(a))
    
    return R * c


def normalize_distance(distance_km: float, max_distance: float = 50.0) -> float:
    """
    Normalize distance to 0-1 range
    
    Args:
        distance_km: Distance in kilometers
        max_distance: Maximum distance for normalization
    
    Returns:
        Normalized distance (0-1)
    """
    return min(distance_km / max_distance, 1.0)


def calculate_bearing(
    lat1: float, lon1: float, lat2: float, lon2: float
) -> float:
    """
    Calculate bearing from point 1 to point 2 (in degrees)
    
    Args:
        lat1: Latitude of point 1
        lon1: Longitude of point 1
        lat2: Latitude of point 2
        lon2: Longitude of point 2
    
    Returns:
        Bearing in degrees (0-360)
    """
    lat1_rad = math.radians(lat1)
    lat2_rad = math.radians(lat2)
    dlon = math.radians(lon2 - lon1)
    
    x = math.sin(dlon) * math.cos(lat2_rad)
    y = math.cos(lat1_rad) * math.sin(lat2_rad) - (
        math.sin(lat1_rad) * math.cos(lat2_rad) * math.cos(dlon)
    )
    
    bearing = math.atan2(x, y)
    bearing = math.degrees(bearing)
    bearing = (bearing + 360) % 360
    
    return bearing


def angular_distance(bearing1: float, bearing2: float) -> float:
    """
    Calculate smallest angular distance between two bearings
    
    Args:
        bearing1: First bearing in degrees
        bearing2: Second bearing in degrees
    
    Returns:
        Angular distance in degrees (0-180)
    """
    diff = abs(bearing1 - bearing2)
    return min(diff, 360 - diff)
