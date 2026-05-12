"""Comprehensive test suite for ML inference pipeline."""

import pytest
import numpy as np
from unittest.mock import patch, MagicMock

from app.core.feature_config import (
    FEATURE_COLUMNS,
    EXPECTED_FEATURE_COUNT,
    validate_feature_order,
    validate_feature_bounds,
    get_feature_index,
)
from app.services.validators import (
    InputValidator,
    FeatureDriftDetector,
    CoordinateValidator,
)


class TestFeatureConfig:
    """Tests for feature configuration."""
    
    def test_feature_count(self):
        """Test correct feature count."""
        assert EXPECTED_FEATURE_COUNT == 10
        assert len(FEATURE_COLUMNS) == 10
    
    def test_feature_order(self):
        """Test feature column order."""
        expected = [
            "distance_km", "driver_rating", "acceptance_rate",
            "cancellation_rate", "behavior_score", "available_seats",
            "traffic_level", "direction_similarity",
            "seat_compatibility", "vehicle_compatibility",
        ]
        assert FEATURE_COLUMNS == expected
    
    def test_validate_feature_order_valid(self):
        """Test feature order validation with valid features."""
        assert validate_feature_order(FEATURE_COLUMNS) is True
    
    def test_validate_feature_order_count_mismatch(self):
        """Test feature order validation with wrong count."""
        with pytest.raises(ValueError):
            validate_feature_order(FEATURE_COLUMNS[:-1])
    
    def test_validate_feature_order_sequence_mismatch(self):
        """Test feature order validation with wrong sequence."""
        wrong_order = FEATURE_COLUMNS[:1] + [FEATURE_COLUMNS[2], FEATURE_COLUMNS[1]] + FEATURE_COLUMNS[3:]
        with pytest.raises(ValueError):
            validate_feature_order(wrong_order)
    
    def test_validate_feature_bounds_valid(self):
        """Test feature bounds validation with valid value."""
        assert validate_feature_bounds("distance_km", 25.0) is True
        assert validate_feature_bounds("driver_rating", 4.5) is True
    
    def test_validate_feature_bounds_invalid(self):
        """Test feature bounds validation with invalid value."""
        with pytest.raises(ValueError):
            validate_feature_bounds("distance_km", 100.0)  # > 50
    
    def test_get_feature_index(self):
        """Test feature index lookup."""
        assert get_feature_index("distance_km") == 0
        assert get_feature_index("vehicle_compatibility") == 9
    
    def test_get_feature_index_invalid(self):
        """Test feature index lookup for non-existent feature."""
        with pytest.raises(ValueError):
            get_feature_index("nonexistent_feature")


class TestInputValidator:
    """Tests for input validation."""
    
    def test_validate_array_valid(self):
        """Test validation of valid array."""
        features = np.array([[1.0, 2.0, 3.0]])
        assert InputValidator.validate_array(features) is True
    
    def test_validate_array_with_nan(self):
        """Test validation of array with NaN."""
        features = np.array([[1.0, np.nan, 3.0]])
        with pytest.raises(ValueError):
            InputValidator.validate_array(features)
    
    def test_validate_array_with_inf(self):
        """Test validation of array with infinity."""
        features = np.array([[1.0, np.inf, 3.0]])
        with pytest.raises(ValueError):
            InputValidator.validate_array(features)
    
    def test_validate_scalar_valid(self):
        """Test validation of valid scalar."""
        assert InputValidator.validate_scalar(5.0) is True
    
    def test_validate_scalar_nan(self):
        """Test validation of NaN scalar."""
        with pytest.raises(ValueError):
            InputValidator.validate_scalar(np.nan)
    
    def test_validate_scalar_inf(self):
        """Test validation of infinite scalar."""
        with pytest.raises(ValueError):
            InputValidator.validate_scalar(np.inf)
    
    def test_validate_shape_valid(self):
        """Test shape validation for valid array."""
        features = np.zeros((5, 10))
        assert InputValidator.validate_shape(features, (5, 10)) is True
    
    def test_validate_shape_invalid(self):
        """Test shape validation for mismatched array."""
        features = np.zeros((5, 10))
        with pytest.raises(ValueError):
            InputValidator.validate_shape(features, (5, 20))


