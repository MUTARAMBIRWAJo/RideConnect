"""Database connection management"""
from typing import Optional

from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from app.core.config import settings
from app.core.logging import get_logger

logger = get_logger(__name__)

# SQLAlchemy engine and session factory
engine = None
SessionLocal = None


def initialize_db() -> None:
    """Initialize database connection"""
    global engine, SessionLocal
    
    database_url = settings.SUPABASE_DB_URL or settings.DATABASE_URL
    if not database_url:
        logger.warning("SUPABASE_DB_URL not configured - database features disabled")
        return
    
    try:
        engine = create_engine(
            database_url,
            pool_pre_ping=True,
            echo=settings.DEBUG
        )
        SessionLocal = sessionmaker(
            autocommit=False,
            autoflush=False,
            bind=engine
        )
        logger.info("Database connection initialized")
    except Exception as e:
        logger.error(f"Failed to initialize database: {str(e)}")
        raise


def get_db() -> Optional[Session]:
    """Get database session"""
    if SessionLocal is None:
        return None
    
    db = SessionLocal()
    try:
        return db
    except Exception as e:
        logger.error(f"Failed to get database session: {str(e)}")
        raise
    finally:
        db.close()
