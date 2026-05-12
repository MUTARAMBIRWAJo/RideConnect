"""Driver ranking service"""
from typing import Optional

import numpy as np

from app.core.logging import get_logger
from app.schemas.match_response import RankedDriver

logger = get_logger(__name__)


class RankingService:
    """Handles driver ranking and re-ranking"""
    
    def rank_drivers(
        self,
        driver_ids: list[int],
        scores: np.ndarray,
        filters: Optional[dict] = None
    ) -> list[RankedDriver]:
        """
        Rank drivers by score with optional filters
        
        Args:
            driver_ids: List of driver IDs
            scores: Array of scores for each driver
            filters: Optional filters to apply
        
        Returns:
            List of RankedDriver objects sorted by score descending
        """
        if len(driver_ids) != len(scores):
            raise ValueError("driver_ids and scores must have same length")
        
        # Create ranking with filters
        ranked = []
        for driver_id, score in zip(driver_ids, scores):
            if filters and self._should_filter(driver_id, filters):
                continue
            
            ranked.append(RankedDriver(
                driver_id=driver_id,
                score=float(score)
            ))
        
        # Sort by score descending
        ranked.sort(key=lambda x: x.score, reverse=True)
        
        return ranked
    
    def _should_filter(self, driver_id: int, filters: dict) -> bool:
        """
        Check if driver should be filtered out
        
        Args:
            driver_id: Driver ID
            filters: Filter dictionary
        
        Returns:
            True if driver should be filtered, False otherwise
        """
        # Excluded drivers filter
        if "excluded_driver_ids" in filters:
            if driver_id in filters["excluded_driver_ids"]:
                return True
        
        # Minimum score filter
        if "min_score" in filters:
            # This would be checked earlier, but kept for completeness
            pass
        
        return False
    
    def re_rank_by_business_rules(
        self,
        ranked_drivers: list[RankedDriver],
        boost_factors: Optional[dict] = None
    ) -> list[RankedDriver]:
        """
        Re-rank drivers based on business rules and boost factors
        
        Args:
            ranked_drivers: Already ranked drivers
            boost_factors: Optional boost factors (e.g., loyalty, promotion)
        
        Returns:
            Re-ranked drivers
        """
        if not boost_factors:
            return ranked_drivers
        
        # Apply boost factors to scores
        updated_drivers = []
        for ranked_driver in ranked_drivers:
            driver_id = ranked_driver.driver_id
            original_score = ranked_driver.score
            
            # Apply boosts
            boost = boost_factors.get(driver_id, 1.0)
            new_score = min(1.0, original_score * boost)
            
            updated_drivers.append(RankedDriver(
                driver_id=driver_id,
                score=new_score
            ))
        
        # Re-sort with new scores
        updated_drivers.sort(key=lambda x: x.score, reverse=True)
        
        return updated_drivers
    
    def filter_by_score(
        self,
        ranked_drivers: list[RankedDriver],
        min_score: float = 0.0,
        max_results: Optional[int] = None
    ) -> list[RankedDriver]:
        """
        Filter drivers by score threshold
        
        Args:
            ranked_drivers: Ranked drivers
            min_score: Minimum acceptable score
            max_results: Maximum number of results
        
        Returns:
            Filtered drivers
        """
        filtered = [d for d in ranked_drivers if d.score >= min_score]
        
        if max_results:
            filtered = filtered[:max_results]
        
        return filtered
