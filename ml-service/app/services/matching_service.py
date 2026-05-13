"""Driver matching service"""
import time
from concurrent.futures import ThreadPoolExecutor, TimeoutError as FuturesTimeoutError
from typing import Optional

import numpy as np

from app.core.config import settings
from app.core.feature_config import EXPECTED_FEATURE_COUNT
from app.core.logging import get_logger
from app.core.startup import get_model_loader
from app.schemas.match_request import MatchRequestPayload
from app.schemas.match_response import BestDriver, MatchDriverResponse, RankedDriver
from app.services.metrics import MetricsBuilder, get_metrics_collector
from app.services.preprocessing_service import FeatureEngineeringService
from app.middleware import get_request_id
from app.utils.validators import validate_candidate_driver, validate_ride_request

logger = get_logger(__name__)


class MatchingService:
    """Handles driver matching and ranking"""
    
    def __init__(self):
        """Initialize matching service"""
        self.feature_engineer = FeatureEngineeringService()
    
    def match_drivers(
        self,
        request: MatchRequestPayload,
        enable_timing: bool = False
    ) -> MatchDriverResponse:
        """
        Match drivers to a ride request using ML model
        
        Args:
            request: MatchRequestPayload with ride and candidate drivers
            enable_timing: Whether to log timing metrics
        
        Returns:
            MatchDriverResponse with best driver and ranking
        
        Raises:
            ValueError: If validation fails
            RuntimeError: If model inference fails
        """
        start_time = time.time() if enable_timing else None
        preprocessing_start = time.time()
        request_id = "unknown"
        try:
            request_id = get_request_id()
        except Exception:
            pass
        
        # Validate ride request
        is_valid, error_msg = validate_ride_request(request.ride_request)
        if not is_valid:
            raise ValueError(f"Invalid ride request: {error_msg}")
        
        if not request.candidate_drivers:
            raise ValueError("No candidate drivers provided")
        
        # Validate all candidate drivers
        for driver in request.candidate_drivers:
            is_valid, error_msg = validate_candidate_driver(driver)
            if not is_valid:
                raise ValueError(f"Invalid driver {driver.driver_id}: {error_msg}")
        
        # Engineer features for each candidate driver
        driver_features_list = []
        driver_ids = []
        
        for driver in request.candidate_drivers:
            try:
                features = self.feature_engineer.engineer_features(
                    driver,
                    request.ride_request
                )
                driver_features_list.append(features)
                driver_ids.append(driver.driver_id)
                
            except Exception as e:
                logger.error(
                    f"Error engineering features for driver {driver.driver_id}: {str(e)}"
                )
                raise
        
        if enable_timing:
            preprocessing_time = time.time() - preprocessing_start
            logger.info(
                f"Feature engineering took {preprocessing_time:.3f}s"
            )
        else:
            preprocessing_time = 0.0
        
        # Get model predictions directly from the trained Keras model.
        # The current repository does not ship a valid scaler artifact, so the
        # model must be able to consume the engineered feature order as-is.
        model_loader = get_model_loader()
        
        # Stack features into batch
        feature_batch = np.vstack(driver_features_list).astype(np.float32)

        # If model is dual-input, matching flow must be updated to provide temporal+zone inputs.
        try:
            is_dual = model_loader._is_dual_input()
        except Exception:
            # Fallback to inspecting model inputs
            is_dual = hasattr(model_loader.model, 'inputs') and len(model_loader.model.inputs) == 2

        if is_dual:
            raise RuntimeError(
                "Dual-input V2 LSTM detected; MatchingService currently supports single-input models only. "
                "Update the service to provide temporal and zone inputs for the model."
            )

        if feature_batch.shape[1] != EXPECTED_FEATURE_COUNT:
            raise ValueError(
                f"Feature batch mismatch: expected {EXPECTED_FEATURE_COUNT}, got {feature_batch.shape[1]}"
            )

        scaler_time = 0.0
        if enable_timing:
            inference_start = time.time()
        
        try:
            with ThreadPoolExecutor(max_workers=1) as executor:
                future = executor.submit(model_loader.predict, feature_batch)
                predictions = future.result(timeout=settings.INFERENCE_TIMEOUT)
            
            if enable_timing:
                inference_time = time.time() - inference_start
                logger.info(
                    f"Model inference took {inference_time:.3f}s"
                )
        except FuturesTimeoutError as e:
            logger.error(f"Model inference timed out after {settings.INFERENCE_TIMEOUT}s")
            raise RuntimeError(
                f"Inference timed out after {settings.INFERENCE_TIMEOUT}s"
            ) from e
            
        except Exception as e:
            logger.error(f"Model inference failed: {str(e)}")
            raise RuntimeError(f"Failed to make predictions: {str(e)}")
        
        # Handle model output
        postprocessing_start = time.time()
        scores = self._extract_scores(predictions)
        
        # Rank drivers by score
        ranked_indices = np.argsort(-scores)  # Sort descending
        
        # Create response
        ranked_drivers = []
        for idx in ranked_indices:
            ranked_drivers.append(
                RankedDriver(
                    driver_id=driver_ids[idx],
                    score=float(scores[idx])
                )
            )
        
        best_driver = ranked_drivers[0]
        postprocessing_time = time.time() - postprocessing_start
        
        if enable_timing:
            logger.info(
                f"Total matching time {time.time() - start_time:.3f}s"
            )

        get_metrics_collector().record_metrics(
            MetricsBuilder(
                request_id=request_id,
                endpoint="/predict/match-driver",
                batch_size=len(driver_ids),
            )
            .set_preprocessing_time(preprocessing_time)
            .set_scaler_time(scaler_time)
            .set_inference_time(inference_time if enable_timing else 0.0)
            .set_postprocessing_time(postprocessing_time)
            .set_output_shape(predictions.shape)
            .set_num_candidates(len(driver_ids))
            .build()
        )
        
        return MatchDriverResponse(
            best_driver=BestDriver(
                driver_id=best_driver.driver_id,
                score=best_driver.score
            ),
            ranked_drivers=ranked_drivers
        )
    
    def _extract_scores(self, predictions: np.ndarray) -> np.ndarray:
        """
        Extract scores from model predictions
        
        Args:
            predictions: Model predictions output
        
        Returns:
            Scores array (0-1)
        """
        # Handle different model output formats
        if len(predictions.shape) == 1:
            # Single output per sample
            scores = predictions
        elif len(predictions.shape) == 2:
            if predictions.shape[1] == 1:
                # Shape (batch, 1) - take first column
                scores = predictions[:, 0]
            else:
                # Shape (batch, classes) - take maximum (best match)
                scores = np.max(predictions, axis=1)
        else:
            raise ValueError(f"Unexpected prediction shape: {predictions.shape}")
        
        # Ensure scores are in 0-1 range using sigmoid if needed
        scores = np.clip(scores, 0, 1)
        
        return scores
