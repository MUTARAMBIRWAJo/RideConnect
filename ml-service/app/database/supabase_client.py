"""Supabase client"""
from typing import Optional

from app.core.config import settings
from app.core.logging import get_logger

logger = get_logger(__name__)


class SupabaseClient:
    """Client for interacting with Supabase"""
    
    def __init__(self):
        """Initialize Supabase client"""
        self.url = settings.SUPABASE_URL
        self.key = settings.SUPABASE_KEY
        self._client = None
    
    async def initialize(self) -> None:
        """Initialize Supabase connection"""
        if not self.url or not self.key:
            logger.warning("Supabase credentials not configured")
            return
        
        try:
            # Import here to make it optional
            import supabase
            
            self._client = supabase.create_client(self.url, self.key)
            logger.info("Supabase client initialized")
        
        except ImportError:
            logger.warning("supabase package not installed - Supabase features disabled")
        except Exception as e:
            logger.error(f"Failed to initialize Supabase: {str(e)}")
    
    async def get_driver_metrics(self, driver_id: int) -> Optional[dict]:
        """
        Get driver metrics from Supabase
        
        Args:
            driver_id: Driver ID
        
        Returns:
            Driver metrics or None
        """
        if not self._client:
            return None
        
        try:
            response = self._client.table("drivers").select("*").eq("id", driver_id).execute()
            
            if response.data:
                return response.data[0]
            
            return None
        
        except Exception as e:
            logger.error(f"Failed to get driver metrics: {str(e)}")
            return None
    
    async def get_active_drivers(self, limit: int = 100) -> Optional[list]:
        """
        Get active drivers from Supabase
        
        Args:
            limit: Maximum number of drivers
        
        Returns:
            List of active drivers or None
        """
        if not self._client:
            return None
        
        try:
            response = (
                self._client.table("drivers")
                .select("*")
                .eq("is_online", True)
                .limit(limit)
                .execute()
            )
            
            return response.data if response.data else []
        
        except Exception as e:
            logger.error(f"Failed to get active drivers: {str(e)}")
            return None
