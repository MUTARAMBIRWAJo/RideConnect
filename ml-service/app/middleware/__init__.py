"""Request context and correlation ID middleware."""

from __future__ import annotations

import contextvars
import time
import uuid
from typing import Awaitable, Callable

from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware
from starlette.responses import Response

from app.core.logging import get_logger

logger = get_logger(__name__)

# Context variables for request tracing
request_id_var: contextvars.ContextVar[str] = contextvars.ContextVar(
    'request_id', default=None
)
request_start_time_var: contextvars.ContextVar[float] = contextvars.ContextVar(
    'request_start_time', default=None
)
request_metadata_var: contextvars.ContextVar[dict] = contextvars.ContextVar(
    'request_metadata', default={}
)


class RequestContextMiddleware(BaseHTTPMiddleware):
    """
    Middleware for adding request context including correlation IDs.
    
    Adds X-Request-ID header, tracks request timing, and maintains
    context for logging across async operations.
    """
    
    async def dispatch(
        self, request: Request, call_next: Callable[[Request], Awaitable[Response]]
    ) -> Response:
        """
        Process request with context setup.
        
        Args:
            request: Incoming request
            call_next: Next middleware/handler
            
        Returns:
            Response with context tracking
        """
        # Generate or extract request ID
        request_id = self._get_or_create_request_id(request)
        request.state.request_id = request_id
        
        # Record start time
        start_time = time.time()
        
        # Set context variables
        request_id_var.set(request_id)
        request_start_time_var.set(start_time)
        
        # Extract metadata
        metadata = {
            "request_id": request_id,
            "method": request.method,
            "path": request.url.path,
            "client_host": request.client.host if request.client else None,
            "start_time": start_time,
        }
        request_metadata_var.set(metadata)
        
        # Log request start
        logger.info(
            f"Request started: {request.method} {request.url.path}",
            extra={
                "request_id": request_id,
                "method": request.method,
                "path": request.url.path,
            }
        )
        
        try:
            # Call next middleware/handler
            response = await call_next(request)
            
            # Add request ID to response headers
            response.headers["X-Request-ID"] = request_id
            
            # Calculate duration
            duration = time.time() - start_time
            
            # Log request completion
            logger.info(
                f"Request completed: {request.method} {request.url.path} "
                f"status={response.status_code} duration={duration:.3f}s",
                extra={
                    "request_id": request_id,
                    "method": request.method,
                    "path": request.url.path,
                    "status_code": response.status_code,
                    "duration_ms": duration * 1000,
                }
            )
            
            return response
            
        except Exception as e:
            # Calculate duration
            duration = time.time() - start_time
            
            # Log error
            logger.error(
                f"Request failed: {request.method} {request.url.path} "
                f"error={str(e)} duration={duration:.3f}s",
                extra={
                    "request_id": request_id,
                    "method": request.method,
                    "path": request.url.path,
                    "duration_ms": duration * 1000,
                    "error": str(e),
                },
                exc_info=True
            )
            raise
    
    @staticmethod
    def _get_or_create_request_id(request: Request) -> str:
        """
        Get request ID from header or create new one.
        
        Accepts:
        - X-Request-ID: Standard header
        - X-Correlation-ID: Alternative header
        - traceparent: W3C Trace Context format
        
        Args:
            request: Incoming request
            
        Returns:
            Request ID string
        """
        # Check for existing request ID headers
        request_id = request.headers.get("X-Request-ID")
        if request_id:
            return request_id
        
        request_id = request.headers.get("X-Correlation-ID")
        if request_id:
            return request_id
        
        # Check W3C Trace Context format
        traceparent = request.headers.get("traceparent")
        if traceparent:
            # Extract trace-id from "version-trace-id-parent-id-flags"
            parts = traceparent.split("-")
            if len(parts) >= 2:
                return parts[1][:16]  # Use first 16 hex chars
        
        # Generate new ID
        return str(uuid.uuid4())


def get_request_id() -> str:
    """
    Get current request ID from context.
    
    Returns:
        Request ID string
        
    Raises:
        RuntimeError: If request context not set
    """
    request_id = request_id_var.get()
    if request_id is None:
        raise RuntimeError("Request ID not set in context")
    return request_id


def get_request_metadata() -> dict:
    """
    Get current request metadata from context.
    
    Returns:
        Request metadata dictionary
    """
    return request_metadata_var.get()


def get_request_start_time() -> float:
    """
    Get request start time from context.
    
    Returns:
        Start time as Unix timestamp
    """
    return request_start_time_var.get()


def get_request_elapsed_time() -> float:
    """
    Get elapsed time since request start.
    
    Returns:
        Elapsed time in seconds
    """
    start_time = get_request_start_time()
    if start_time is None:
        return 0.0
    return time.time() - start_time
