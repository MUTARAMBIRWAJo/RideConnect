"""
Placeholder script for training matching/ranking models (e.g., XGBoost).
"""
import os
import numpy as np
from app.core.config import settings
import joblib


def main():
    # generate dummy features and train a small sklearn model
    from sklearn.ensemble import RandomForestRegressor
    X = np.random.rand(1000, 10)
    y = np.random.rand(1000)
    model = RandomForestRegressor(n_estimators=10)
    model.fit(X, y)
    os.makedirs(settings.MODEL_DIR, exist_ok=True)
    joblib.dump(model, os.path.join(settings.MODEL_DIR, "matcher_v0.joblib"))


if __name__ == "__main__":
    main()
