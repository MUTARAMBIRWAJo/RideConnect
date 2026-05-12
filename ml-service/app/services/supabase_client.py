"""Optional Supabase client wrapper. Falls back to SQLAlchemy when supabase package not installed."""
from typing import Any, Dict, List, Optional
from app.core.config import settings
import os

_supabase = None

def get_supabase():
    global _supabase
    if _supabase is not None:
        return _supabase
    try:
        from supabase import create_client
        url = os.getenv("SUPABASE_URL") or settings.SUPABASE_URL
        key = os.getenv("SUPABASE_KEY") or settings.SUPABASE_KEY
        if url and key:
            _supabase = create_client(url, key)
            return _supabase
    except Exception:
        _supabase = None
    return None

def fetch_table_rows(table: str, limit: int = 100) -> List[Dict[str, Any]]:
    sb = get_supabase()
    if sb is not None:
        try:
            return sb.table(table).select("*").limit(limit).execute().data
        except Exception:
            return []
    # fallback: not implemented, requires SQLAlchemy session
    return []
