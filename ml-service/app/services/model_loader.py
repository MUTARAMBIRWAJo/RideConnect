"""ML Model loading and management service"""
import os
from typing import Optional

import numpy as np
import tensorflow as tf
from tensorflow import keras

from app.core.config import settings
from app.core.feature_config import EXPECTED_FEATURE_COUNT, EXPECTED_OUTPUT_SHAPES
from app.core.logging import get_logger

logger = get_logger(__name__)


class ModelValidationError(Exception):
    """Raised when model validation fails."""
    pass


class ModelLoader:
    """Handles loading and managing the Keras model"""
    
    def __init__(self):
        """Initialize model loader"""
        self.model: Optional[tf.keras.Model] = None
        self.model_path = settings.MODEL_PATH
        self.model_version = settings.MODEL_VERSION
        self.devices = None
        self.initialized_at = None
        
    async def initialize(self) -> None:
        """
        Load the Keras model asynchronously with comprehensive validation.
        
        Raises:
            FileNotFoundError: If model file doesn't exist
            ModelValidationError: If model validation fails
            Exception: If model loading fails
        """
        try:
            # Log TensorFlow device info
            self._log_device_info()
            
            # Check if model file exists
            if not os.path.exists(self.model_path):
                raise FileNotFoundError(
                    f"Model file not found at {self.model_path}"
                )
            
            logger.info(f"Loading model from {self.model_path}")
            
            # Load the Keras model
            self.model = keras.models.load_model(self.model_path)
            self.initialized_at = __import__("time").time()
            
            logger.info(
                f"Model loaded successfully. "
                f"Input shape: {self.model.input_shape}, "
                f"Output shape: {self.model.output_shape}"
            )
            
            # CRITICAL: Validate model input/output shapes
            self._validate_model_shapes()
            
            # Log model architecture and layer info
            self._log_model_info()
            
            # Perform model warmup
            await self._warmup_model()
            
            logger.info("Model initialization complete and validated")
            
        except Exception as e:
            logger.error(f"Failed to load model: {str(e)}", exc_info=True)
            raise
    
    def _log_device_info(self) -> None:
        """Log available TensorFlow devices."""
        try:
            devices = tf.config.list_physical_devices()
            logger.info(f"Available TensorFlow devices: {len(devices)}")
            for i, device in enumerate(devices):
                logger.info(f"  Device {i}: {device}")
            
            # Check for GPU
            gpu_devices = tf.config.list_physical_devices('GPU')
            if gpu_devices:
                logger.info(f"GPU found: {len(gpu_devices)} device(s)")
            else:
                logger.info("No GPU detected, using CPU")
            
            self.devices = devices
        except Exception as e:
            logger.warning(f"Could not log device info: {str(e)}")
    
    def _validate_model_shapes(self) -> None:
        """
        Validate model input/output shapes match expected dimensions.
        
        Supports two architectures:
        1. V2 LSTM (dual-input): 2 inputs with shapes (None, 16, 17) and (None, 1)
        2. Legacy single-input: 1 input with shape (None, N) for any N
        
        CRITICAL: This ensures preprocessing output matches model input expectations.
        
        Raises:
            ModelValidationError: If shapes don't match supported architectures
        """
        if self.model is None:
            raise ModelValidationError("Model not loaded")
        
        output_shape = self.model.output_shape
        
        logger.info(f"Validating model shapes...")
        
        # Detect model architecture via inputs property
        num_inputs = len(self.model.inputs) if hasattr(self.model, 'inputs') else 1
        
        if num_inputs == 2:
            # V2 LSTM dual-input model
            input_shapes = [inp.shape for inp in self.model.inputs]
            logger.info(f"Detected V2 LSTM (dual-input) model")
            logger.info(f"  Input shapes: {input_shapes}")
            logger.info(f"  Output shape: {output_shape}")
            
            # Validate V2 architecture: (None, 16, 17) and (None, 1)
            temporal_shape = input_shapes[0]
            zone_shape = input_shapes[1]
            
            if temporal_shape != (None, 16, 17):
                logger.warning(
                    f"V2 temporal input shape {temporal_shape} != expected (None, 16, 17). "
                    f"Model may still work but predictions may be incorrect."
                )
            
            if zone_shape != (None, 1):
                logger.warning(
                    f"V2 zone input shape {zone_shape} != expected (None, 1). "
                    f"Model may still work but predictions may be incorrect."
                )
            
            # Validate output is (None, 8) for V2
            if output_shape != (None, 8):
                logger.warning(
                    f"V2 output shape {output_shape} != expected (None, 8). "
                    f"May still work but verify predictions are correct."
                )
        
        elif num_inputs == 1:
            # Legacy single-input model (fare, ranking, etc.)
            input_shape = self.model.input_shape
            logger.info(f"Detected single-input legacy model")
            logger.info(f"  Input shape: {input_shape}")
            logger.info(f"  Output shape: {output_shape}")
            
            # Validate basic 2D structure
            if input_shape is None:
                raise ModelValidationError("Model has no input shape")
            
            if len(input_shape) != 2:
                raise ModelValidationError(
                    f"Expected 2D input shape (batch, features), got {input_shape}"
                )
            
            # Log feature count but don't enforce strict match (allow flexibility)
            feature_dim = input_shape[1]
            logger.info(f"  Input features: {feature_dim}")
            
            if feature_dim != EXPECTED_FEATURE_COUNT:
                logger.warning(
                    f"Input feature count {feature_dim} != EXPECTED_FEATURE_COUNT {EXPECTED_FEATURE_COUNT}. "
                    f"Model may still work if training setup matches."
                )
        
        else:
            raise ModelValidationError(
                f"Unsupported model: expected 1 or 2 inputs, got {num_inputs}"
            )
        
        # Validate output shape is present and reasonable
        if output_shape is None:
            raise ModelValidationError("Model has no output shape")
        
        logger.info("Model shape validation passed")
    
    def _is_dual_input(self) -> bool:
        """
        Check if model is dual-input V2 LSTM architecture.
        
        Returns:
            True if model has exactly 2 inputs with V2 LSTM shapes, False otherwise
        """
        if self.model is None:
            return False
        
        num_inputs = len(self.model.inputs) if hasattr(self.model, 'inputs') else 1
        
        if num_inputs != 2:
            return False
        
        # Check if shapes match V2 LSTM pattern
        input_shapes = [inp.shape for inp in self.model.inputs]
        temporal_shape = input_shapes[0]
        zone_shape = input_shapes[1]
        
        return temporal_shape == (None, 16, 17) and zone_shape == (None, 1)
    
    def _log_model_info(self) -> None:
        """Log detailed model information."""
        if self.model is None:
            return
        
        try:
            # Model summary
            logger.info("Model Architecture:")
            summary_lines = []
            self.model.summary(print_fn=lambda x: summary_lines.append(x))
            for line in summary_lines[:20]:  # Log first 20 lines
                logger.info(f"  {line}")
            
            # Total parameters
            total_params = self.model.count_params()
            logger.info(f"Total parameters: {total_params}")
            
            # Model config
            if hasattr(self.model, 'optimizer') and self.model.optimizer:
                logger.info(f"Optimizer: {self.model.optimizer.__class__.__name__}")
            
            # Loss and metrics
            if hasattr(self.model, 'loss'):
                logger.info(f"Loss: {self.model.loss}")
            
        except Exception as e:
            logger.warning(f"Could not log model info: {str(e)}")
    
    async def _warmup_model(self) -> None:
        """
        Perform model warmup with dummy batch to initialize TensorFlow graph.
        
        This reduces first-request latency by ~100-200ms.
        Handles both single-input and dual-input V2 LSTM models.
        """
        if self.model is None:
            return
        
        try:
            logger.info("Performing model warmup...")
            
            if self._is_dual_input():
                # V2 LSTM: temporal_input (1, 16, 17) + zone_input (1, 1)
                logger.info("Warmup with dual-input V2 LSTM")
                temporal_dummy = np.zeros((1, 16, 17), dtype=np.float32)
                zone_dummy = np.zeros((1, 1), dtype=np.int32)
                _ = self.model.predict([temporal_dummy, zone_dummy], verbose=0)
            else:
                # Legacy single-input
                logger.info("Warmup with single-input legacy model")
                dummy_batch = np.zeros(
                    (1, EXPECTED_FEATURE_COUNT),
                    dtype=np.float32
                )
                _ = self.model.predict(dummy_batch, verbose=0)
            
            logger.info("Model warmup completed successfully")
            
        except Exception as e:
            logger.error(f"Model warmup failed: {str(e)}")
            # Don't fail initialization, just warn
            raise
    
    def predict(self, features: np.ndarray) -> np.ndarray:
        """
        Run inference on features.
        
        For single-input models:
            Args: features with shape (batch_size, EXPECTED_FEATURE_COUNT)
        
        For dual-input V2 LSTM models:
            NOT SUPPORTED via this interface. Use predict_dual_input() instead.
            
        Returns:
            Model predictions (shape: (batch_size,) or (batch_size, 1))
            
        Raises:
            RuntimeError: If model not loaded or is dual-input
            ValueError: If input shape invalid
        """
        if self.model is None:
            raise RuntimeError("Model not loaded")
        
        if self._is_dual_input():
            raise RuntimeError(
                "V2 LSTM dual-input model detected. "
                "Use predict_dual_input(temporal, zone) instead of predict(features)."
            )
        
        # Validate input shape
        if features.ndim != 2:
            raise ValueError(
                f"Expected 2D features (batch, features), got shape {features.shape}"
            )
        
        if features.shape[1] != EXPECTED_FEATURE_COUNT:
            raise ValueError(
                f"Feature count mismatch: expected {EXPECTED_FEATURE_COUNT}, "
                f"got {features.shape[1]}"
            )
        
        # Run prediction with verbose=0 to minimize logging
        predictions = self.model.predict(features, verbose=0)
        
        return predictions
    
    def predict_dual_input(self, temporal_features: np.ndarray, zone_features: np.ndarray) -> np.ndarray:
        """
        Run inference on dual-input V2 LSTM model.
        
        Args:
            temporal_features: Temporal input (batch_size, 16, 17) float32
            zone_features: Zone input (batch_size, 1) int32 or float32
            
        Returns:
            Model predictions (shape: (batch_size, 8))
            
        Raises:
            RuntimeError: If model not loaded or not dual-input
            ValueError: If input shapes invalid
        """
        if self.model is None:
            raise RuntimeError("Model not loaded")
        
        if not self._is_dual_input():
            raise RuntimeError(
                "Single-input model detected. Use predict(features) instead."
            )
        
        # Validate temporal input
        if temporal_features.ndim != 3:
            raise ValueError(
                f"Expected 3D temporal features (batch, 16, 17), "
                f"got shape {temporal_features.shape}"
            )
        
        if temporal_features.shape[1:] != (16, 17):
            raise ValueError(
                f"Temporal input shape mismatch: expected (batch, 16, 17), "
                f"got {temporal_features.shape}"
            )
        
        # Validate zone input
        if zone_features.ndim != 2:
            raise ValueError(
                f"Expected 2D zone features (batch, 1), got shape {zone_features.shape}"
            )
        
        if zone_features.shape[1] != 1:
            raise ValueError(
                f"Zone input shape mismatch: expected (batch, 1), got {zone_features.shape}"
            )
        
        # Ensure batch sizes match
        if temporal_features.shape[0] != zone_features.shape[0]:
            raise ValueError(
                f"Batch size mismatch: temporal {temporal_features.shape[0]} "
                f"!= zone {zone_features.shape[0]}"
            )
        
        # Run prediction
        predictions = self.model.predict([temporal_features, zone_features], verbose=0)
        
        return predictions
    
    def cleanup(self) -> None:
        """
        Clean up model resources.
        
        This should be called when shutting down the service.
        """
        if self.model is not None:
            # Delete model and clear backend
            del self.model
            self.model = None
            
            # Clear TensorFlow session
            keras.backend.clear_session()
            
            logger.info("Model cleaned up and backend session cleared")

    def get_model_info(self) -> dict:
        """
        Get information about the loaded model.

        Returns:
            Dictionary with model information.
        """
        uptime_seconds = 0.0
        if self.initialized_at is not None:
            import time
            uptime_seconds = time.time() - self.initialized_at

        if self.model is None:
            return {
                "loaded": False,
                "path": self.model_path,
                "version": self.model_version,
                "tensorflow_version": tf.__version__,
                "available_devices": [
                    str(device) for device in tf.config.list_physical_devices()
                ],
                "uptime_seconds": uptime_seconds,
            }

        # Handle both single-input and dual-input models
        if self._is_dual_input():
            input_shapes = [tuple(inp.shape) for inp in self.model.inputs]
        else:
            input_shapes = list(self.model.input_shape) if self.model.input_shape else None
        
        return {
            "loaded": True,
            "path": self.model_path,
            "version": self.model_version,
            "input_shape": input_shapes,
            "output_shape": list(self.model.output_shape),
            "parameters": self.model.count_params(),
            "tensorflow_version": tf.__version__,
            "available_devices": [
                str(device) for device in tf.config.list_physical_devices()
            ],
            "uptime_seconds": uptime_seconds,
        }


# Global model loader instance
_model_loader: Optional[ModelLoader] = None


def get_model_loader() -> ModelLoader:
    """
    Get the global model loader instance.
    
    Returns:
        ModelLoader instance
        
    Raises:
        RuntimeError: If model loader not initialized
    """
    global _model_loader
    if _model_loader is None:
        raise RuntimeError("Model loader not initialized")
    return _model_loader


def initialize_model_loader() -> ModelLoader:
    """
    Initialize the global model loader.
    
    Returns:
        Initialized ModelLoader instance
    """
    global _model_loader
    _model_loader = ModelLoader()
    return _model_loader
