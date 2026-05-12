"""FastAPI application lifespan management and startup/shutdown hooks."""

from __future__ import annotations

import asyncio
from contextlib import asynccontextmanager
from typing import AsyncGenerator

from app.core.config import settings
from app.core.logging import get_logger
from app.core.monitoring import initialize_monitoring
from app.core.scaler_manager import initialize_scaler, get_scaler_manager
from app.core.startup_validator import StartupValidator
from app.services.metrics import initialize_metrics_collector
from app.services.model_loader import initialize_model_loader, get_model_loader

logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app) -> AsyncGenerator[None, None]:
    """
    FastAPI lifespan context manager.
    
    Handles startup and shutdown events for the application.
    
    Args:
        app: FastAPI application instance
        
    Yields:
        During application lifetime
    """
    # ==================== STARTUP ====================
    logger.info("=" * 70)
    logger.info("ML SERVICE STARTUP SEQUENCE BEGINNING")
    logger.info("=" * 70)
    
    try:
        # Initialize metrics collector
        logger.info("Step 1/5: Initializing metrics collector...")
        initialize_metrics_collector()
        logger.info("✓ Metrics collector initialized")

        # Initialize monitoring hooks
        logger.info("Step 2/6: Initializing monitoring hooks...")
        app.state.monitoring = initialize_monitoring()
        logger.info("✓ Monitoring hooks initialized")
        
        # Initialize model loader
        logger.info("Step 3/6: Initializing model loader...")
        model_loader = initialize_model_loader()
        await model_loader.initialize()
        logger.info("✓ Model loaded and validated")
        
        # Initialize scaler manager if a real scaler artifact is available.
        # The current repository does not ship a valid scaler for the matching
        # model, so startup must continue when only the Keras model is present.
        logger.info("Step 4/6: Initializing scaler manager...")
        try:
            initialize_scaler()
            logger.info("✓ Scaler manager initialized")
        except Exception as scaler_error:
            logger.warning(f"Scaler initialization skipped: {scaler_error}")
            app.state.scaler_initialization_error = str(scaler_error)
        
        # Run comprehensive startup validation
        logger.info("Step 5/6: Running startup validation...")
        validation_results = await StartupValidator.validate_all()
        logger.info("✓ Startup validation passed")
        
        # Store results in app state for health checks
        logger.info("Step 6/6: Storing startup results in app state...")
        app.state.startup_validation = validation_results
        app.state.startup_time = _get_startup_time()
        logger.info("✓ Startup state stored")
        
        logger.info("=" * 70)
        logger.info("ML SERVICE STARTUP COMPLETE - SERVICE READY")
        logger.info("=" * 70)
        
    except Exception as e:
        logger.error("=" * 70)
        logger.error(f"STARTUP FAILED: {str(e)}")
        logger.error("=" * 70)
        raise
    
    # Yield control to FastAPI
    try:
        yield
    finally:
        # ==================== SHUTDOWN ====================
        logger.info("=" * 70)
        logger.info("ML SERVICE SHUTDOWN SEQUENCE BEGINNING")
        logger.info("=" * 70)
        
        try:
            # Clean up model
            logger.info("Cleaning up model loader...")
            model_loader = get_model_loader()
            model_loader.cleanup()
            logger.info("✓ Model cleaned up")
            
            # Clean up scaler
            logger.info("Cleaning up scaler manager...")
            scaler_manager = get_scaler_manager()
            scaler_manager.cleanup()
            logger.info("✓ Scaler cleaned up")
            
            logger.info("=" * 70)
            logger.info("ML SERVICE SHUTDOWN COMPLETE")
            logger.info("=" * 70)
            
        except Exception as e:
            logger.error(f"Error during shutdown: {str(e)}", exc_info=True)


def _get_startup_time() -> dict:
    """
    Get current startup timestamp information.
    
    Returns:
        Dictionary with timing information
    """
    import time
    return {
        "timestamp": time.time(),
        "timestamp_iso": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
