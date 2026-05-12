"""Integration tests for the deployed ML service (ml-service/main.py)."""
import json
import pytest
from datetime import datetime, timezone
from fastapi.testclient import TestClient

# Import from the deployed main.py entrypoint
import sys
from pathlib import Path

# Add ml-service directory to path
ml_service_dir = Path(__file__).parent.parent
sys.path.insert(0, str(ml_service_dir))

# Import the FastAPI app from the deployed main.py
from main import app


@pytest.fixture
def client():
    """Create test client for deployed ml-service."""
    return TestClient(app)


class TestHealthEndpoint:
    """Tests for the health check endpoint."""

    def test_health_check_returns_200(self, client):
        """Health endpoint always returns 200."""
        response = client.get("/ml/health")
        assert response.status_code == 200

    def test_health_includes_required_fields(self, client):
        """Health response includes status and model loaded info."""
        response = client.get("/ml/health")
        data = response.json()
        assert "status" in data
        assert data["status"] in ("ok", "degraded")
        assert "model_loaded" in data
        assert "database_connected" in data


class TestPredictDemandV2:
    """Tests for V2 LSTM demand prediction endpoint."""

    def test_demand_prediction_valid_input_returns_200(self, client):
        """Valid zone and history returns 200 with 8 forecast steps."""
        payload = {
            "zone_id": "Z01",
            "history": [[0.1] * 17 for _ in range(16)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        # Check model is loaded - if not, we expect 503
        if response.status_code == 503:
            pytest.skip("V2 LSTM model not loaded. Place rideconnect_v2_best.keras in models/")
        
        assert response.status_code == 200, f"Got {response.status_code}: {response.text}"
        data = response.json()

        # Validate response structure
        assert "zone_id" in data
        assert data["zone_id"] == "Z01"
        assert "reference_time" in data
        assert "forecast_steps" in data
        assert len(data["forecast_steps"]) == 8

        # Validate each forecast step
        for step_idx, step in enumerate(data["forecast_steps"], start=1):
            assert "step" in step
            assert step["step"] == step_idx
            assert "timestamp" in step
            assert "predicted_demand" in step

            # Check predicted_demand is non-negative
            assert step["predicted_demand"] >= 0, (
                f"Step {step_idx} has negative predicted_demand: {step['predicted_demand']}"
            )

            # Validate timestamp is ISO format and future
            try:
                step_time = datetime.fromisoformat(step["timestamp"].replace("Z", "+00:00"))
                assert step_time > datetime.now(timezone.utc), (
                    f"Step {step_idx} timestamp is not in future"
                )
            except ValueError as e:
                pytest.fail(f"Step {step_idx} timestamp {step['timestamp']} is not valid ISO format: {e}")

    def test_demand_prediction_timestamps_are_15min_apart(self, client):
        """Forecast steps should be 15 minutes apart."""
        payload = {
            "zone_id": "Z01",
            "history": [[0.1] * 17 for _ in range(16)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        if response.status_code == 503:
            pytest.skip("V2 LSTM model not loaded")

        assert response.status_code == 200
        data = response.json()

        # Parse timestamps
        timestamps = []
        for step in data["forecast_steps"]:
            ts = datetime.fromisoformat(step["timestamp"].replace("Z", "+00:00"))
            timestamps.append(ts)

        # Check that each step is 15 minutes apart
        for i in range(1, len(timestamps)):
            delta = timestamps[i] - timestamps[i - 1]
            assert delta.total_seconds() == 15 * 60, (
                f"Steps {i} and {i+1} are {delta.total_seconds()} seconds apart, "
                f"expected {15*60}"
            )

    def test_demand_prediction_unknown_zone_returns_404(self, client):
        """Unknown zone_id returns 404."""
        payload = {
            "zone_id": "Z99",
            "history": [[0.1] * 17 for _ in range(16)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        # Skip if model not loaded (get 503 instead)
        if response.status_code == 503:
            pytest.skip("V2 LSTM model not loaded")

        assert response.status_code == 404, (
            f"Expected 404 for unknown zone, got {response.status_code}: {response.text}"
        )

    def test_demand_prediction_malformed_history_returns_422(self, client):
        """Malformed history (wrong number of timesteps) returns 400 or 422."""
        # 15 timesteps instead of 16
        payload = {
            "zone_id": "Z01",
            "history": [[0.1] * 17 for _ in range(15)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        # Validation errors can be 400 or 422
        assert response.status_code in (400, 422), (
            f"Expected 400/422 for malformed input, got {response.status_code}: {response.text}"
        )

    def test_demand_prediction_wrong_feature_count_returns_422(self, client):
        """History with wrong feature count (16 instead of 17) returns 400 or 422."""
        # Each timestep has 16 features instead of 17
        payload = {
            "zone_id": "Z01",
            "history": [[0.1] * 16 for _ in range(16)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        assert response.status_code in (400, 422), (
            f"Expected 400/422 for wrong feature count, got {response.status_code}: {response.text}"
        )

    def test_demand_prediction_missing_zone_id_returns_422(self, client):
        """Missing zone_id returns 400 or 422."""
        payload = {
            "history": [[0.1] * 17 for _ in range(16)],
        }
        response = client.post("/ml/predict-demand", json=payload)

        assert response.status_code in (400, 422), (
            f"Expected 400/422 for missing zone_id, got {response.status_code}: {response.text}"
        )


class TestExamplesEndpoint:
    """Tests for the examples endpoint."""

    def test_examples_includes_demand_v2(self, client):
        """Examples endpoint includes V2 LSTM demand example."""
        response = client.get("/ml/examples")
        assert response.status_code == 200
        data = response.json()

        assert "predict_demand_v2" in data
        example = data["predict_demand_v2"]
        assert "zone_id" in example
        assert "history" in example
        assert len(example["history"]) == 16
        assert len(example["history"][0]) == 17


class TestPredictDemandHelpEndpoint:
    """Tests for the demand prediction help endpoint."""

    def test_predict_demand_help_get_returns_200(self, client):
        """GET /ml/predict-demand should return help text."""
        response = client.get("/ml/predict-demand")
        assert response.status_code == 200
        data = response.json()

        assert "detail" in data
        assert data["detail"] == "Use POST for this endpoint."
        assert "contract" in data
        assert "example_payload" in data
