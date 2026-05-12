import json
import logging
import os
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import psycopg2
import tensorflow as tf
from dotenv import load_dotenv
from fastapi import BackgroundTasks, FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field, field_validator
from psycopg2 import pool
from psycopg2.extras import Json

# -----------------------------------------------------------------------------
# Environment bootstrapping
# -----------------------------------------------------------------------------
# Load local service env first, then Laravel root .env so this service can share
# the same Postgres/Supabase credentials used by Laravel.
BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env.local", override=False)
load_dotenv(BASE_DIR.parent / ".env", override=False)

# -----------------------------------------------------------------------------
# Logging setup
# -----------------------------------------------------------------------------
logging.basicConfig(
	level=os.getenv("LOG_LEVEL", "INFO").upper(),
	format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
LOGGER = logging.getLogger("rideconnect-ml")

# -----------------------------------------------------------------------------
# Paths and model metadata
# -----------------------------------------------------------------------------
MODELS_DIR = BASE_DIR / "models"
SKLEARN_MODEL_PATH = MODELS_DIR / "RideConnect_Model.pkl"
LSTM_MODEL_PATH_V2 = MODELS_DIR / "rideconnect_v2_best.keras"
FEAT_SCALER_PATH = MODELS_DIR / "feat_scaler.pkl"
TARGET_SCALER_PATH = MODELS_DIR / "target_scaler.pkl"
ZONE_MAP_PATH = MODELS_DIR / "zone_map.json"
METRICS_PATH = MODELS_DIR / "metrics.json"

MODEL_VERSION = os.getenv("MODEL_VERSION", "v1")
PREDICT_RETRIES = int(os.getenv("PREDICT_RETRIES", "3"))
RETRY_DELAY_SECONDS = float(os.getenv("PREDICT_RETRY_DELAY", "0.2"))

# API contract shapes used for strict model compatibility checks.
FARE_CONTRACT_FEATURES = int(os.getenv("FARE_CONTRACT_FEATURES", "23"))
DRIVER_CONTRACT_FEATURES = int(os.getenv("DRIVER_CONTRACT_FEATURES", "21"))
DEMAND_CONTRACT_SEQUENCE_LENGTH = int(os.getenv("DEMAND_CONTRACT_SEQUENCE_LENGTH", "8"))

# -----------------------------------------------------------------------------
# App initialization
# -----------------------------------------------------------------------------
app = FastAPI(title="RideConnect ML Service", version="1.2.0")


@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError) -> JSONResponse:
	"""Convert request validation failures to HTTP 400 with explicit payload errors."""
	messages: list[str] = []
	for err in exc.errors():
		loc = ".".join(str(part) for part in err.get("loc", []))
		msg = err.get("msg", "Invalid input")
		messages.append(f"{loc}: {msg}")

	return JSONResponse(
		status_code=400,
		content={
			"detail": "Invalid request payload",
			"errors": messages,
		},
	)

# Global model references loaded during startup and reload operations.
fare_estimator: Any = None
driver_ranker: Any = None
demand_model: Any = None

# V2 LSTM scaler and zone mapping.
feat_scaler: Any = None
target_scaler: Any = None
zone_map: dict[str, int] = {}

# Global Postgres pool reference. Safe writes are done with retries.
db_pool: pool.SimpleConnectionPool | None = None

# Cache detected logging table columns to support schema-compatible inserts.
log_table_columns: set[str] = set()


# -----------------------------------------------------------------------------
# Request schemas with validation
# -----------------------------------------------------------------------------
class PredictFareRequest(BaseModel):
	# Fare model requires exactly 23 feature values.
	features: list[float] = Field(..., min_length=FARE_CONTRACT_FEATURES, max_length=FARE_CONTRACT_FEATURES)

	@field_validator("features")
	@classmethod
	def validate_features(cls, value: list[float]) -> list[float]:
		if len(value) != FARE_CONTRACT_FEATURES:
			raise ValueError(
				f"features must contain exactly {FARE_CONTRACT_FEATURES} values for fare prediction"
			)
		return value


