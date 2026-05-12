from sqlalchemy import Column, Integer, String, Float, Text, DateTime
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.sql import func

Base = declarative_base()


class Weight(Base):
    __tablename__ = "ml_weights"
    id = Column(Integer, primary_key=True)
    key = Column(String(128), unique=True, nullable=False)
    value = Column(Float, nullable=False)


class WeightAudit(Base):
    __tablename__ = "ml_weights_audit"
    id = Column(Integer, primary_key=True)
    actor = Column(String(128), nullable=False)
    payload = Column(Text, nullable=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
