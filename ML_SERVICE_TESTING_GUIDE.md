# ML Service - Testing Guide

## Running Tests in Docker

All tests run inside Docker containers to ensure consistency with production environment.

---

## Quick Start

```bash
cd /home/joseph/projects/RideConnectBackend/ml-service

# Run all tests
docker-compose run --rm ml-service pytest tests/ -v

# Run specific test file
docker-compose run --rm ml-service pytest tests/test_api.py -v

# Run with coverage report
docker-compose run --rm ml-service pytest tests/ --cov=app --cov-report=html
```

---

## Test Suite Overview

### Available Tests

**tests/conftest.py**
- Pytest fixtures
- FastAPI TestClient setup
- Database fixtures
- Mock models

**tests/test_api.py**
- Health check endpoint tests
- Driver matching endpoint tests
- Demand prediction tests
- ETA prediction tests
- Input validation tests
- Error handling tests
- Authentication tests (admin endpoints)

### Test Categories

```python
# Health Check Tests
TestHealthEndpoint:
  - test_health_check_returns_200
  - test_health_check_model_status
  - test_health_check_json_structure

# Matching Endpoint Tests
TestMatchingEndpoint:
  - test_match_driver_success
  - test_match_driver_invalid_coordinates
  - test_match_driver_empty_candidates
  - test_match_driver_batch_performance
  - test_match_driver_score_range

# Demand Prediction Tests
TestDemandPrediction:
  - test_demand_prediction_valid_input
  - test_demand_prediction_response_structure
  - test_demand_prediction_confidence_range

# ETA Prediction Tests
TestETAPrediction:
  - test_eta_prediction_valid_input
  - test_eta_prediction_response_structure
  - test_eta_prediction_with_traffic
```

---

## Running Different Test Suites

### All Tests with Verbose Output

```bash
docker-compose run --rm ml-service pytest tests/ -v
```

**Output:**
```
tests/test_api.py::TestHealthEndpoint::test_health_check_returns_200 PASSED       [ 5%]
tests/test_api.py::TestHealthEndpoint::test_health_check_model_status PASSED      [10%]
tests/test_api.py::TestMatchingEndpoint::test_match_driver_success PASSED         [15%]
...
===================== 20 passed in 2.34s =====================
```

### Specific Test File

```bash
docker-compose run --rm ml-service pytest tests/test_api.py -v
```

### Specific Test Class

```bash
docker-compose run --rm ml-service pytest tests/test_api.py::TestMatchingEndpoint -v
```

### Specific Test Function

```bash
docker-compose run --rm ml-service pytest tests/test_api.py::TestMatchingEndpoint::test_match_driver_success -v
```

---

## Advanced Testing Options

### With Coverage Report

```bash
# Generate coverage report
docker-compose run --rm ml-service pytest tests/ \
  --cov=app \
  --cov-report=html \
  --cov-report=term-missing

# View HTML report (if running on desktop)
# coverage report will be in ml-service/htmlcov/index.html
```

### With Markers

```bash
# Run only fast tests
docker-compose run --rm ml-service pytest tests/ -m "not slow" -v

# Run only integration tests
docker-compose run --rm ml-service pytest tests/ -m "integration" -v
```

### With Output Capture

```bash
# Show print statements
docker-compose run --rm ml-service pytest tests/ -v -s

# Show all stdout
docker-compose run --rm ml-service pytest tests/ -v --capture=no
```

### With Timeout

```bash
# Fail if test takes >10 seconds
docker-compose run --rm ml-service pytest tests/ -v --timeout=10
```

### With Retry on Failure

```bash
# Retry failed tests up to 2 times
docker-compose run --rm ml-service pytest tests/ -v --reruns=2
```

---

## Debugging Tests

### Run Single Test with Debugging

```bash
# Enter pdb on test failure
docker-compose run --rm ml-service pytest tests/test_api.py::TestMatchingEndpoint::test_match_driver_success -v --pdb

# Enter pdb before each test
docker-compose run --rm ml-service pytest tests/test_api.py -v --pdb --trace
```