class RankDriversRequest(BaseModel):
	# Driver ranker requires exactly 21 feature values.
	features: list[float] = Field(..., min_length=DRIVER_CONTRACT_FEATURES, max_length=DRIVER_CONTRACT_FEATURES)

	@field_validator("features")
	@classmethod
	def validate_features(cls, value: list[float]) -> list[float]:
		if len(value) != DRIVER_CONTRACT_FEATURES:
			raise ValueError(
				f"features must contain exactly {DRIVER_CONTRACT_FEATURES} values for driver ranking"
			)
		return value


class PredictDemandRequest(BaseModel):
	# V2 LSTM endpoint: zone_id + 16 timesteps of 17 temporal features each.
	zone_id: str = Field(..., description="Zone ID e.g., Z01, Z02, ..., Z15")
	history: list[list[float]] = Field(
		...,
		min_length=16,
		max_length=16,
		description="16 timesteps of 17 temporal features in exact order",
	)

	@field_validator("history")
	@classmethod
	def validate_history(cls, value: list[list[float]]) -> list[list[float]]:
		if len(value) != 16:
			raise ValueError("history must contain exactly 16 timesteps")
		for i, timestep in enumerate(value):
			if len(timestep) != 17:
				raise ValueError(
					f"timestep {i} has {len(timestep)} features, expected 17 "
					"(hour_sin, hour_cos, dow_sin, dow_cos, is_weekend, is_holiday, "
					"is_market_day, is_event_day, temperature_c, lag_1, lag_4, lag_96, "
					"rolling_1h, wx_cloudy, wx_heavy_rain, wx_light_rain, wx_sunny)"
			)
		return value


# -----------------------------------------------------------------------------
# Utility functions
# -----------------------------------------------------------------------------
def _get_db_dsn() -> str | None:
	"""Build a Postgres DSN from DATABASE_URL or DB_* variables."""
	database_url = os.getenv("DATABASE_URL")
	if database_url:
		return database_url

	host = os.getenv("DB_HOST")
	port = os.getenv("DB_PORT", "5432")
	dbname = os.getenv("DB_DATABASE")
	user = os.getenv("DB_USERNAME")
	password = os.getenv("DB_PASSWORD")
	sslmode = os.getenv("DB_SSLMODE", "require")

	if all([host, dbname, user, password]):
		return (
			f"postgresql://{user}:{password}@{host}:{port}/{dbname}"
			f"?sslmode={sslmode}"
		)
	return None


def _init_db_pool() -> None:
	"""Initialize a small connection pool for prediction logging."""
	global db_pool, log_table_columns
	dsn = _get_db_dsn()
	if not dsn:
		LOGGER.warning("DB credentials not found. Prediction logging is disabled.")
		return

	try:
		db_pool = pool.SimpleConnectionPool(minconn=1, maxconn=5, dsn=dsn)
		# Detect ai_prediction_logs schema so inserts can match existing columns.
		connection = db_pool.getconn()
		try:
			with connection.cursor() as cursor:
				cursor.execute(
					"""
					SELECT column_name
					FROM information_schema.columns
					WHERE table_schema='public' AND table_name='ai_prediction_logs'
					"""
				)
				log_table_columns = {row[0] for row in cursor.fetchall()}
				# Keep the serial id sequence aligned with the current max(id).
				cursor.execute(
					"""
					DO $$
					BEGIN
						IF EXISTS (
							SELECT 1
							FROM information_schema.columns
							WHERE table_schema = 'public'
							AND table_name = 'ai_prediction_logs'
							AND column_name = 'id'
						) THEN
							PERFORM setval(
								pg_get_serial_sequence('ai_prediction_logs', 'id'),
								COALESCE((SELECT MAX(id) FROM ai_prediction_logs), 1),
								true
							);
						END IF;
					END $$;
					"""
				)
		finally:
			db_pool.putconn(connection)
		LOGGER.info("Postgres connection pool initialized.")
	except Exception:
		db_pool = None
		LOGGER.exception("Failed to initialize Postgres connection pool.")


