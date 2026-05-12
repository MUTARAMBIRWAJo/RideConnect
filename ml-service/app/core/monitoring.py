"""Optional production monitoring hooks for Prometheus, OpenTelemetry, and Azure Monitor."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Optional

from app.core.logging import get_logger

logger = get_logger(__name__)


@dataclass
class MonitoringState:
    """Tracks which monitoring integrations are active."""

    prometheus_enabled: bool = False
    opentelemetry_enabled: bool = False
    azure_monitor_enabled: bool = False
    prometheus_registry: Optional[Any] = None
    tracer_provider: Optional[Any] = None


_monitoring_state = MonitoringState()


def initialize_monitoring() -> MonitoringState:
    """
    Initialize optional monitoring integrations.

    This enables production observability without making the service fail if
    a particular exporter package is absent in the runtime image.
    """
    global _monitoring_state

    _monitoring_state = MonitoringState()

    _initialize_prometheus()
    _initialize_opentelemetry()
    _initialize_azure_monitor()

    logger.info(
        "Monitoring initialized",
        extra={
            "prometheus_enabled": _monitoring_state.prometheus_enabled,
            "opentelemetry_enabled": _monitoring_state.opentelemetry_enabled,
            "azure_monitor_enabled": _monitoring_state.azure_monitor_enabled,
        },
    )
    return _monitoring_state


def get_monitoring_state() -> MonitoringState:
    """Return the current monitoring state."""
    return _monitoring_state


def _initialize_prometheus() -> None:
    """Initialize Prometheus metrics if the client library is available."""
    try:
        import prometheus_client  # type: ignore

        _monitoring_state.prometheus_enabled = True
        _monitoring_state.prometheus_registry = prometheus_client.REGISTRY
        logger.info("Prometheus client initialized")
    except Exception as exc:
        logger.warning(f"Prometheus monitoring unavailable: {exc}")


def _initialize_opentelemetry() -> None:
    """Initialize OpenTelemetry tracing if the SDK is available."""
    try:
        from opentelemetry import trace  # type: ignore
        from opentelemetry.sdk.resources import Resource  # type: ignore
        from opentelemetry.sdk.trace import TracerProvider  # type: ignore

        provider = TracerProvider(resource=Resource.create({"service.name": "rideconnect-ml-service"}))
        trace.set_tracer_provider(provider)
        _monitoring_state.opentelemetry_enabled = True
        _monitoring_state.tracer_provider = provider
        logger.info("OpenTelemetry tracing initialized")
    except Exception as exc:
        logger.warning(f"OpenTelemetry monitoring unavailable: {exc}")


def _initialize_azure_monitor() -> None:
    """Initialize Azure Monitor / Application Insights hooks if available."""
    try:
        from azure.monitor.opentelemetry import configure_azure_monitor  # type: ignore

        configure_azure_monitor()
        _monitoring_state.azure_monitor_enabled = True
        logger.info("Azure Monitor initialized")
    except Exception as exc:
        logger.warning(f"Azure Monitor unavailable: {exc}")
