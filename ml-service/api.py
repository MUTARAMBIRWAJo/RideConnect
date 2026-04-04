"""Compatibility ASGI entrypoint for Docker runtime.

This module exposes `app` for `uvicorn api:app` while keeping
implementation in main.py.
"""

from main import app
