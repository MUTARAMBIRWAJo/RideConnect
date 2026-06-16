from sqlalchemy import create_engine, text
engine = create_engine("postgresql+psycopg2://postgres.tpahuvmhlfluztuhznfj:rOnptMsAAnTbrpIY@aws-1-us-east-1.pooler.supabase.com:5432/postgres?sslmode=require")
with engine.connect() as conn:
    print(list(conn.execute(text("SELECT count(*) FROM trips"))))
