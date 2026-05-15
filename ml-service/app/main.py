"""Main FastAPI application."""

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app.api import health, matching, prediction
from app.api.admin_routes import router as admin_router
from app.core.config import settings
from app.core.logging import get_logger
from app.core.startup import lifespan
from app.middleware import RequestContextMiddleware, get_request_id

logger = get_logger(__name__)


def create_app() -> FastAPI:
    """Create and configure the FastAPI application."""
    app = FastAPI(
        title=settings.APP_NAME,
        version=settings.APP_VERSION,
        description="Production-grade ML microservice for RideConnect driver matching",
        openapi_url="/docs/openapi.json",
        docs_url="/docs",
        redoc_url="/redoc",
        lifespan=lifespan,
    )

    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )
    app.add_middleware(RequestContextMiddleware)

    app.include_router(health.router)
    app.include_router(matching.router)
    app.include_router(prediction.router)
    app.include_router(admin_router, prefix="/api/admin")

    @app.get("/", tags=["info"])
    async def root() -> dict[str, object]:
        """Root endpoint with service information."""
        return {
            "service": settings.APP_NAME,
            "version": settings.APP_VERSION,
            "status": "running",
            "endpoints": {
                "health": "/health",
                "docs": "/docs",
                "matching": "/predict/match-driver",
                "demand": "/predict/demand",
                "eta": "/predict/eta",
                "admin_weights": "/api/admin/weights",
                "admin_weight_audit": "/api/admin/weights/audit",
            },
        }

    @app.exception_handler(ValueError)
    async def value_error_handler(request: Request, exc: ValueError):
        """Handle ValueError exceptions."""
        logger.error(f"ValueError: {exc}")
        try:
            request_id = get_request_id()
        except Exception:
            request_id = getattr(request.state, "request_id", None)
        return JSONResponse(
            status_code=400,
            content={
                "detail": str(exc),
                "error_code": "VALIDATION_ERROR",
                "request_id": request_id,
            },
        )

    @app.exception_handler(RuntimeError)
    async def runtime_error_handler(request: Request, exc: RuntimeError):
        """Handle RuntimeError exceptions."""
        logger.error(f"RuntimeError: {exc}")
        try:
            request_id = get_request_id()
        except Exception:
            request_id = getattr(request.state, "request_id", None)
        return JSONResponse(
            status_code=500,
            content={
                "detail": str(exc),
                "error_code": "RUNTIME_ERROR",
                "request_id": request_id,
            },
        )

    @app.on_event("startup")
    async def startup_event() -> None:
        """Startup event handler."""
        logger.info(f"Starting {settings.APP_NAME} v{settings.APP_VERSION}")
        logger.info(f"Debug mode: {settings.DEBUG}")
        logger.info(f"Model path: {settings.MODEL_PATH}")

    @app.on_event("shutdown")
    async def shutdown_event() -> None:
        """Shutdown event handler."""
        logger.info(f"Shutting down {settings.APP_NAME}")

    return app


app = create_app()
