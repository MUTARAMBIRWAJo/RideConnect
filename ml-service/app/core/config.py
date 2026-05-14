"""Configuration for the ML service."""

from __future__ import annotations

import os
from pathlib import Path


def _as_bool(value: str | None, default: bool = False) -> bool:
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def _build_database_url() -> str:
    database_url = os.getenv("DATABASE_URL")
    if database_url:
        return database_url

    db_host = os.getenv("DB_HOST")
    db_port = os.getenv("DB_PORT", "5432")
    db_name = os.getenv("DB_DATABASE", "postgres")
    db_user = os.getenv("DB_USERNAME", "postgres")
    db_password = os.getenv("DB_PASSWORD")
    db_sslmode = os.getenv("DB_SSLMODE", "require")

    if db_host and db_user and db_password:
        return (
            f"postgresql://{db_user}:{db_password}@{db_host}:{db_port}/{db_name}"
            f"?sslmode={db_sslmode}"
        )

    return "postgresql://postgres:postgres@db:5432/rideconnect"


def _normalize_log_level(value: str | None, default: str = "INFO") -> str:
    if value is None:
        return default

    normalized = value.strip()
    if normalized.isdigit():
        return normalized

    return normalized.upper()


_default_model_path = Path(__file__).resolve().parents[2] / "models" / "trained" / "rideconnect_v2_best.keras"


class Settings:
    APP_NAME = os.getenv("APP_NAME", "RideConnect ML Service")
    APP_VERSION = os.getenv("APP_VERSION", os.getenv("VERSION", "1.0.0"))
    VERSION = APP_VERSION
    DEBUG = _as_bool(os.getenv("DEBUG"), _as_bool(os.getenv("APP_DEBUG"), False))

    HOST = os.getenv("HOST", "0.0.0.0")
    PORT = int(os.getenv("PORT", "8000"))

    MODEL_PATH = os.getenv("MODEL_PATH", str(_default_model_path))
    MODEL_DIR = os.getenv("MODEL_DIR", str(_default_model_path.parent))
    MODEL_VERSION = os.getenv("MODEL_VERSION", "v1")
    ACTIVE_MODEL = os.getenv("ACTIVE_MODEL", MODEL_VERSION)
    SCALER_PATH = os.getenv(
        "SCALER_PATH",
        str(Path(MODEL_DIR) / "matcher_v0.joblib"),
    )
    ALLOW_SCALER_FALLBACK = _as_bool(os.getenv("ALLOW_SCALER_FALLBACK"), False)
    INFERENCE_TIMEOUT = float(os.getenv("INFERENCE_TIMEOUT", "5.0"))

    # Behavior anomaly detection model paths
    BEHAVIOR_DETECTOR_PATH = os.getenv(
        "BEHAVIOR_DETECTOR_PATH",
        str(Path(MODEL_DIR) / "behavior_detector.pkl"),
    )
    BEHAVIOR_SCALER_PATH = os.getenv(
        "BEHAVIOR_SCALER_PATH",
        str(Path(MODEL_DIR) / "behavior_scaler.pkl"),
    )
    BEHAVIOR_CONFIG_PATH = os.getenv(
        "BEHAVIOR_CONFIG_PATH",
        str(Path(MODEL_DIR) / "behavior_feature_config.json"),
    )

    DATABASE_URL = _build_database_url()
    SUPABASE_DB_URL = os.getenv("SUPABASE_DB_URL", DATABASE_URL)
    REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/0")

    SUPABASE_URL = os.getenv("SUPABASE_URL", "")
    SUPABASE_KEY = os.getenv("SUPABASE_KEY", "")
    SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY", "")
    SUPABASE_JWT_SECRET = os.getenv("SUPABASE_JWT_SECRET", "")

    LOG_LEVEL = _normalize_log_level(os.getenv("LOG_LEVEL", "INFO"))
    ENABLE_CACHING = _as_bool(os.getenv("ENABLE_CACHING"), True)
    CACHE_TTL = int(os.getenv("CACHE_TTL", "3600"))

    SECRET_KEY = os.getenv("SECRET_KEY", os.getenv("APP_KEY", "change-me"))

    # Distance and normalization settings
    DISTANCE_MAX_KM = float(os.getenv("DISTANCE_MAX_KM", "10.0"))
    RATING_MIN = float(os.getenv("RATING_MIN", "1.0"))
    RATING_MAX = float(os.getenv("RATING_MAX", "5.0"))
    ACCEPTANCE_RATE_MAX = float(os.getenv("ACCEPTANCE_RATE_MAX", "1.0"))
    CANCELLATION_RATE_MAX = float(os.getenv("CANCELLATION_RATE_MAX", "1.0"))
    BEHAVIOR_SCORE_MAX = float(os.getenv("BEHAVIOR_SCORE_MAX", "1.0"))
    TRAFFIC_LEVEL_MAX = float(os.getenv("TRAFFIC_LEVEL_MAX", "1.0"))


settings = Settings()
