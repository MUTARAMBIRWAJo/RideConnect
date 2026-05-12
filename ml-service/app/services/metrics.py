"""Metrics collection and monitoring for inference pipeline."""

from __future__ import annotations

import time
from dataclasses import dataclass, asdict
from typing import Optional

from app.core.logging import get_logger

logger = get_logger(__name__)


@dataclass
class InferenceMetrics:
    """Metrics for a single inference request."""
    
    request_id: str
    endpoint: str
    batch_size: int
    preprocessing_time_ms: float
    scaler_time_ms: float
    model_inference_time_ms: float
    postprocessing_time_ms: float
    total_time_ms: float
    model_output_shape: tuple
    num_candidates: Optional[int] = None
    error: Optional[str] = None
    
    def to_dict(self) -> dict:
        """Convert to dictionary for logging."""
        return asdict(self)


class InferenceMetricsCollector:
    """Collects and aggregates inference metrics."""
    
    def __init__(self):
        """Initialize metrics collector."""
        self.total_requests = 0
        self.total_errors = 0
        self.cumulative_time_ms = 0.0
        self.min_time_ms = float('inf')
        self.max_time_ms = 0.0
        self.recent_metrics: list[InferenceMetrics] = []
        self.max_recent = 100  # Keep last 100 requests
    
    def record_metrics(self, metrics: InferenceMetrics) -> None:
        """
        Record inference metrics.
        
        Args:
            metrics: InferenceMetrics to record
        """
        self.total_requests += 1
        
        if metrics.error:
            self.total_errors += 1
        else:
            self.cumulative_time_ms += metrics.total_time_ms
            self.min_time_ms = min(self.min_time_ms, metrics.total_time_ms)
            self.max_time_ms = max(self.max_time_ms, metrics.total_time_ms)
        
        # Keep recent metrics
        self.recent_metrics.append(metrics)
        if len(self.recent_metrics) > self.max_recent:
            self.recent_metrics.pop(0)
        
        # Log metrics
        logger.info(
            f"Inference metrics: batch_size={metrics.batch_size} "
            f"total_time={metrics.total_time_ms:.1f}ms "
            f"preprocessing={metrics.preprocessing_time_ms:.1f}ms "
            f"inference={metrics.model_inference_time_ms:.1f}ms",
            extra={
                "request_id": metrics.request_id,
                "batch_size": metrics.batch_size,
                "total_time_ms": metrics.total_time_ms,
                "preprocessing_ms": metrics.preprocessing_time_ms,
                "inference_ms": metrics.model_inference_time_ms,
            }
        )
    
    def get_average_time_ms(self) -> float:
        """Get average inference time in milliseconds."""
        if self.total_requests - self.total_errors == 0:
            return 0.0
        return self.cumulative_time_ms / (self.total_requests - self.total_errors)
    
    def get_error_rate(self) -> float:
        """Get error rate as percentage."""
        if self.total_requests == 0:
            return 0.0
        return (self.total_errors / self.total_requests) * 100
    
    def get_stats(self) -> dict:
        """Get aggregated statistics."""
        stats = {
            "total_requests": self.total_requests,
            "total_errors": self.total_errors,
            "error_rate_percent": self.get_error_rate(),
            "average_time_ms": self.get_average_time_ms(),
            "min_time_ms": self.min_time_ms if self.min_time_ms != float('inf') else None,
            "max_time_ms": self.max_time_ms,
            "cumulative_time_ms": self.cumulative_time_ms,
        }
        return stats


class InferenceTimer:
    """Context manager for timing inference stages."""
    
    def __init__(self, stage_name: str):
        """
        Initialize timer.
        
        Args:
            stage_name: Name of stage being timed
        """
        self.stage_name = stage_name
        self.start_time = None
        self.elapsed_ms = 0.0
    
    def __enter__(self) -> InferenceTimer:
        """Start timer."""
        self.start_time = time.time()
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb) -> None:
        """Stop timer and log."""
        if self.start_time is not None:
            self.elapsed_ms = (time.time() - self.start_time) * 1000
            if exc_type is not None:
                logger.warning(
                    f"{self.stage_name} failed after {self.elapsed_ms:.1f}ms"
                )


class MetricsBuilder:
    """Builder for constructing InferenceMetrics."""
    
    def __init__(self, request_id: str, endpoint: str, batch_size: int):
        """
        Initialize builder.
        
        Args:
            request_id: Request correlation ID
            endpoint: API endpoint
            batch_size: Number of items in batch
        """
        self.request_id = request_id
        self.endpoint = endpoint
        self.batch_size = batch_size
        self.preprocessing_time_ms = 0.0
        self.scaler_time_ms = 0.0
        self.model_inference_time_ms = 0.0
        self.postprocessing_time_ms = 0.0
        self.model_output_shape = None
        self.num_candidates = None
        self.error = None
    
    def set_preprocessing_time(self, time_ms: float) -> MetricsBuilder:
        """Set preprocessing time."""
        self.preprocessing_time_ms = time_ms
        return self
    
    def set_scaler_time(self, time_ms: float) -> MetricsBuilder:
        """Set scaler time."""
        self.scaler_time_ms = time_ms
        return self
    
    def set_inference_time(self, time_ms: float) -> MetricsBuilder:
        """Set model inference time."""
        self.model_inference_time_ms = time_ms
        return self
    
    def set_postprocessing_time(self, time_ms: float) -> MetricsBuilder:
        """Set postprocessing time."""
        self.postprocessing_time_ms = time_ms
        return self
    
    def set_output_shape(self, shape: tuple) -> MetricsBuilder:
        """Set model output shape."""
        self.model_output_shape = shape
        return self
    
    def set_num_candidates(self, num: int) -> MetricsBuilder:
        """Set number of candidates processed."""
        self.num_candidates = num
        return self
    
    def set_error(self, error_msg: str) -> MetricsBuilder:
        """Set error message."""
        self.error = error_msg
        return self
    
    def build(self) -> InferenceMetrics:
        """
        Build InferenceMetrics.
        
        Returns:
            InferenceMetrics instance
        """
        total_time = (
            self.preprocessing_time_ms +
            self.scaler_time_ms +
            self.model_inference_time_ms +
            self.postprocessing_time_ms
        )
        
        return InferenceMetrics(
            request_id=self.request_id,
            endpoint=self.endpoint,
            batch_size=self.batch_size,
            preprocessing_time_ms=self.preprocessing_time_ms,
            scaler_time_ms=self.scaler_time_ms,
            model_inference_time_ms=self.model_inference_time_ms,
            postprocessing_time_ms=self.postprocessing_time_ms,
            total_time_ms=total_time,
            model_output_shape=self.model_output_shape,
            num_candidates=self.num_candidates,
            error=self.error,
        )


# Global metrics collector instance
_metrics_collector: Optional[InferenceMetricsCollector] = None


def get_metrics_collector() -> InferenceMetricsCollector:
    """
    Get the global metrics collector.
    
    Returns:
        InferenceMetricsCollector instance
        
    Raises:
        RuntimeError: If not initialized
    """
    global _metrics_collector
    if _metrics_collector is None:
        raise RuntimeError("Metrics collector not initialized")
    return _metrics_collector


def initialize_metrics_collector() -> InferenceMetricsCollector:
    """
    Initialize the global metrics collector.
    
    Returns:
        Initialized InferenceMetricsCollector
    """
    global _metrics_collector
    _metrics_collector = InferenceMetricsCollector()
    return _metrics_collector
