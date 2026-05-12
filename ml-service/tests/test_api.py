"""Integration tests for ML service"""
import os

import pytest
from fastapi.testclient import TestClient
from app.main import app


@pytest.fixture
def client():
    """Create test client"""
    return TestClient(app)


class TestHealthEndpoint:
    """Tests for health check endpoint"""
    
    def test_health_check_success(self, client):
        """Test successful health check"""
        response = client.get("/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert "version" in data
        assert "model_loaded" in data


class TestMatchingEndpoint:
    """Tests for driver matching endpoint"""
    
    def test_match_driver_success(self, client):
        """Test successful driver matching"""
        payload = {
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
        
        response = client.post("/predict/match-driver", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert "best_driver" in data
        assert "ranked_drivers" in data
        assert data["best_driver"]["driver_id"] == 1
        assert 0 <= data["best_driver"]["score"] <= 1
    
    def test_match_driver_invalid_coordinates(self, client):
        """Test with invalid coordinates"""
        payload = {
            "ride_request": {
                "pickup_latitude": 100,  # Invalid
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
        
        response = client.post("/predict/match-driver", json=payload)
        assert response.status_code == 422  # Validation error
    
    def test_match_driver_empty_candidates(self, client):
        """Test with no candidate drivers"""
        payload = {
            "ride_request": {
                "pickup_latitude": -1.9441,
                "pickup_longitude": 30.0619,
                "destination_latitude": -1.9536,
                "destination_longitude": 30.1044,
                "requested_vehicle_type": "car",
                "required_seats": 3,
            },
            "candidate_drivers": [],
        }
        
        response = client.post("/predict/match-driver", json=payload)
        assert response.status_code == 422  # Validation error


class TestDemandPrediction:
    """Tests for demand prediction"""
    
    def test_demand_prediction_success(self, client):
        """Test successful demand prediction"""
        payload = {
            "latitude": -1.9441,
            "longitude": 30.0619,
            "hour": 14,
            "day_of_week": 2,
        }
        
        response = client.post("/predict/demand", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert "demand_level" in data
        assert "expected_wait_time_minutes" in data
        assert "confidence" in data
        assert 0 <= data["demand_level"] <= 1
        assert data["expected_wait_time_minutes"] >= 0


class TestETAPrediction:
    """Tests for ETA prediction"""
    
    def test_eta_prediction_success(self, client):
        """Test successful ETA prediction"""
        payload = {
            "pickup_latitude": -1.9441,
            "pickup_longitude": 30.0619,
            "destination_latitude": -1.9536,
            "destination_longitude": 30.1044,
            "traffic_level": 0.3,
            "distance_km": 2.5,
        }
        
        response = client.post("/predict/eta", json=payload)
        assert response.status_code == 200
        data = response.json()
        assert "estimated_time_minutes" in data
        assert "distance_km" in data
        assert "confidence" in data
        assert data["estimated_time_minutes"] > 0


class TestAdminWeightAuditEndpoint:
    """Tests for admin weight audit endpoint."""

    def test_weight_audit_logs_success(self, client, monkeypatch):
        """Test audit log retrieval with admin token."""
        monkeypatch.setattr(
            "app.api.admin_routes.get_weight_audit_logs",
            lambda limit=50, offset=0: [
                {
                    "id": 1,
                    "actor": "admin",
                    "payload": {"distance": 0.35},
                    "created_at": "2026-05-11T00:00:00+00:00",
                }
            ],
        )

        headers = {"x-admin-token": os.getenv("APP_KEY", "change-me")}
        response = client.get("/api/admin/weights/audit", headers=headers)

        assert response.status_code == 200
        data = response.json()
        assert data["total"] == 1
        assert data["items"][0]["actor"] == "admin"
