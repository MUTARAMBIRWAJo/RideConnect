import math


def haversine_distance(lat1, lon1, lat2, lon2):
    # returns distance in kilometers
    if None in (lat1, lon1, lat2, lon2):
        return float("inf")
    R = 6371.0
    phi1 = math.radians(lat1)
    phi2 = math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlambda = math.radians(lon2 - lon1)
    a = math.sin(dphi/2.0)**2 + math.cos(phi1) * math.cos(phi2) * math.sin(dlambda/2.0)**2
    return R * 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))


def cosine_similarity(heading1, heading2):
    # headings are degrees; convert to unit vectors and compute cosine
    import math
    a1 = math.radians(heading1 or 0.0)
    a2 = math.radians(heading2 or 0.0)
    x1, y1 = math.cos(a1), math.sin(a1)
    x2, y2 = math.cos(a2), math.sin(a2)
    dot = x1 * x2 + y1 * y2
    denom = math.sqrt(x1*x1 + y1*y1) * math.sqrt(x2*x2 + y2*y2)
    if denom == 0:
        return 0.5
    return max(0.0, min(1.0, (dot / denom + 1) / 2))
