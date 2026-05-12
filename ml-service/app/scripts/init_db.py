"""One-shot database initialization helper."""

from __future__ import annotations

import sys

from app.core.logging import get_logger
from app.services.weights_db import init_db

logger = get_logger(__name__)


def main() -> int:
    """Create the ML service tables if they do not exist yet."""
    try:
        init_db()
        logger.info("ML service tables initialized")
        return 0
    except Exception as exc:
        logger.exception("Failed to initialize ML service tables: %s", exc)
        return 1


if __name__ == "__main__":
    sys.exit(main())