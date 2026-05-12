"""CLI to enqueue training samples from Supabase into Redis/RQ."""
from app.services.etl import enqueue_training_samples


def main():
    n = enqueue_training_samples(limit=50)
    print(f"Enqueued {n} training samples")


if __name__ == "__main__":
    main()
