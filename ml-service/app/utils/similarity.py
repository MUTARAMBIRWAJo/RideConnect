"""Utility functions for similarity calculations"""
import math
from typing import Optional


def cosine_similarity(vec1: list[float], vec2: list[float]) -> float:
    """
    Calculate cosine similarity between two vectors
    
    Args:
        vec1: First vector
        vec2: Second vector
    
    Returns:
        Cosine similarity (0-1)
    """
    if len(vec1) != len(vec2):
        raise ValueError("Vectors must have same length")
    
    dot_product = sum(a * b for a, b in zip(vec1, vec2))
    magnitude1 = math.sqrt(sum(a * a for a in vec1))
    magnitude2 = math.sqrt(sum(b * b for b in vec2))
    
    if magnitude1 == 0 or magnitude2 == 0:
        return 0.0
    
    return dot_product / (magnitude1 * magnitude2)


def euclidean_distance(vec1: list[float], vec2: list[float]) -> float:
    """
    Calculate Euclidean distance between two vectors
    
    Args:
        vec1: First vector
        vec2: Second vector
    
    Returns:
        Euclidean distance
    """
    if len(vec1) != len(vec2):
        raise ValueError("Vectors must have same length")
    
    return math.sqrt(sum((a - b) ** 2 for a, b in zip(vec1, vec2)))


def jaccard_similarity(set1: set, set2: set) -> float:
    """
    Calculate Jaccard similarity between two sets
    
    Args:
        set1: First set
        set2: Second set
    
    Returns:
        Jaccard similarity (0-1)
    """
    intersection = len(set1 & set2)
    union = len(set1 | set2)
    
    if union == 0:
        return 1.0
    
    return intersection / union


def normalize_value(
    value: float,
    min_val: float = 0.0,
    max_val: float = 1.0,
    clip: bool = True
) -> float:
    """
    Normalize value to 0-1 range
    
    Args:
        value: Value to normalize
        min_val: Minimum value
        max_val: Maximum value
        clip: Whether to clip to 0-1 range
    
    Returns:
        Normalized value
    """
    if max_val == min_val:
        return 0.5
    
    normalized = (value - min_val) / (max_val - min_val)
    
    if clip:
        return max(0.0, min(1.0, normalized))
    
    return normalized


def inverse_normalize(
    value: float,
    min_val: float = 0.0,
    max_val: float = 1.0
) -> float:
    """
    Inverse normalization (higher is better becomes lower is better)
    
    Args:
        value: Value to inverse normalize
        min_val: Minimum value
        max_val: Maximum value
    
    Returns:
        Inverse normalized value (0-1)
    """
    normalized = normalize_value(value, min_val, max_val)
    return 1.0 - normalized


def weighted_average(values: list[float], weights: list[float]) -> float:
    """
    Calculate weighted average
    
    Args:
        values: List of values
        weights: List of weights
    
    Returns:
        Weighted average
    """
    if len(values) != len(weights):
        raise ValueError("Values and weights must have same length")
    
    if sum(weights) == 0:
        return 0.0
    
    return sum(v * w for v, w in zip(values, weights)) / sum(weights)
