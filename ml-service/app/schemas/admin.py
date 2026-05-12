"""Schemas for admin endpoints."""

from __future__ import annotations

from datetime import datetime
from typing import Any

from pydantic import BaseModel, Field


class WeightAuditLogItem(BaseModel):
    id: int = Field(..., description="Audit entry identifier")
    actor: str = Field(..., description="Actor that changed the weights")
    payload: dict[str, Any] = Field(..., description="Submitted weight payload")
    created_at: datetime | None = Field(None, description="Audit timestamp")


class WeightAuditLogResponse(BaseModel):
    items: list[WeightAuditLogItem] = Field(default_factory=list)
    total: int = Field(..., ge=0)
    limit: int = Field(..., ge=1)
    offset: int = Field(..., ge=0)