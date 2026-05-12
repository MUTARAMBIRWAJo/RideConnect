import os
import pickle

MODEL_DIR = os.path.join(os.path.dirname(__file__), "..", "models", "trained")
os.makedirs(MODEL_DIR, exist_ok=True)


class DummyMatcher:
    def predict(self, X):
        # X is array-like of feature lists; return a simple score (sum normalized)
        out = []
        for row in X:
            s = 0.0
            for i, v in enumerate(row):
                s += float(v) * (1.0 / (i + 1))
            out.append(s)
        return out


def main():
    model = DummyMatcher()
    p = os.path.join(MODEL_DIR, "matcher_v0.joblib")
    # save as pickle-compatible file so matcher can load without joblib
    with open(p, "wb") as f:
        pickle.dump(model, f)
    print("Wrote dummy matcher to", p)


if __name__ == "__main__":
    main()
