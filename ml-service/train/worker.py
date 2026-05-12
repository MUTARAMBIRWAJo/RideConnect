"""RQ worker entrypoint to process retrain jobs.
Run with: `rq worker default` or use this module to spawn a worker.
"""
from train import train_demand_model, train_matching_model

def main():
    # functions are imported so RQ can import them by path
    return True

if __name__ == "__main__":
    main()