def _build_log_insert(
	model_name: str,
	input_payload: dict[str, Any],
	output_payload: dict[str, Any],
) -> tuple[str, tuple[Any, ...]] | None:
	"""Build a schema-compatible insert statement for ai_prediction_logs."""
	# Preferred canonical schema.
	if {"model_name", "input_data", "output_data", "created_at"}.issubset(log_table_columns):
		return (
			"""
			INSERT INTO ai_prediction_logs (model_name, input_data, output_data, created_at)
			VALUES (%s, %s, %s, %s)
			""",
			(
				model_name,
				Json(input_payload),
				Json(output_payload),
				datetime.now(timezone.utc),
			),
		)

	# Existing Laravel schema observed in some environments.
	if {
		"prediction_type",
		"request_payload",
		"response_payload",
		"success",
		"requested_at",
		"created_at",
		"updated_at",
	}.issubset(log_table_columns):
		now = datetime.now(timezone.utc)
		return (
			"""
			INSERT INTO ai_prediction_logs (
				prediction_type,
				trip_id,
				request_payload,
				response_payload,
				response_time_ms,
				success,
				requested_at,
				created_at,
				updated_at
			)
			VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
			""",
			(
				model_name,
				None,
				Json(input_payload),
				Json(output_payload),
				None,
				True,
				now,
				now,
				now,
			),
		)

	return None


def _load_models() -> None:
	"""Load sklearn and V2 LSTM artifacts into global references."""
	global fare_estimator, driver_ranker, demand_model, feat_scaler, target_scaler, zone_map

	fare_estimator = None
	driver_ranker = None
	demand_model = None
	feat_scaler = None
	target_scaler = None
	zone_map = {}

	# Shared sklearn pickle can contain dict models or a single estimator.
	try:
		loaded_sklearn = joblib.load(SKLEARN_MODEL_PATH)
		if isinstance(loaded_sklearn, dict):
			fare_estimator = loaded_sklearn.get("fare_estimator")
			driver_ranker = loaded_sklearn.get("driver_ranker")
		else:
			fare_estimator = loaded_sklearn
			driver_ranker = loaded_sklearn
		LOGGER.info("Sklearn model artifact loaded from %s", SKLEARN_MODEL_PATH)
	except Exception:
		LOGGER.exception("Failed to load sklearn model artifact.")

	# Load V2 LSTM demand model with dual inputs.
	try:
		demand_model = tf.keras.models.load_model(LSTM_MODEL_PATH_V2, compile=False)
		# Validate dual-input signature: (None, 16, 17) and (None, 1).
		if not isinstance(demand_model.input, list) or len(demand_model.input) != 2:
			LOGGER.error(
				"Loaded model does not have exactly 2 inputs. "
				"Expected dual-input architecture for V2 LSTM."
			)
			demand_model = None
			return

		temporal_input = demand_model.input[0]
		zone_input = demand_model.input[1]
		temporal_shape = getattr(temporal_input, "shape", None)
		zone_shape = getattr(zone_input, "shape", None)

		if (
			not (
				getattr(temporal_shape, "as_list", None) == [None, 16, 17]
				or (len(temporal_shape) == 3 and temporal_shape[1] == 16 and temporal_shape[2] == 17)
			)
		):
			LOGGER.error(
				f"Temporal input shape is {temporal_shape}, expected (None, 16, 17). "
				"Model is incompatible with V2 LSTM contract."
			)
			demand_model = None
			return

		if not (
			getattr(zone_shape, "as_list", None) == [None, 1]
			or (len(zone_shape) == 2 and zone_shape[1] == 1)
		):
			LOGGER.error(
				f"Zone input shape is {zone_shape}, expected (None, 1). "
				"Model is incompatible with V2 LSTM contract."
			)
			demand_model = None
			return

		LOGGER.info("V2 LSTM demand model loaded and validated from %s", LSTM_MODEL_PATH_V2)
	except Exception:
		LOGGER.exception("Failed to load V2 LSTM demand model artifact.")
		demand_model = None

	# Load feature scaler.
	try:
		feat_scaler = joblib.load(FEAT_SCALER_PATH)
		LOGGER.info("Feature scaler loaded from %s", FEAT_SCALER_PATH)
	except Exception:
		LOGGER.exception("Failed to load feature scaler.")
		feat_scaler = None

	# Load target scaler.
	try:
		target_scaler = joblib.load(TARGET_SCALER_PATH)
		LOGGER.info("Target scaler loaded from %s", TARGET_SCALER_PATH)
	except Exception:
		LOGGER.exception("Failed to load target scaler.")
		target_scaler = None

	# Load zone map.
	try:
		with open(ZONE_MAP_PATH, "r", encoding="utf-8") as f:
			zone_map = json.load(f)
		LOGGER.info("Zone map loaded from %s with %d zones", ZONE_MAP_PATH, len(zone_map))
	except Exception:
		LOGGER.exception("Failed to load zone map.")
		zone_map = {}


