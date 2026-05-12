from app.database.db import engine, SessionLocal
from app.database.models import Base, Weight, WeightAudit
import json


def init_db():
    Base.metadata.create_all(bind=engine)


def get_weights_db():
    db = SessionLocal()
    try:
        rows = db.query(Weight).all()
        return {r.key: r.value for r in rows}
    finally:
        db.close()


def set_weights_db(weights: dict):
    db = SessionLocal()
    try:
        for k, v in weights.items():
            row = db.query(Weight).filter(Weight.key == k).first()
            if row:
                row.value = float(v)
            else:
                db.add(Weight(key=k, value=float(v)))
        db.commit()
    finally:
        db.close()


def record_weight_audit(actor: str, payload: dict):
    db = SessionLocal()
    try:
        db.add(WeightAudit(actor=actor, payload=json.dumps(payload)))
        db.commit()
    finally:
        db.close()


def get_weight_audit_logs(limit: int = 50, offset: int = 0):
    db = SessionLocal()
    try:
        rows = (
            db.query(WeightAudit)
            .order_by(WeightAudit.created_at.desc())
            .offset(offset)
            .limit(limit)
            .all()
        )

        items = []
        for row in rows:
            try:
                payload = json.loads(row.payload) if row.payload else {}
            except Exception:
                payload = {"raw": row.payload}

            items.append(
                {
                    "id": row.id,
                    "actor": row.actor,
                    "payload": payload,
                    "created_at": row.created_at.isoformat() if row.created_at else None,
                }
            )

        return items
    finally:
        db.close()
