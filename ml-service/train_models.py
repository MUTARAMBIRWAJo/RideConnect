"""
Train all RideConnect ML models and export production artifacts.

This script can be triggered by a Laravel Artisan command, for example:
php artisan models:retrain

Expected outputs:
- models/RideConnect_Model.pkl  (dict with fare_estimator + driver_ranker)
- models/demand_lstm.h5         (TensorFlow LSTM model)
- models/metrics.json           (evaluation and version metadata)
"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
import tensorflow as tf
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error
from sklearn.model_selection import train_test_split

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
BASE_DIR = Path(__file__).resolve().parent
MODELS_DIR = BASE_DIR / "models"
MODELS_DIR.mkdir(parents=True, exist_ok=True)

SKLEARN_MODEL_PATH = MODELS_DIR / "RideConnect_Model.pkl"
LSTM_MODEL_PATH = MODELS_DIR / "demand_lstm.h5"
METRICS_PATH = MODELS_DIR / "metrics.json"

RNG = np.random.default_rng(42)


# -----------------------------------------------------------------------------
# Synthetic training data helpers
# -----------------------------------------------------------------------------
def build_fare_dataset(n_samples: int = 4000) -> tuple[np.ndarray, np.ndarray]:
	"""Generate synthetic fare training data for baseline retraining."""
	distance = RNG.uniform(0.5, 35.0, n_samples)
	hour = RNG.integers(0, 24, n_samples)
	day_of_week = RNG.integers(0, 7, n_samples)

	is_peak = np.isin(hour, [7, 8, 9, 17, 18, 19]).astype(float)
	is_weekend = np.isin(day_of_week, [5, 6]).astype(float)
	noise = RNG.normal(0, 180, n_samples)

	fare = 500 + (distance * 320) + (is_peak * 350) + (is_weekend * 220) + noise
	fare = np.clip(fare, a_min=400, a_max=None)

	x = np.column_stack([distance, hour, day_of_week]).astype(np.float32)
	y = fare.astype(np.float32)
	return x, y


def build_driver_rank_dataset(n_samples: int = 5000) -> tuple[np.ndarray, np.ndarray]:
	"""Generate synthetic ranking-score data for driver ranking model."""
	rating = RNG.uniform(3.5, 5.0, n_samples)
	completed_trips = RNG.integers(50, 3000, n_samples)
	acceptance_rate = RNG.uniform(0.6, 1.0, n_samples)
	years_active = RNG.uniform(0.2, 12.0, n_samples)

	score = (
		(rating * 0.45)
		+ (np.log1p(completed_trips) * 0.15)
		+ (acceptance_rate * 0.25)
		+ (years_active * 0.05)
		+ RNG.normal(0, 0.03, n_samples)
	)

	x = np.column_stack([rating, completed_trips, acceptance_rate, years_active]).astype(np.float32)
	y = score.astype(np.float32)
	return x, y


def build_lstm_dataset(
	n_sequences: int = 900, timesteps: int = 24, feature_count: int = 3
) -> tuple[np.ndarray, np.ndarray]:
	"""Generate synthetic demand sequence data for LSTM model."""
	x = np.zeros((n_sequences, timesteps, feature_count), dtype=np.float32)
	y = np.zeros((n_sequences, feature_count), dtype=np.float32)

	for i in range(n_sequences):
		base = RNG.uniform(80, 220)
		trend = RNG.uniform(-0.8, 0.8)
		for t in range(timesteps):
			demand = base + (trend * t) + 15 * np.sin((t / 24) * 2 * np.pi) + RNG.normal(0, 6)
			traffic = 20 + 0.12 * demand + RNG.normal(0, 1.5)
			weather_score = np.clip(0.75 + RNG.normal(0, 0.08), 0.5, 1.0)
			x[i, t, :] = [demand, traffic, weather_score]

		# Predict next-step proxy (mean of last 3 steps with slight drift).
		y[i, :] = np.mean(x[i, -3:, :], axis=0) + np.array([2.0, 0.5, 0.0], dtype=np.float32)

	return x, y


# -----------------------------------------------------------------------------
# Training routines
# -----------------------------------------------------------------------------
def train_sklearn_models() -> dict[str, float]:
	"""Train fare and ranking regressors and persist them in one artifact."""
	fare_x, fare_y = build_fare_dataset()
	rank_x, rank_y = build_driver_rank_dataset()

	x_train_f, x_test_f, y_train_f, y_test_f = train_test_split(
		fare_x, fare_y, test_size=0.2, random_state=42
	)
	x_train_r, x_test_r, y_train_r, y_test_r = train_test_split(
		rank_x, rank_y, test_size=0.2, random_state=42
	)

	fare_estimator = RandomForestRegressor(n_estimators=200, random_state=42, n_jobs=-1)
	driver_ranker = RandomForestRegressor(n_estimators=200, random_state=42, n_jobs=-1)

	fare_estimator.fit(x_train_f, y_train_f)
	driver_ranker.fit(x_train_r, y_train_r)

	fare_pred = fare_estimator.predict(x_test_f)
	rank_pred = driver_ranker.predict(x_test_r)

	metrics = {
		"fare_mae": float(mean_absolute_error(y_test_f, fare_pred)),
		"fare_rmse": float(np.sqrt(mean_squared_error(y_test_f, fare_pred))),
		"rank_mae": float(mean_absolute_error(y_test_r, rank_pred)),
		"rank_rmse": float(np.sqrt(mean_squared_error(y_test_r, rank_pred))),
	}

	joblib.dump(
		{
			"fare_estimator": fare_estimator,
			"driver_ranker": driver_ranker,
			"trained_at": datetime.now(timezone.utc).isoformat(),
		},
		SKLEARN_MODEL_PATH,
	)

	return metrics


def train_lstm_model() -> dict[str, float]:
	"""Train a compact LSTM model and export H5 artifact."""
	x, y = build_lstm_dataset()
	x_train, x_test, y_train, y_test = train_test_split(x, y, test_size=0.2, random_state=42)

	model = tf.keras.Sequential(
		[
			tf.keras.layers.Input(shape=(24, x.shape[2])),
			tf.keras.layers.LSTM(32, return_sequences=False),
			tf.keras.layers.Dense(16, activation="relu"),
			tf.keras.layers.Dense(x.shape[2]),
		]
	)

	model.compile(optimizer="adam", loss="mse", metrics=[tf.keras.metrics.MeanAbsoluteError()])
	model.fit(x_train, y_train, validation_split=0.1, epochs=8, batch_size=32, verbose=0)

	loss, mae = model.evaluate(x_test, y_test, verbose=0)
	y_pred = model.predict(x_test, verbose=0)
	rmse = float(np.sqrt(mean_squared_error(y_test.reshape(-1), y_pred.reshape(-1))))

	model.save(LSTM_MODEL_PATH)

	return {
		"demand_loss": float(loss),
		"demand_mae": float(mae),
		"demand_rmse": rmse,
	}


def main() -> None:
	"""Run end-to-end training and export models/metrics for production use."""
	sklearn_metrics = train_sklearn_models()
	lstm_metrics = train_lstm_model()

	combined_metrics = {
		"trained_at": datetime.now(timezone.utc).isoformat(),
		"artifact_paths": {
			"sklearn": str(SKLEARN_MODEL_PATH),
			"lstm": str(LSTM_MODEL_PATH),
		},
		"metrics": {
			**sklearn_metrics,
			**lstm_metrics,
		},
	}

	METRICS_PATH.write_text(json.dumps(combined_metrics, indent=2), encoding="utf-8")
	print(json.dumps(combined_metrics, indent=2))


if __name__ == "__main__":
	# This script is safe to run from Laravel scheduler/Artisan command.
	# After completion, call POST /ml/reload-models to load new artifacts.
	main()