def _predict_with_retries(model: Any, features: np.ndarray) -> np.ndarray:
	"""Retry model prediction to tolerate transient runtime failures."""
	last_exception: Exception | None = None
	for attempt in range(1, PREDICT_RETRIES + 1):
		try:
			prediction = model.predict(features)
			return np.array(prediction)
		except Exception as exc:
			last_exception = exc
			LOGGER.warning("Prediction attempt %s/%s failed.", attempt, PREDICT_RETRIES)
			if attempt < PREDICT_RETRIES:
				time.sleep(RETRY_DELAY_SECONDS * attempt)

	raise RuntimeError("Prediction failed after retries") from last_exception


def _log_prediction(model_name: str, input_payload: dict[str, Any], output_payload: dict[str, Any]) -> None:
	"""Write inference logs to ai_prediction_logs using safe DB writes."""
	if db_pool is None:
		return

	insert_stmt = _build_log_insert(model_name, input_payload, output_payload)
	if insert_stmt is None:
		LOGGER.warning("ai_prediction_logs schema not recognized. Skipping prediction log write.")
		return

	query, params = insert_stmt

	last_error: Exception | None = None
	for attempt in range(1, 4):
		connection = None
		try:
			connection = db_pool.getconn()
			with connection.cursor() as cursor:
				cursor.execute(
					query,
					params,
				)
			connection.commit()
			return
		except Exception as exc:
			last_error = exc
			LOGGER.warning("DB write attempt %s/3 failed for %s", attempt, model_name)
			if connection is not None:
				connection.rollback()
			time.sleep(0.2 * attempt)
		finally:
			if connection is not None:
				db_pool.putconn(connection)

	LOGGER.exception("Failed to log prediction for %s: %s", model_name, last_error)


def _model_versions() -> dict[str, str]:
	"""Return lightweight model version metadata for operations visibility."""
	versions: dict[str, str] = {
		"service_version": MODEL_VERSION,
		"fare_estimator": "unavailable",
		"driver_ranker": "unavailable",
		"demand_lstm": "unavailable",
	}

	if SKLEARN_MODEL_PATH.exists():
		versions["fare_estimator"] = str(int(SKLEARN_MODEL_PATH.stat().st_mtime))
		versions["driver_ranker"] = str(int(SKLEARN_MODEL_PATH.stat().st_mtime))
	if LSTM_MODEL_PATH.exists():
		versions["demand_lstm"] = str(int(LSTM_MODEL_PATH.stat().st_mtime))

	if METRICS_PATH.exists():
		try:
			metrics = json.loads(METRICS_PATH.read_text(encoding="utf-8"))
			trained_at = metrics.get("trained_at")
			if trained_at:
				versions["trained_at"] = str(trained_at)
		except Exception:
			LOGGER.warning("Could not parse metrics.json for version metadata.")

	return versions


def _safe_int(value: Any) -> int | None:
	"""Best-effort integer conversion helper for metadata values."""
	try:
		if value is None:
			return None
		return int(value)
	except Exception:
		return None


