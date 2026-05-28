"""ML model scaler loading and management service."""

from __future__ import annotations

import os
import pickle
from typing import Optional

try:
    import joblib
except ModuleNotFoundError:
    joblib = None

import numpy as np

from app.core.config import settings
from app.core.feature_config import EXPECTED_FEATURE_COUNT
from app.core.logging import get_logger

logger = get_logger(__name__)


class ScalerManager:
    """Manages loading and using feature scalers for preprocessing."""
    
    # Common scaler filenames to search for
    SCALER_CANDIDATES = [
        "feature_scaler.pkl",
        "feature_scaler.joblib",
        "scaler.pkl",
        "scaler.joblib",
        "preprocessing_scaler.pkl",
    ]
    
    def __init__(self):
        """Initialize scaler manager."""
        self.scaler = None
        self.scaler_path = None
        self.scaler_type = None
        self.is_loaded = False
    
    def find_scaler(self, model_dir: Optional[str] = None) -> Optional[str]:
        """
        Attempt to find scaler file in model directory.
        
        Args:
            model_dir: Directory to search for scaler. Defaults to settings.MODEL_DIR
            
        Returns:
            Path to scaler file if found, None otherwise
        """
        if model_dir is None:
            model_dir = settings.MODEL_DIR
        
        logger.info(f"Searching for scaler in {model_dir}")
        
        for filename in self.SCALER_CANDIDATES:
            path = os.path.join(model_dir, filename)
            if os.path.exists(path):
                logger.info(f"Found scaler: {path}")
                return path
        
        logger.warning(
            f"No scaler found in {model_dir}. Searched for: {self.SCALER_CANDIDATES}"
        )
        return None
    
    def load_scaler(self, scaler_path: Optional[str] = None) -> bool:
        """
        Load scaler from file.
        
        Args:
            scaler_path: Path to scaler file. If None, searches automatically.
            
        Returns:
            True if scaler loaded successfully, False if not found but allowed to fail,
            raises Exception if loading fails when scaler exists
        """
        # Determine scaler path
        if scaler_path is None:
            scaler_path = settings.SCALER_PATH
            logger.info(f"Configured scaler path: {scaler_path}")
            if not os.path.exists(scaler_path):
                logger.error(
                    f"Configured scaler path does not exist: {scaler_path}"
                )
                if settings.ALLOW_SCALER_FALLBACK:
                    logger.warning(
                        "ALLOW_SCALER_FALLBACK=true. Searching for compatible scaler artifact."
                    )
                    scaler_path = self.find_scaler()
                else:
                    raise FileNotFoundError(
                        f"Scaler file not found at configured path: {scaler_path}"
                    )
            if scaler_path is None:
                raise FileNotFoundError(
                    "Scaler file not found and fallback search did not locate a valid artifact"
                )
        
        # Verify path exists
        if not os.path.exists(scaler_path):
            raise FileNotFoundError(f"Scaler file not found: {scaler_path}")
        
        # Load the scaler
        try:
            logger.info(f"Loading scaler from {scaler_path}")
            
            # Try joblib first (more robust)
            if scaler_path.endswith('.joblib'):
                if joblib is None:
                    raise RuntimeError("joblib is required to load .joblib scaler files")
                self.scaler = joblib.load(scaler_path)
                self.scaler_type = 'joblib'
            else:
                # Try pickle as fallback
                with open(scaler_path, 'rb') as f:
                    self.scaler = pickle.load(f)
                self.scaler_type = 'pickle'
            
            self.scaler_path = scaler_path
            self.is_loaded = True
            
            logger.info(
                f"Scaler loaded successfully from {scaler_path} "
                f"(type: {self.scaler_type})"
            )
            
            # Validate scaler has expected methods
            if not hasattr(self.scaler, 'transform'):
                raise ValueError("Scaler does not have transform method")

            # Validate feature compatibility when available
            if hasattr(self.scaler, 'n_features_in_'):
                n_features = int(self.scaler.n_features_in_)
                if n_features != EXPECTED_FEATURE_COUNT:
                    raise ValueError(
                        f"Scaler feature count mismatch: expected {EXPECTED_FEATURE_COUNT}, got {n_features}"
                    )
                logger.info(f"Scaler input dimension validated: {n_features}")
            
            # Check if scaler has scale_ attribute (for StandardScaler)
            if hasattr(self.scaler, 'scale_'):
                logger.info(
                    f"Scaler mean: {self.scaler.mean_ if hasattr(self.scaler, 'mean_') else 'N/A'}, "
                    f"scale: {self.scaler.scale_ if hasattr(self.scaler, 'scale_') else 'N/A'}"
                )
            
            return True
            
        except Exception as e:
            logger.error(f"Failed to load scaler: {str(e)}")
            raise
    
    def transform(self, features: np.ndarray) -> np.ndarray:
        """
        Transform features using scaler.
        
        Args:
            features: Input features array (shape: (N, D) or (D,))
            
        Returns:
            Scaled features array
            
        Raises:
            RuntimeError: If scaler not loaded
        """
        if not self.is_loaded or self.scaler is None:
            raise RuntimeError("Scaler not loaded")
        
        try:
            # Ensure features are 2D
            if features.ndim == 1:
                features = features.reshape(1, -1)

            if features.shape[1] != EXPECTED_FEATURE_COUNT:
                raise ValueError(
                    f"Feature count mismatch for scaling: expected {EXPECTED_FEATURE_COUNT}, got {features.shape[1]}"
                )
            
            scaled = self.scaler.transform(features)
            return scaled
            
        except Exception as e:
            logger.error(f"Scaler transform failed: {str(e)}")
            raise RuntimeError(f"Feature scaling failed: {str(e)}")
    
    def get_scaler_info(self) -> dict:
        """
        Get information about loaded scaler.
        
        Returns:
            Dictionary with scaler metadata
        """
        info = {
            "is_loaded": self.is_loaded,
            "scaler_path": self.scaler_path,
            "scaler_type": self.scaler_type,
        }
        
        if self.scaler is not None:
            info["scaler_class"] = self.scaler.__class__.__name__
            
            # Add scaler-specific info if available
            if hasattr(self.scaler, 'n_features_in_'):
                info["input_dim"] = int(self.scaler.n_features_in_)
            if hasattr(self.scaler, 'scale_'):
                info["scale_shape"] = list(self.scaler.scale_.shape)
            if hasattr(self.scaler, 'mean_'):
                info["mean_shape"] = list(self.scaler.mean_.shape)
        
        return info
    
    def cleanup(self) -> None:
        """Clean up scaler resources."""
        if self.scaler is not None:
            del self.scaler
            self.scaler = None
            self.is_loaded = False
            logger.info("Scaler cleaned up")


# Global scaler instance
_scaler_manager: Optional[ScalerManager] = None


def get_scaler_manager() -> ScalerManager:
    """
    Get global scaler manager instance.
    
    Returns:
        ScalerManager instance
        
    Raises:
        RuntimeError: If scaler manager not initialized
    """
    global _scaler_manager
    if _scaler_manager is None:
        raise RuntimeError("Scaler manager not initialized")
    return _scaler_manager


def initialize_scaler() -> ScalerManager:
    """
    Initialize the global scaler manager.
    
    Returns:
        Initialized ScalerManager
    """
    global _scaler_manager
    _scaler_manager = ScalerManager()
    
    _scaler_manager.load_scaler()

    return _scaler_manager
