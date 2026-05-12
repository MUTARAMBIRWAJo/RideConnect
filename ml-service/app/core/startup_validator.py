"""Startup validation and health checks."""

from __future__ import annotations

import sys
from typing import Optional

import numpy as np

from app.core.config import settings
from app.core.feature_config import EXPECTED_FEATURE_COUNT, FEATURE_COLUMNS
from app.core.logging import get_logger
from app.core.scaler_manager import get_scaler_manager
from app.services.model_loader import get_model_loader

logger = get_logger(__name__)


class StartupValidator:
    """Validates service startup prerequisites."""
    
    @staticmethod
    async def validate_all() -> dict:
        """
        Run all startup validations.
        
        Returns:
            Validation results dictionary
            
        Raises:
            RuntimeError: If critical validation fails
        """
        logger.info("=" * 60)
        logger.info("STARTUP VALIDATION BEGINNING")
        logger.info("=" * 60)
        
        results = {
            "config": {},
            "model": {},
            "scaler": {},
            "integration": {},
            "warnings": [],
        }
        
        try:
            # 1. Validate configuration
            results["config"] = StartupValidator._validate_config()
            
            # 2. Validate model
            results["model"] = StartupValidator._validate_model()
            
            # 3. Validate scaler
            results["scaler"] = StartupValidator._validate_scaler()
            
            # 4. Validate integration
            results["integration"] = await StartupValidator._validate_integration()
            
            logger.info("=" * 60)
            logger.info("STARTUP VALIDATION COMPLETE - SUCCESS")
            logger.info("=" * 60)
            
            return results
            
        except Exception as e:
            logger.error("=" * 60)
            logger.error(f"STARTUP VALIDATION FAILED: {str(e)}")
            logger.error("=" * 60)
            raise RuntimeError(f"Startup validation failed: {str(e)}")
    
    @staticmethod
    def _validate_config() -> dict:
        """
        Validate configuration values.
        
        Returns:
            Validation results
        """
        logger.info("Validating configuration...")
        results = {
            "app_name": settings.APP_NAME,
            "app_version": settings.APP_VERSION,
            "debug": settings.DEBUG,
            "model_path": settings.MODEL_PATH,
            "model_version": settings.MODEL_VERSION,
            "tensorflow_version": _get_tensorflow_version(),
        }
        
        logger.info(f"  App: {settings.APP_NAME} v{settings.APP_VERSION}")
        logger.info(f"  Debug: {settings.DEBUG}")
        logger.info(f"  Model: {settings.MODEL_PATH}")
        logger.info(f"  TensorFlow: {results['tensorflow_version']}")
        
        return results
    
    @staticmethod
    def _validate_model() -> dict:
        """
        Validate model loading and shapes.
        
        Returns:
            Validation results
            
        Raises:
            RuntimeError: If validation fails
        """
        logger.info("Validating model...")
        results = {}
        
        try:
            model_loader = get_model_loader()
            
            if model_loader.model is None:
                raise RuntimeError("Model not loaded")
            
            input_shape = model_loader.model.input_shape
            output_shape = model_loader.model.output_shape
            
            logger.info(f"  Input shape: {input_shape}")
            logger.info(f"  Output shape: {output_shape}")
            
            # Verify input dimension matches feature count
            if input_shape[1] != EXPECTED_FEATURE_COUNT:
                raise RuntimeError(
                    f"Model input dim {input_shape[1]} != "
                    f"EXPECTED_FEATURE_COUNT {EXPECTED_FEATURE_COUNT}"
                )
            
            results = {
                "input_shape": input_shape,
                "output_shape": output_shape,
                "total_parameters": model_loader.model.count_params(),
                "valid": True,
            }
            
            logger.info(f"  Parameters: {results['total_parameters']}")
            logger.info("  Model validation PASSED")
            
            return results
            
        except Exception as e:
            logger.error(f"  Model validation FAILED: {str(e)}")
            raise
    
    @staticmethod
    def _validate_scaler() -> dict:
        """
        Validate scaler loading.
        
        Returns:
            Validation results
        """
        logger.info("Validating scaler...")
        results = {"loaded": False}
        
        try:
            scaler_manager = get_scaler_manager()
            
            if not scaler_manager.is_loaded:
                logger.warning("  Scaler not loaded - using identity preprocessing")
                logger.warning("  WARNING: Predictions may be incorrect!")
                results["loaded"] = False
                results["warning"] = "Scaler not loaded, using identity preprocessing"
                return results
            
            scaler_info = scaler_manager.get_scaler_info()
            results = {**results, **scaler_info}
            results["loaded"] = True
            
            logger.info(f"  Scaler type: {scaler_info.get('scaler_type')}")
            logger.info(f"  Scaler class: {scaler_info.get('scaler_class')}")
            logger.info(f"  Input dimension: {scaler_info.get('input_dim')}")
            logger.info("  Scaler validation PASSED")
            
            return results
            
        except Exception as e:
            logger.warning(f"  Scaler validation warning: {str(e)}")
            return results
    
    @staticmethod
    async def _validate_integration() -> dict:
        """
        Validate model and scaler integration.
        
        Returns:
            Validation results
        """
        logger.info("Validating integration...")
        results = {}
        
        try:
            model_loader = get_model_loader()
            
            # Create dummy batch matching feature space
            batch_size = 2
            dummy_features = np.random.randn(batch_size, EXPECTED_FEATURE_COUNT).astype(np.float32)
            
            # Apply scaler if available; otherwise validate the raw model input path.
            try:
                scaler_manager = get_scaler_manager()
                if scaler_manager.is_loaded:
                    logger.info("  Applying scaler to dummy batch...")
                    scaled_features = scaler_manager.transform(dummy_features)
                else:
                    logger.warning("  No scaler available, using raw features")
                    scaled_features = dummy_features
            except Exception:
                logger.warning("  Scaler manager unavailable, using raw features")
                scaled_features = dummy_features
            
            # Run model prediction
            logger.info("  Running test prediction...")
            predictions = model_loader.predict(scaled_features)
            
            results = {
                "test_batch_size": batch_size,
                "test_predictions_shape": predictions.shape,
                "test_predictions_valid": _validate_predictions(predictions),
                "valid": True,
            }
            
            logger.info(f"  Predictions shape: {predictions.shape}")
            logger.info(f"  Sample predictions: {predictions[:2].flatten()}")
            logger.info("  Integration validation PASSED")
            
            return results
            
        except Exception as e:
            logger.error(f"  Integration validation FAILED: {str(e)}")
            raise
    
    @staticmethod
    def log_validation_summary(results: dict) -> None:
        """Log a summary of validation results."""
        logger.info("=" * 60)
        logger.info("STARTUP VALIDATION SUMMARY")
        logger.info("=" * 60)
        
        for section, data in results.items():
            if isinstance(data, dict):
                logger.info(f"{section}:")
                for key, value in data.items():
                    logger.info(f"  {key}: {value}")


def _get_tensorflow_version() -> str:
    """Get TensorFlow version."""
    try:
        import tensorflow as tf
        return tf.__version__
    except Exception:
        return "unknown"


def _validate_predictions(predictions: np.ndarray) -> bool:
    """
    Validate prediction output.
    
    Args:
        predictions: Prediction array
        
    Returns:
        True if predictions valid
    """
    # Check shape
    if predictions.ndim not in [1, 2]:
        return False
    
    # Check for valid values
    if np.isnan(predictions).any():
        return False
    
    if np.isinf(predictions).any():
        return False
    
    # Check value range (typically 0-1 for classification, but allow wider range)
    if predictions.size > 0:
        min_val = np.min(predictions)
        max_val = np.max(predictions)
        
        # Reasonable bounds for prediction scores
        if min_val < -10 or max_val > 10:
            return False
    
    return True