def _model_compatibility() -> dict[str, dict[str, Any]]:
	"""
	Return strict compatibility report per model against API input contract.
	This helps detect schema drifts before they cause runtime fallback behavior.
	"""
	fare_expected = _safe_int(getattr(fare_estimator, "n_features_in_", None))
	driver_expected = _safe_int(getattr(driver_ranker, "n_features_in_", None))

	demand_input_shape = getattr(demand_model, "input_shape", None)
	demand_timesteps: int | None = None
	demand_expected_features: int | None = None
	if isinstance(demand_input_shape, tuple) and len(demand_input_shape) >= 3:
		demand_timesteps = _safe_int(demand_input_shape[1])
		demand_expected_features = _safe_int(demand_input_shape[2])

	fare_compatible = fare_estimator is not None and fare_expected == FARE_CONTRACT_FEATURES
	driver_compatible = driver_ranker is not None and driver_expected == DRIVER_CONTRACT_FEATURES
	demand_compatible = (
		demand_model is not None
		and demand_timesteps == DEMAND_CONTRACT_SEQUENCE_LENGTH
		and demand_expected_features == 1
	)

	return {
		"fare_estimator": {
			"loaded": fare_estimator is not None,
			"compatible": fare_compatible,
			"endpoint_expected_features": FARE_CONTRACT_FEATURES,
			"model_expected_features": fare_expected,
		},
		"driver_ranker": {
			"loaded": driver_ranker is not None,
			"compatible": driver_compatible,
			"endpoint_expected_features": DRIVER_CONTRACT_FEATURES,
			"model_expected_features": driver_expected,
		},
		"demand_lstm_v2": {
			"loaded": demand_model is not None,
			"scaler_loaded": feat_scaler is not None and target_scaler is not None,
			"zone_map_loaded": len(zone_map) > 0,
			"endpoint_expected_temporal": "(None, 16, 17)",
			"endpoint_expected_zone": "(None, 1)",
			"model_expected_timesteps": demand_timesteps,
			"model_expected_features": demand_expected_features,
		},
	}


# -----------------------------------------------------------------------------
# Lifecycle hooks
# -----------------------------------------------------------------------------
@app.on_event("startup")
def on_startup() -> None:
	"""Initialize DB pool and load models at service startup."""
	_init_db_pool()
	_load_models()


@app.on_event("shutdown")
def on_shutdown() -> None:
	"""Close DB pool cleanly when the service is stopped."""
	global db_pool
	if db_pool is not None:
		db_pool.closeall()
		db_pool = None


# -----------------------------------------------------------------------------
# Endpoints
# -----------------------------------------------------------------------------
@app.get("/ml/health")
def health() -> dict[str, Any]:
	"""
	Service health endpoint used by Docker/K8s and Laravel checks.
	Always returns 200 with status: ok (all models + DB loaded) or degraded (missing components).
	Never crashes or returns 503.
	"""
	try:
		# Determine health status.
		fare_ok = fare_estimator is not None
		driver_ok = driver_ranker is not None
		demand_ok = (
			demand_model is not None
			and feat_scaler is not None
			and target_scaler is not None
			and len(zone_map) > 0
		)
		db_ok = db_pool is not None

		# Status is "ok" if all critical components loaded, "degraded" otherwise.
		status = "ok" if (fare_ok and driver_ok and demand_ok and db_ok) else "degraded"

		return {
			"status": status,
			"model_loaded": demand_ok,
			"database_connected": db_ok,
			"models_loaded": {
				"fare_estimator": fare_ok,
				"driver_ranker": driver_ok,
				"demand_lstm_v2": demand_ok,
			},
			"model_compatibility": _model_compatibility(),
			"db_logging_enabled": db_ok,
			"model_versions": _model_versions(),
		}
	except Exception as exc:
		LOGGER.exception("Unexpected error in health endpoint: %s", exc)
		return {
			"status": "degraded",
			"model_loaded": False,
			"database_connected": False,
			"error": "Unexpected exception in health check",
		}


@app.post("/ml/reload-models")
def reload_models() -> dict[str, Any]:
	"""
	Reload model artifacts without restarting container.
	Laravel can call this after weekly retraining completes.
	"""
	_load_models()
	return {
		"status": "reloaded",
		"models_loaded": {
			"fare_estimator": fare_estimator is not None,
			"driver_ranker": driver_ranker is not None,
			"demand_lstm": demand_model is not None,
		},
		"model_compatibility": _model_compatibility(),
		"model_versions": _model_versions(),
	}


@app.get("/ml/examples")
def examples() -> dict[str, Any]:
	"""Example payloads for local testing and integration verification."""
	return {
		"predict_fare": {
			"features": [0.1] * FARE_CONTRACT_FEATURES,
		},
		"rank_drivers": {
			"features": [0.1] * DRIVER_CONTRACT_FEATURES,
		},
		"predict_demand_v2": {
			"zone_id": "Z01",
			"history": [[0.1] * 17 for _ in range(16)],
		},
	}


