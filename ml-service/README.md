# RideConnect ML Microservice

Production-grade FastAPI microservice for intelligent driver matching in the RideConnect platform using TensorFlow/Keras ML model.

## Overview

This microservice provides:

- **Driver Matching**: ML-powered driver-to-ride matching using trained Keras model
- **Demand Prediction**: Predict ride demand at specific locations
- **ETA Prediction**: Estimate arrival time for routes
- **Production Architecture**: Async FastAPI, structured logging, comprehensive error handling
- **Docker Ready**: Multi-stage build, health checks, production optimizations
- **Scalable**: Load-balanced with Nginx, Redis caching, async endpoints

## Key Features

✅ **Real Keras Model**: Loads and infers from `rideconnect_v2_best.keras` (no mocking, no retraining)
✅ **Production Grade**: Type hints, validation, logging, exception handling
✅ **Fast Inference**: Batch predictions, optimized model loading
✅ **Enterprise API**: Swagger docs, structured responses, error codes
✅ **Database Integration**: Supabase connectivity, optional metrics enrichment
✅ **Full Docker Suite**: Docker + docker-compose + Nginx reverse proxy
✅ **Monitoring**: Health checks, timing metrics, structured JSON logging

## Project Structure

```
ml-service/
├── app/
│   ├── api/              # API endpoints
│   │   ├── health.py     # Health check
│   │   ├── matching.py   # Driver matching
│   │   └── prediction.py # Demand & ETA predictions
│   ├── core/             # Core application
│   │   ├── config.py     # Configuration management
│   │   ├── logging.py    # Structured logging
│   │   └── startup.py    # Model loading & lifespan
│   ├── services/         # Business logic
│   │   ├── model_loader.py        # Keras model management
│   │   ├── matching_service.py    # Matching algorithm
│   │   ├── preprocessing_service.py # Feature engineering
│   │   ├── feature_engineering.py  # Feature calculations
│   │   └── ranking_service.py     # Driver ranking
│   ├── schemas/          # Pydantic models
│   │   ├── match_request.py
│   │   ├── match_response.py
│   │   └── driver_schema.py
│   ├── database/         # Database integrations
│   │   ├── connection.py
│   │   └── supabase_client.py
│   ├── utils/            # Utilities
│   │   ├── distance.py      # Haversine, bearing calculations
│   │   ├── similarity.py    # Similarity metrics
│   │   └── validators.py    # Request validation
│   └── main.py           # FastAPI application
├── models/
│   └── trained/
│       └── rideconnect_v2_best.keras  # ML model
├── tests/                 # Unit tests
├── requirements.txt       # Python dependencies
├── Dockerfile            # Multi-stage production build
├── docker-compose.yml    # Full stack orchestration
├── nginx.conf           # Reverse proxy configuration
├── .env.example         # Environment template
└── README.md            # This file
```

## Requirements

### Software

- Python 3.11+
- Docker & Docker Compose
- TensorFlow 2.14+
- FastAPI 0.104+

### Keras Model

The service requires:
```
models/trained/rideconnect_v2_best.keras
```

This file must exist before startup. The model is loaded once at startup and reused for all predictions.

## Installation

### 1. Clone & Setup

```bash
cd RideConnectBackend/ml-service
cp .env.example .env
```

### 2. Configure Environment

Edit `.env` with your values:

```bash
# Required
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_KEY=your-anon-key
SUPABASE_DB_URL=postgresql://user:pass@host:5432/db

# Optional
DEBUG=false
LOG_LEVEL=INFO
ENABLE_CACHING=true
CACHE_TTL=3600
```

### 3. Install Dependencies (Local Development)

```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install -r requirements.txt
```

### 4. Verify Model Exists

```bash
ls -la models/trained/rideconnect_v2_best.keras
```

## Running

### Local Development

```bash
# Activate virtual environment
source venv/bin/activate

# Set environment variables
export MODEL_PATH=models/trained/rideconnect_v2_best.keras
export LOG_LEVEL=DEBUG

# Run with hot reload
uvicorn app.main:app --reload --port 8000
```

### Docker (Recommended)

```bash
# Build and run with docker-compose
docker-compose up --build

# Run in background
docker-compose up -d

# View logs
docker-compose logs -f ml-service
```

### Verify Service

```bash
# Health check
curl http://localhost:8000/health

# View API docs
http://localhost:8000/docs

# View ReDoc
http://localhost:8000/redoc
```

## API Endpoints

### Health Check

```http
GET /health
```

Response:
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "model_loaded": true
}
```

### Driver Matching

```http
POST /predict/match-driver
```

Request:
```json
{
  "ride_request": {
    "pickup_latitude": -1.9441,
    "pickup_longitude": 30.0619,
    "destination_latitude": -1.9536,
    "destination_longitude": 30.1044,
    "requested_vehicle_type": "car",
    "required_seats": 3
  },
  "candidate_drivers": [
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
    }
  ]
}
```

Response:
```json
{
  "best_driver": {
    "driver_id": 1,
    "score": 0.97
  },
  "ranked_drivers": [
    {
      "driver_id": 1,
      "score": 0.97
    },
    {
      "driver_id": 2,
      "score": 0.85
    }
  ]
}
```

### Demand Prediction

```http
POST /predict/demand
```

Request:
```json
{
  "latitude": -1.9441,
  "longitude": 30.0619,
  "hour": 14,
  "day_of_week": 2
}
```

Response:
```json
{
  "demand_level": 0.75,
  "expected_wait_time_minutes": 8,
  "confidence": 0.92
}
```

### ETA Prediction

```http
POST /predict/eta
```

Request:
```json
{
  "pickup_latitude": -1.9441,
  "pickup_longitude": 30.0619,
  "destination_latitude": -1.9536,
  "destination_longitude": 30.1044,
  "traffic_level": 0.3,
  "distance_km": 2.5
}
```

Response:
```json
{
  "estimated_time_minutes": 12.5,
  "distance_km": 2.5,
  "confidence": 0.88
}
```

## Laravel Integration

The Laravel backend includes a service class to integrate with the ML microservice.

### Configuration

Add to `config/services.php`:

```php
'ml_service' => [
    'url' => env('ML_SERVICE_URL', 'http://localhost:8000'),
    'timeout' => env('ML_SERVICE_TIMEOUT', 30),
    'enabled' => env('ML_SERVICE_ENABLED', true),
],
```

### Usage

```php
use App\Services\MLPredictionService;

