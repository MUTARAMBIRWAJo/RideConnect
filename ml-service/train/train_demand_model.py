"""
Minimal training script for demand model. This is a stub demonstrating LSTM training.
"""
import os
import numpy as np
import tensorflow as tf
from tensorflow.keras import layers
from app.core.config import settings


def build_model(input_shape):
    model = tf.keras.Sequential([
        layers.Input(shape=input_shape),
        layers.LSTM(64, return_sequences=True),
        layers.LSTM(32),
        layers.Dense(16, activation="relu"),
        layers.Dense(1, activation="sigmoid"),
    ])
    model.compile(optimizer="adam", loss="mse", metrics=["mae"])
    return model


def dummy_data(seq_len=24, samples=1000):
    X = np.random.rand(samples, seq_len, 4)
    y = np.random.rand(samples, 1)
    return X, y


def main():
    X, y = dummy_data()
    model = build_model((X.shape[1], X.shape[2]))
    model.fit(X, y, epochs=2, batch_size=32, validation_split=0.1)
    os.makedirs(settings.MODEL_DIR, exist_ok=True)
    model.save(os.path.join(settings.MODEL_DIR, "demand_model.keras"))


if __name__ == "__main__":
    main()