@app.get("/ml/predict-fare")
def predict_fare_help() -> dict[str, Any]:
	"""Browser-friendly help for the fare prediction endpoint."""
	return {
		"detail": "Use POST for this endpoint.",
		"expected_method": "POST",
		"example_payload": {
			"features": [0.1] * FARE_CONTRACT_FEATURES,
		},
		"example_curl": "curl -X POST http://localhost:8080/ml/predict-fare -H 'Content-Type: application/json' -d '{\"features\":[0.1,0.1,...]}'",
	}


@app.get("/ml/rank-drivers")
def rank_drivers_help() -> dict[str, Any]:
	"""Browser-friendly help for the driver ranking endpoint."""
	return {
		"detail": "Use POST for this endpoint.",
		"expected_method": "POST",
		"example_payload": {
			"features": [0.1] * DRIVER_CONTRACT_FEATURES,
		},
		"example_curl": "curl -X POST http://localhost:8080/ml/rank-drivers -H 'Content-Type: application/json' -d '{\"features\":[0.1,0.1,...]}'",
	}


@app.get("/ml/predict-demand")
def predict_demand_help() -> dict[str, Any]:
	"""Browser-friendly help for the V2 LSTM demand prediction endpoint."""
	return {
		"detail": "Use POST for this endpoint.",
		"expected_method": "POST",
		"contract": {
			"input": {
				"zone_id": "str (e.g., Z01, Z02, ..., Z15)",
				"history": "16 timesteps of 17 features each (float)",
				"features_in_order": [
					"hour_sin",
					"hour_cos",
					"dow_sin",
					"dow_cos",
					"is_weekend",
					"is_holiday",
					"is_market_day",
					"is_event_day",
					"temperature_c",
					"lag_1",
					"lag_4",
					"lag_96",
					"rolling_1h",
					"wx_cloudy",
					"wx_heavy_rain",
					"wx_light_rain",
					"wx_sunny",
				],
			},
			"output": {
				"forecast_steps": "8 steps (2 hours ahead at 15-min resolution)",
				"each_step": {
					"step": "1-8",
					"timestamp": "UTC ISO string",
					"predicted_demand": "float (request count)",
				},
			},
		},
		"example_payload": {
			"zone_id": "Z01",
			"history": [[0.1] * 17 for _ in range(16)],
		},
		"example_curl": "curl -X POST http://localhost:8080/ml/predict-demand -H 'Content-Type: application/json' -d '{\"zone_id\":\"Z01\",\"history\":[[0.1]*17]*16}'".
	}


@app.get("/ml/reload-models")
def reload_models_help() -> dict[str, Any]:
	"""Browser-friendly help for the model reload endpoint."""
	return {
		"detail": "Use POST to reload models.",
		"expected_method": "POST",
		"example_curl": "curl -X POST http://localhost:8080/ml/reload-models",
	}


@app.post("/ml/predict-fare")
def predict_fare(payload: PredictFareRequest) -> dict[str, Any]:
	"""Predict fare using strict 23-feature input validation."""
	if fare_estimator is None:
		raise HTTPException(status_code=503, detail="fare model is not loaded")

	features = np.array(payload.features, dtype=np.float32).reshape(1, -1)
	if features.shape[1] != FARE_CONTRACT_FEATURES:
		raise HTTPException(
			status_code=400,
			detail=(
				f"invalid fare feature length: expected {FARE_CONTRACT_FEATURES}, "
				f"got {features.shape[1]}"
			),
		)

	try:
		prediction = _predict_with_retries(fare_estimator, features)
		predicted_fare = float(prediction.reshape(-1)[0])
	except Exception as exc:
		LOGGER.exception("Fare prediction failed.")
		raise HTTPException(status_code=500, detail=f"fare prediction failed: {exc}") from exc

	response = {"predicted_fare": predicted_fare, "currency": "RWF"}
	_log_prediction("fare_estimator", payload.model_dump(), response)
	return response