$mlService = new MLPredictionService();

// Check health
if ($mlService->isHealthy()) {
    // Service is available
}

// Match driver
$rideRequest = [
    'pickup_latitude' => -1.9441,
    'pickup_longitude' => 30.0619,
    'destination_latitude' => -1.9536,
    'destination_longitude' => 30.1044,
    'requested_vehicle_type' => 'car',
    'required_seats' => 3,
];

$candidates = [
    [
        'driver_id' => 1,
        'distance_km' => 1.2,
        'driver_rating' => 4.8,
        // ... other metrics
    ]
];

$result = $mlService->matchDriver($rideRequest, $candidates);
// Returns: ['best_driver' => [...], 'ranked_drivers' => [...]]

// Predict demand
$demand = $mlService->predictDemand(-1.9441, 30.0619, 14, 2);

// Predict ETA
$eta = $mlService->predictETA($pickup_lat, $pickup_lon, $dest_lat, $dest_lon, $traffic, $distance);
```

## Feature Engineering

The service implements sophisticated feature engineering:

- **Distance Normalization**: Haversine distance with max 50km scaling
- **Driver Metrics**: Rating, acceptance/cancellation rates, behavior scores
- **Vehicle Compatibility**: Seat availability and type matching
- **Traffic Adjustments**: Penalty application for congested routes
- **Route Similarity**: Direction and waypoint alignment scoring
- **ETA Calculations**: Speed estimation with traffic multipliers

## Model Inference

The Keras model is loaded once at startup:

```python
# In app/core/startup.py
async def load_model():
    model_loader = ModelLoader()
    await model_loader.initialize()
    # Model is now loaded and cached globally
```

Predictions are made using batch inference:

```python
# In app/services/matching_service.py
feature_batch = np.vstack(driver_features_list)
predictions = model_loader.predict(feature_batch)  # Batch inference
```

## Performance Optimization

- **Batch Predictions**: Multiple drivers scored in single inference call
- **Feature Caching**: Redis-ready for frequently accessed data
- **Async Endpoints**: Non-blocking request handling
- **Model Lazy Loading**: Loaded only once, reused across requests
- **Connection Pooling**: Supabase connection management
- **Structured Logging**: JSON logs for efficient parsing

## Monitoring & Logging

All logs are in JSON format for easy parsing:

```json
{
  "timestamp": "2024-05-11 14:30:45",
  "level": "INFO",
  "logger": "app.services.matching_service",
  "message": "Matching request for 5 drivers"
}
```

Access logs at container runtime:

```bash
docker-compose logs -f ml-service
```

## Testing

```bash
# Run tests
pytest tests/ -v

# With coverage
pytest tests/ --cov=app
```

## Deployment

### Azure Container Registry

```bash
# Build and push
docker build -t rideconnect-ml:latest .
docker tag rideconnect-ml:latest myregistry.azurecr.io/rideconnect-ml:latest
docker push myregistry.azurecr.io/rideconnect-ml:latest
```

### Kubernetes

```bash
# Deploy with Helm or kubectl
kubectl apply -f k8s/deployment.yaml
```

### Environment Variables

```bash
# Production
DEBUG=false
LOG_LEVEL=WARN
ML_SERVICE_WORKERS=1
ENABLE_CACHING=true
CACHE_TTL=7200
```

## Troubleshooting

### Model Not Loading

```
Error: Model file not found at models/trained/rideconnect_v2_best.keras
```

**Solution**: Ensure the model file exists and path is correct in `.env`

### Service Timeout

```
Error: ML service request timeout
```

**Solution**: Increase `ML_SERVICE_TIMEOUT` in `.env` or check Redis connectivity

### Memory Issues

```
Error: Cannot allocate memory
```

**Solution**: Use `ML_SERVICE_WORKERS=1` or reduce `--workers` in the Dockerfile CMD, or increase container memory limits

### Invalid Predictions

```
Error: Prediction shape mismatch
```

**Solution**: Verify model input shape matches feature engineering output shape (10 features expected)

## Contributing

1. Create feature branch
2. Write tests
3. Run linting: `flake8 app/`
4. Format code: `black app/`
5. Type check: `mypy app/`
6. Submit PR

## License

Proprietary - RideConnect Platform

## Support

For issues or questions:
1. Check logs: `docker-compose logs ml-service`
2. Test endpoint: `curl http://localhost:8000/health`
3. Review API docs: `http://localhost:8000/docs`
4. Contact: engineering@rideconnect.com

## Version History

- **1.0.0** (2024-05-11) - Initial release with driver matching, demand & ETA prediction
