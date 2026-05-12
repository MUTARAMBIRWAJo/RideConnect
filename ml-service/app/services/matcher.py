from typing import Dict, List
from app.utils.geo import haversine_distance, cosine_similarity
from app.core.config import settings
from app.core.weights import get_weights
import os


def _normalize(value, min_v, max_v):
    if max_v == min_v:
        return 0.0
    return (value - min_v) / (max_v - min_v)


class MatcherModel:
    def __init__(self):
        self.model = None
        self._loaded = False

    def load(self):
        if self._loaded:
            return
        self._loaded = True
        # try latest known filenames
        candidates = [
            os.path.join(settings.MODEL_DIR, "matcher_latest.joblib"),
            os.path.join(settings.MODEL_DIR, "matcher_v0.joblib"),
            os.path.join(settings.MODEL_DIR, "matcher.joblib"),
        ]
        for p in candidates:
            if os.path.exists(p):
                # try joblib first, then pickle
                try:
                    import joblib
                    self.model = joblib.load(p)
                    return
                except Exception:
                    try:
                        import pickle
                        with open(p, "rb") as f:
                            self.model = pickle.load(f)
                            return
                    except Exception:
                        self.model = None


_matcher_model = MatcherModel()


def match_drivers(ride_request: Dict, candidate_drivers: List[Dict]) -> List[Dict]:
    """
    Weighted scoring engine for driver matching.
    If a trained matcher model exists it will be used to score candidates; otherwise fall back to heuristic.
    Returns a list of ranked drivers with scores and predicted ETA.
    """
    # try to load model
    _matcher_model.load()

    # heuristic weights (loadable)
    weights = get_weights()

    px, py = ride_request.get("pickup_latitude"), ride_request.get("pickup_longitude")

    distances = []
    ratings = []
    acceptance = []
    cancellation = []
    behavior = []
    for d in candidate_drivers:
        dist = haversine_distance(px, py, d.get("current_latitude"), d.get("current_longitude"))
        distances.append(dist)
        ratings.append(d.get("driver_rating", 0.0))
        acceptance.append(d.get("acceptance_rate", 0.0))
        cancellation.append(d.get("cancellation_rate", 0.0))
        behavior.append(d.get("behavior_score", 0.0))

    min_dist, max_dist = (min(distances), max(distances)) if distances else (0, 1)
    min_rating, max_rating = (min(ratings), max(ratings)) if ratings else (0, 5)
    min_acc, max_acc = (min(acceptance), max(acceptance)) if acceptance else (0, 1)
    min_can, max_can = (min(cancellation), max(cancellation)) if cancellation else (0, 1)
    min_beh, max_beh = (min(behavior), max(behavior)) if behavior else (0, 1)

    ranked = []
    for d in candidate_drivers:
        dist = haversine_distance(px, py, d.get("current_latitude"), d.get("current_longitude"))
        ndist = 1.0 - _normalize(dist, min_dist, max_dist)
        nrating = _normalize(d.get("driver_rating", 0.0), min_rating, max_rating)
        nacc = _normalize(d.get("acceptance_rate", 0.0), min_acc, max_acc)
        ncan = 1.0 - _normalize(d.get("cancellation_rate", 0.0), min_can, max_can)
        nbeh = _normalize(d.get("behavior_score", 0.0), min_beh, max_beh)

        dir_sim = 0.5
        try:
            dir_sim = cosine_similarity(ride_request.get("pickup_heading", 0.0), d.get("current_heading", 0.0))
        except Exception:
            dir_sim = 0.5

        # If we have a trained model, use it
        if _matcher_model.model is not None:
            try:
                # construct feature vector: [dist, rating, acceptance, cancellation, behavior, direction, seats]
                feat = [
                    dist,
                    d.get("driver_rating", 0.0),
                    d.get("acceptance_rate", 0.0),
                    d.get("cancellation_rate", 0.0),
                    d.get("behavior_score", 0.0),
                    dir_sim,
                    d.get("available_seats", 1),
                ]
                import numpy as np
                score = float(_matcher_model.model.predict(np.array([feat]))[0])
            except Exception:
                score = (
                    weights["distance"] * ndist
                    + weights["rating"] * nrating
                    + weights["acceptance"] * nacc
                    + weights["cancellation"] * ncan
                    + weights["behavior"] * nbeh
                    + weights["direction"] * dir_sim
                )
        else:
            score = (
                weights["distance"] * ndist
                + weights["rating"] * nrating
                + weights["acceptance"] * nacc
                + weights["cancellation"] * ncan
                + weights["behavior"] * nbeh
                + weights["direction"] * dir_sim
            )

        eta = max(30, int(dist / 0.5 * 60))

        ranked.append({
            "driver_id": d.get("driver_id"),
            "score": score,
            "eta_seconds": eta,
            "distance_km": dist,
        })

    ranked.sort(key=lambda x: x["score"], reverse=True)
    return ranked
