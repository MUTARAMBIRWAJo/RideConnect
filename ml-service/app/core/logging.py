"""Structured logging configuration"""
import json
import logging
import sys
from typing import Any, Dict

from .config import settings


class JSONFormatter(logging.Formatter):
    """JSON log formatter for structured logging"""
    
    def format(self, record: logging.LogRecord) -> str:
        """Format log record as JSON"""
        log_data: Dict[str, Any] = {
            "timestamp": self.formatTime(record),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
        }
        
        if record.exc_info:
            log_data["exception"] = self.formatException(record.exc_info)
        
        if hasattr(record, "user_id"):
            log_data["user_id"] = record.user_id
        
        if hasattr(record, "request_id"):
            log_data["request_id"] = record.request_id
        
        return json.dumps(log_data)


def get_logger(name: str) -> logging.Logger:
    """
    Get configured logger instance with JSON formatting
    
    Args:
        name: Logger name (typically __name__)
    
    Returns:
        Configured logger instance
    """
    logger = logging.getLogger(name)
    
    if not logger.handlers:
        handler = logging.StreamHandler(sys.stdout)
        formatter = JSONFormatter()
        handler.setFormatter(formatter)
        logger.addHandler(handler)

    log_level = settings.LOG_LEVEL
    if log_level.isdigit():
        logger.setLevel(int(log_level))
    else:
        level = getattr(logging, log_level, None)
        if not isinstance(level, int):
            raise ValueError(f"Invalid LOG_LEVEL value: {log_level}")
        logger.setLevel(level)

    logger.propagate = False
    
    return logger