### View Full Error Output

```bash
# No output capture (see all prints and errors)
docker-compose run --rm ml-service pytest tests/ -v -s --tb=short

# Full traceback
docker-compose run --rm ml-service pytest tests/ -v --tb=long
```

### Collect Tests Without Running

```bash
# See what tests would run without executing
docker-compose run --rm ml-service pytest tests/ --collect-only
```

---

## Continuous Testing

### Watch for Changes (requires pytest-watch)

```bash
# Watch for file changes and re-run tests automatically
docker-compose run --rm ml-service ptw tests/ -- -v
```

### Run Tests on Commit

```bash
# Add to .git/hooks/pre-commit
#!/bin/bash
cd ml-service
docker-compose run --rm ml-service pytest tests/ -q
if [ $? -ne 0 ]; then
  echo "Tests failed. Commit aborted."
  exit 1
fi
```

---

## Test Configuration

### pytest.ini

```ini
[pytest]
testpaths = tests
python_files = test_*.py
python_classes = Test*
python_functions = test_*
addopts = -v --tb=short
markers =
    integration: integration tests
    slow: slow tests
    unit: unit tests
```

### conftest.py Fixtures

```python
@pytest.fixture
def test_client():
    """FastAPI TestClient for API testing."""
    from app.main import app
    return TestClient(app)

@pytest.fixture
def sample_ride_request():
    """Sample ride request for matching tests."""
    return {
        "pickup_latitude": -1.9441,
        "pickup_longitude": 30.0619,
        "destination_latitude": -1.9536,
        "destination_longitude": 30.1044,
        "requested_vehicle_type": "car",
        "required_seats": 3
    }

@pytest.fixture
def sample_candidates():
    """Sample candidate drivers for matching."""
    return [
        {
            "driver_id": 1,
            "distance_km": 1.2,
            "driver_rating": 4.8,
            "acceptance_rate": 92,
            "cancellation_rate": 2,
            "behavior_score": 88,
            "available_seats": 4,
            "traffic_level": 0.3,
            "direction_similarity": 0.9
        },
        {
            "driver_id": 2,
            "distance_km": 2.1,
            "driver_rating": 4.5,
            "acceptance_rate": 88,
            "cancellation_rate": 3,
            "behavior_score": 85,
            "available_seats": 4,
            "traffic_level": 0.5,
            "direction_similarity": 0.7
        }
    ]
```

---

## Test Execution Examples

### Complete Test Run with Report

```bash
# Run all tests, generate coverage, and save results
docker-compose run --rm ml-service bash -c \
  "pytest tests/ \
    -v \
    --cov=app \
    --cov-report=html \
    --cov-report=term-missing \
    --junitxml=test-results.xml \
    && echo 'Tests completed successfully!'"
```

### Parallel Testing (pytest-xdist)

```bash
# Run tests in parallel (4 workers)
docker-compose run --rm ml-service pytest tests/ -v -n 4
```

### Benchmark Tests (pytest-benchmark)

```bash
# Run with performance benchmarks
docker-compose run --rm ml-service pytest tests/ -v --benchmark-only
```

---

## Integration Tests

### Running with Real Database

```bash
# Tests use DATABASE_URL from .env
docker-compose run --rm ml-service pytest tests/ -v -m integration
```

### Running with Mock Database

```bash
# Skip database-dependent tests
docker-compose run --rm ml-service pytest tests/ -v -m "not integration"
```

---

## Docker-Based Test Workflows

### Test Before Deployment

```bash
#!/bin/bash
# Complete pre-deployment test

set -e  # Exit on error

cd /home/joseph/projects/RideConnectBackend/ml-service

echo "1. Building Docker image..."
docker-compose build --no-cache ml-service

echo "2. Running unit tests..."
docker-compose run --rm ml-service pytest tests/unit -v

echo "3. Running integration tests..."
docker-compose run --rm ml-service pytest tests/integration -v

echo "4. Running full test suite with coverage..."
docker-compose run --rm ml-service pytest tests/ \
  --cov=app \
  --cov-report=term-missing \
  --cov-fail-under=80

echo "5. All tests passed! Ready for deployment."
```