class TestFeatureDriftDetector:
    """Tests for feature drift detection."""
    
    def test_detect_drift_valid_features(self):
        """Test drift detection with valid features."""
        features = {
            "distance_km": 25.0,
            "driver_rating": 4.5,
            "acceptance_rate": 85.0,
        }
        result = FeatureDriftDetector.detect_drift(features, warn_on_drift=False)
        assert len(result["out_of_bounds"]) == 0
    
    def test_detect_drift_out_of_bounds(self):
        """Test drift detection with out-of-bounds feature."""
        features = {
            "distance_km": 100.0,  # > 50.0 (out of bounds)
            "driver_rating": 4.5,
        }
        result = FeatureDriftDetector.detect_drift(features, warn_on_drift=False)
        assert "distance_km" in result["out_of_bounds"]
    
    def test_validate_and_detect_drift_strict_mode(self):
        """Test strict mode drift validation."""
        features = {
            "distance_km": 100.0,  # Out of bounds
            "driver_rating": 4.5,
        }
        with pytest.raises(ValueError):
            FeatureDriftDetector.validate_and_detect_drift(features, strict_mode=True)
    
    def test_validate_and_detect_drift_loose_mode(self):
        """Test loose mode drift validation."""
        features = {
            "distance_km": 100.0,  # Out of bounds
            "driver_rating": 4.5,
        }
        # Should not raise in loose mode
        assert FeatureDriftDetector.validate_and_detect_drift(features, strict_mode=False) is True


class TestCoordinateValidator:
    """Tests for coordinate validation."""
    
    def test_validate_latitude_valid(self):
        """Test validation of valid latitude."""
        assert CoordinateValidator.validate_latitude(0.0) is True
        assert CoordinateValidator.validate_latitude(45.0) is True
        assert CoordinateValidator.validate_latitude(-45.0) is True
    
    def test_validate_latitude_invalid(self):
        """Test validation of invalid latitude."""
        with pytest.raises(ValueError):
            CoordinateValidator.validate_latitude(91.0)
        with pytest.raises(ValueError):
            CoordinateValidator.validate_latitude(-91.0)
    
    def test_validate_longitude_valid(self):
        """Test validation of valid longitude."""
        assert CoordinateValidator.validate_longitude(0.0) is True
        assert CoordinateValidator.validate_longitude(90.0) is True
        assert CoordinateValidator.validate_longitude(-90.0) is True
    
    def test_validate_longitude_invalid(self):
        """Test validation of invalid longitude."""
        with pytest.raises(ValueError):
            CoordinateValidator.validate_longitude(181.0)
        with pytest.raises(ValueError):
            CoordinateValidator.validate_longitude(-181.0)
    
    def test_validate_coordinates(self):
        """Test validation of coordinate pair."""
        assert CoordinateValidator.validate_coordinates(45.0, 90.0) is True
    
    def test_validate_coordinates_invalid(self):
        """Test validation of invalid coordinate pair."""
        with pytest.raises(ValueError):
            CoordinateValidator.validate_coordinates(91.0, 90.0)


class TestMetrics:
    """Tests for metrics collection."""
    
    def test_metrics_builder(self):
        """Test metrics builder."""
        from app.services.metrics import MetricsBuilder
        
        builder = MetricsBuilder(
            request_id="req-123",
            endpoint="/predict",
            batch_size=5
        )
        
        metrics = (builder
                   .set_preprocessing_time(10.0)
                   .set_scaler_time(5.0)
                   .set_inference_time(20.0)
                   .set_postprocessing_time(5.0)
                   .set_output_shape((5, 1))
                   .build())
        
        assert metrics.request_id == "req-123"
        assert metrics.batch_size == 5
        assert metrics.total_time_ms == 40.0
    
    def test_metrics_collector(self):
        """Test metrics collector."""
        from app.services.metrics import InferenceMetricsCollector, InferenceMetrics
        
        collector = InferenceMetricsCollector()
        
        metrics = InferenceMetrics(
            request_id="req-1",
            endpoint="/predict",
            batch_size=5,
            preprocessing_time_ms=10.0,
            scaler_time_ms=5.0,
            model_inference_time_ms=20.0,
            postprocessing_time_ms=5.0,
            total_time_ms=40.0,
            model_output_shape=(5, 1),
        )
        
        collector.record_metrics(metrics)
        
        stats = collector.get_stats()
        assert stats["total_requests"] == 1
        assert stats["total_errors"] == 0
        assert stats["average_time_ms"] == 40.0


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
