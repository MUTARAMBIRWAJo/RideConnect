import json
import logging
import os
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import psycopg2
import tensorflow as tf
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request
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
LSTM_MODEL_PATH = MODELS_DIR / "demand_lstm.h5"
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
	# LSTM endpoint accepts a single-feature sequence with exactly 8 timesteps.
	features: list[float] = Field(
		...,
		min_length=DEMAND_CONTRACT_SEQUENCE_LENGTH,
		max_length=DEMAND_CONTRACT_SEQUENCE_LENGTH,
	)

	@field_validator("features")
	@classmethod
	def validate_features(cls, value: list[float]) -> list[float]:
		if len(value) != DEMAND_CONTRACT_SEQUENCE_LENGTH:
			raise ValueError(
				f"features must contain exactly {DEMAND_CONTRACT_SEQUENCE_LENGTH} values for demand prediction"
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
	"""Load sklearn and LSTM artifacts into global references."""
	global fare_estimator, driver_ranker, demand_model

	fare_estimator = None
	driver_ranker = None
	demand_model = None

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

	try:
		demand_model = tf.keras.models.load_model(LSTM_MODEL_PATH, compile=False)
		LOGGER.info("LSTM demand model loaded from %s", LSTM_MODEL_PATH)
	except Exception:
		LOGGER.exception("Failed to load LSTM model artifact.")


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
		"demand_lstm": {
			"loaded": demand_model is not None,
			"compatible": demand_compatible,
			"endpoint_expected_timesteps": DEMAND_CONTRACT_SEQUENCE_LENGTH,
			"endpoint_expected_features": 1,
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
	Includes model loaded flags and model version metadata.
	"""
	return {
		"status": "ok",
		"models_loaded": {
			"fare_estimator": fare_estimator is not None,
			"driver_ranker": driver_ranker is not None,
			"demand_lstm": demand_model is not None,
		},
		"model_compatibility": _model_compatibility(),
		"db_logging_enabled": db_pool is not None,
		"model_versions": _model_versions(),
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
		"predict_demand": {
			"features": [0.1] * DEMAND_CONTRACT_SEQUENCE_LENGTH,
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
	"""Browser-friendly help for the demand prediction endpoint."""
	return {
		"detail": "Use POST for this endpoint.",
		"expected_method": "POST",
		"example_payload": {
			"features": [0.1] * DEMAND_CONTRACT_SEQUENCE_LENGTH,
		},
		"example_curl": "curl -X POST http://localhost:8080/ml/predict-demand -H 'Content-Type: application/json' -d '{\"features\":[0.1,0.1,...]}'",
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
def predict_demand(payload: PredictDemandRequest) -> dict[str, Any]:
	"""Predict demand using a strict single-feature sequence of length 8."""
	if demand_model is None:
		raise HTTPException(status_code=503, detail="demand model is not loaded")

	sequence = np.array(payload.features, dtype=np.float32)
	model_input_shape = getattr(demand_model, "input_shape", None)
	model_timesteps = None
	model_feature_count = 1

	if isinstance(model_input_shape, tuple) and len(model_input_shape) >= 3:
		model_timesteps = _safe_int(model_input_shape[1])
		model_feature_count = _safe_int(model_input_shape[2]) or 1

	if model_timesteps is not None and model_timesteps != DEMAND_CONTRACT_SEQUENCE_LENGTH:
		raise HTTPException(
			status_code=500,
			detail=(
				f"demand model expects sequence length {model_timesteps}, "
				f"but API contract is {DEMAND_CONTRACT_SEQUENCE_LENGTH}"
			),
		)

	if model_feature_count != 1:
		raise HTTPException(
			status_code=500,
			detail=(
				f"demand model expects {model_feature_count} features per timestep, "
				"but API contract provides 1"
			),
		)

	features = sequence.reshape(1, DEMAND_CONTRACT_SEQUENCE_LENGTH, 1)

	try:
		prediction = _predict_with_retries(demand_model, features)
		predicted = np.array(prediction).reshape(-1).tolist()
	except Exception as exc:
		LOGGER.exception("Demand prediction failed.")
		raise HTTPException(status_code=500, detail=f"demand prediction failed: {exc}") from exc

	response = {"predicted_demand": predicted}
	_log_prediction("demand_lstm", payload.model_dump(), response)
	return response


# -----------------------------------------------------------------------------
# Integration notes for Laravel operations
# -----------------------------------------------------------------------------
# Weekly retraining flow from Laravel:
# 1) Laravel Artisan command runs: python train_models.py (inside ml-service).
# 2) Script updates model artifacts in models/ and writes models/metrics.json.
# 3) Laravel calls POST /ml/reload-models to reload new artifacts in memory.
# 4) Laravel monitors /ml/health model_versions + metrics.json for performance.