@app.post("/ml/rank-drivers")
def rank_drivers(payload: RankDriversRequest) -> dict[str, Any]:
	"""Rank a driver using strict 21-feature input validation."""
	if driver_ranker is None:
		raise HTTPException(status_code=503, detail="driver ranker model is not loaded")

	features = np.array(payload.features, dtype=np.float32).reshape(1, -1)
	if features.shape[1] != DRIVER_CONTRACT_FEATURES:
		raise HTTPException(
			status_code=400,
			detail=(
				f"invalid driver feature length: expected {DRIVER_CONTRACT_FEATURES}, "
				f"got {features.shape[1]}"
			),
		)

	try:
		prediction = _predict_with_retries(driver_ranker, features)
		ranks = prediction.reshape(-1).tolist()
	except Exception as exc:
		LOGGER.exception("Driver ranking failed.")
		raise HTTPException(status_code=500, detail=f"driver ranking failed: {exc}") from exc

	response = {"driver_ranks": ranks}
	_log_prediction("driver_ranker", payload.model_dump(), response)
	return response


@app.post("/ml/predict-demand")
def predict_demand(
	payload: PredictDemandRequest,
	background_tasks: BackgroundTasks,
) -> dict[str, Any]:
	"""Predict demand using V2 LSTM dual-input model with 16x17 temporal history and zone.

	Returns 8 forecast steps (2 hours ahead at 15-min resolution) with timestamps.
	"""

	# Check all required components are loaded.
	if demand_model is None:
		raise HTTPException(status_code=503, detail="demand model is not loaded")
	if feat_scaler is None:
		raise HTTPException(status_code=503, detail="feature scaler is not loaded")
	if target_scaler is None:
		raise HTTPException(status_code=503, detail="target scaler is not loaded")
	if len(zone_map) == 0:
		raise HTTPException(status_code=503, detail="zone map is not loaded")

	# Look up zone_id in zone_map.
	if payload.zone_id not in zone_map:
		raise HTTPException(
			status_code=404,
			detail=f"Unknown zone_id: {payload.zone_id}. Available zones: {list(zone_map.keys())}",
	)

	zone_idx = zone_map[payload.zone_id]

	try:
		# Build temporal tensor: (1, 16, 17) float32.
		temporal = np.array(payload.history, dtype=np.float32).reshape(1, 16, 17)

		# Build zone tensor: (1, 1) int32.
		zone_tensor = np.array([[zone_idx]], dtype=np.int32)

		# Call model with dual inputs.
		predictions = demand_model.predict([temporal, zone_tensor], verbose=0)
		predictions = np.array(predictions, dtype=np.float32).reshape(-1)

		# Inverse-transform from [0, 1] back to request counts.
		inverse_predictions = target_scaler.inverse_transform(predictions.reshape(-1, 1)).flatten()

		# Clip to non-negative (defensive).
		inverse_predictions = np.maximum(inverse_predictions, 0.0)

		# Compute reference_time as now rounded down to nearest 15-min slot.
		now = datetime.now(timezone.utc)
		minutes_offset = (now.minute // 15) * 15
		reference_time = now.replace(minute=minutes_offset, second=0, microsecond=0)

		# Build 8 forecast steps, each 15 minutes apart.
		forecast_steps = []
		for step_idx, predicted_count in enumerate(inverse_predictions[:8], start=1):
			step_time = reference_time + timedelta(minutes=15 * step_idx)
			forecast_steps.append(
				{
					"step": step_idx,
					"timestamp": step_time.isoformat(),
					"predicted_demand": round(float(predicted_count), 3),
				}
			)

		response = {
			"zone_id": payload.zone_id,
			"reference_time": reference_time.isoformat(),
			"forecast_steps": forecast_steps,
		}

		# Move prediction logging to background to keep hot path fast.
		background_tasks.add_task(
			_log_prediction,
			"demand_lstm_v2",
			payload.model_dump(),
			response,
		)

		return response

	except HTTPException:
		raise
	except Exception as exc:
		LOGGER.exception("V2 LSTM demand prediction failed for zone %s: %s", payload.zone_id, exc)
		raise HTTPException(status_code=500, detail="demand prediction failed") from exc


# -----------------------------------------------------------------------------
# Integration notes for Laravel operations
# -----------------------------------------------------------------------------
# Weekly retraining flow from Laravel:
# 1) Laravel Artisan command runs: python train_models.py (inside ml-service).
# 2) Script updates model artifacts in models/ and writes models/metrics.json.
# 3) Laravel calls POST /ml/reload-models to reload new artifacts in memory.
# 4) Laravel monitors /ml/health model_versions + metrics.json for performance.