### Test with Multiple Python Versions

```bash
# Create multiple Dockerfile targets for testing
docker build --target py39 -t ml-service:py39 .
docker build --target py310 -t ml-service:py310 .
docker build --target py311 -t ml-service:py311 .

# Test each version
for version in py39 py310 py311; do
  docker run --rm ml-service:$version pytest tests/ -v
done
```

---

## CI/CD Pipeline Integration

### GitHub Actions

```yaml
name: Test ML Service

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v2
      
      - name: Build Docker image
        run: |
          cd ml-service
          docker-compose build ml-service
      
      - name: Run pytest
        run: |
          cd ml-service
          docker-compose run --rm ml-service pytest tests/ \
            -v \
            --cov=app \
            --cov-report=xml
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./ml-service/coverage.xml
```

### GitLab CI

```yaml
test_ml_service:
  image: docker:latest
  services:
    - docker:dind
  script:
    - cd ml-service
    - docker-compose build ml-service
    - docker-compose run --rm ml-service pytest tests/ -v --cov=app
  coverage: '/TOTAL.*?\s+(\d+%)$/'
```

---

## Performance Testing

### Load Testing with Locust

```bash
# Create tests/load_test.py
from locust import HttpUser, task

class LoadTest(HttpUser):
    @task
    def match_driver(self):
        self.client.post("/predict/match-driver", json={...})

# Run load test
docker-compose run --rm ml-service locust -f tests/load_test.py
```

### Profiling

```bash
# Profile test execution
docker-compose run --rm ml-service pytest tests/ \
  -v \
  --profile \
  --profile-svg

# Results in prof_data.svg
```

---

## Troubleshooting Test Issues

### Tests Fail Locally but Pass in CI

```bash
# Ensure using exact same environment
docker-compose run --rm ml-service bash -c "pip list"

# Check Python version
docker-compose run --rm ml-service python --version

# Run with verbose logging
docker-compose run --rm ml-service pytest tests/ -v -s --log-cli-level=DEBUG
```

### Timeout Issues

```bash
# Increase timeout for slow tests
docker-compose run --rm ml-service pytest tests/ -v --timeout=30
```

### Database Connection Errors

```bash
# Check database connectivity first
docker-compose run --rm ml-service python -c \
  "from app.database.db import engine; print(engine.connect())"
```

### Model Loading Fails in Tests

```bash
# Verify model file exists in container
docker-compose run --rm ml-service ls -la /app/models/trained/

# Check model path in config
docker-compose run --rm ml-service python -c \
  "from app.core.config import settings; print(settings.MODEL_PATH)"
```

---

## Summary Table

| Task | Command |
|------|---------|
| Run all tests | `docker-compose run --rm ml-service pytest tests/ -v` |
| Run single test | `docker-compose run --rm ml-service pytest tests/test_api.py::TestMatchingEndpoint::test_match_driver_success -v` |
| Run with coverage | `docker-compose run --rm ml-service pytest tests/ --cov=app --cov-report=html` |
| Run with output capture | `docker-compose run --rm ml-service pytest tests/ -v -s` |
| Run in parallel | `docker-compose run --rm ml-service pytest tests/ -v -n 4` |
| Debug failing test | `docker-compose run --rm ml-service pytest tests/ -v --pdb` |
| Collect tests only | `docker-compose run --rm ml-service pytest tests/ --collect-only` |
| Run with benchmark | `docker-compose run --rm ml-service pytest tests/ -v --benchmark-only` |

---

For database initialization, see [ML_SERVICE_MIGRATION_GUIDE.md](ML_SERVICE_MIGRATION_GUIDE.md)  
For architecture details, see [ML_SERVICE_ARCHITECTURE.md](ML_SERVICE_ARCHITECTURE.md)  
For quick start, see [ML_SERVICE_QUICKSTART.md](ML_SERVICE_QUICKSTART.md)
