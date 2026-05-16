"""JSON response helpers."""

from __future__ import annotations

import json
from typing import Any

import numpy as np
from fastapi.responses import JSONResponse


class NumpySafeJSONResponse(JSONResponse):
    """JSONResponse that converts NumPy scalar/array values."""

    def render(self, content: Any) -> bytes:
        return json.dumps(
            content,
            default=self._convert,
            ensure_ascii=False,
            allow_nan=False,
            separators=(",", ":"),
        ).encode("utf-8")

    @staticmethod
    def _convert(obj: Any) -> Any:
        if isinstance(obj, np.bool_):
            return bool(obj)
        if isinstance(obj, np.integer):
            return int(obj)
        if isinstance(obj, np.floating):
            return float(obj)
        if isinstance(obj, np.ndarray):
            return obj.tolist()

        raise TypeError(f"Object of type {type(obj).__name__} is not JSON serializable")
