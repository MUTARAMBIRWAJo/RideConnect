# RideConnect ML Microservice - Architecture & Deployment Guide

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        RideConnect Platform                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌──────────────────┐              ┌──────────────────────────────┐ │
│  │   Flutter Apps   │              │    Laravel Backend           │ │
│  │  (Driver/Pass)   │◄────HTTP────►│  (API + Business Logic)      │ │
│  └──────────────────┘              └──────────────────────────────┘ │
│         ▲                                   ▲                        │
│         │                                   │                        │
│         │                                   │ HTTP Requests          │
│         │                                   │                        │
│         └───────────────────┬───────────────┘                        │
│                             │                                        │
│                             ▼                                        │
│              ┌──────────────────────────┐                            │
│              │   Nginx Reverse Proxy    │                            │
│              │   (Port 80/443)          │                            │
│              └────────────┬─────────────┘                            │
│                           │                                          │
│                           ▼                                          │
│        ┌─────────────────────────────────────┐                      │
│        │   FastAPI ML Microservice           │                      │
│        │   (Port 8000, 4 Workers)            │                      │
│        │                                     │                      │
│        │  ┌─────────────────────────────┐   │                      │
│        │  │  Model Loading & Inference  │   │                      │
│        │  │ (rideconnect_v2_best.keras) │   │                      │
│        │  └─────────────────────────────┘   │                      │
│        │                                     │                      │
│        │  ┌─────────────────────────────┐   │                      │
│        │  │  Feature Engineering        │   │                      │
│        │  │  Preprocessing & Ranking    │   │                      │
│        │  └─────────────────────────────┘   │                      │
│        │                                     │                      │
│        │  ┌─────────────────────────────┐   │                      │
│        │  │  API Endpoints              │   │                      │
│        │  │  /health                    │   │                      │
│        │  │  /predict/match-driver      │   │                      │
│        │  │  /predict/demand            │   │                      │
│        │  │  /predict/eta               │   │                      │
│        │  └─────────────────────────────┘   │                      │
│        └─────────┬──────────────┬────────────┘                      │
│                  │              │                                   │
│                  │              │                                   │
│                  ▼              ▼                                   │
│        ┌──────────────┐  ┌─────────────────┐                       │
│        │ Redis Cache  │  │ Supabase        │                       │
│        │ (Port 6379)  │  │ PostgreSQL DB   │                       │
│        └──────────────┘  └─────────────────┘                       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### FastAPI ML Microservice
- **Model Loading**: Keras model loaded once at startup
- **Feature Engineering**: Transform input data to 10-feature vectors
- **Driver Matching**: ML inference for driver-to-ride matching
- **Demand Prediction**: Spatial-temporal demand forecasting
- **ETA Prediction**: Route arrival time estimation

### Laravel Backend Integration
- **HTTP Client**: Calls ML service endpoints
- **Request Preparation**: Formats ride/driver data for ML service
- **Response Handling**: Receives rankings and assigns best driver
- **Fallback Logic**: Uses simple distance-based matching if ML service unavailable
- **Monitoring**: Logs requests, errors, and metrics

### Nginx Reverse Proxy
- **Load Balancing**: Distributes requests across ML service workers
- **SSL/TLS**: HTTPS termination
- **Compression**: GZIP compression for responses
- **Caching**: HTTP caching headers support
- **Rate Limiting**: Optional request throttling

### Supporting Services
- **Redis**: Caching predictions, session management
- **Supabase PostgreSQL**: Driver metrics, historical data

## Deployment Architecture

### Development (Local)

```
docker-compose up --build

┌─────────────────┐
│ Docker Network  │
├─────────────────┤
│ ┌─────────────┐ │
│ │ Redis:6379  │ │
│ └─────────────┘ │
│ ┌─────────────┐ │
│ │ ML:8000     │ │
│ └─────────────┘ │
│ ┌─────────────┐ │
│ │ Nginx:80    │ │
│ └─────────────┘ │
└─────────────────┘
```

### Production (Docker)

```
┌──────────────────────────────────┐
│   Kubernetes / Docker Swarm      │
├──────────────────────────────────┤
│ ┌────────────────────────────┐   │
│ │ Nginx Ingress (80/443)     │   │
│ └──────────┬─────────────────┘   │
│            │                      │
│ ┌──────────┴────────────────┐    │
│ │ ML Service Pod (4 workers)│    │
│ │  - Replica 1 (Port 8000)  │    │
│ │  - Replica 2 (Port 8000)  │    │
│ │  - Replica 3 (Port 8000)  │    │
│ │  - Replica 4 (Port 8000)  │    │
│ └──────────┬─────────────────┘    │
│            │                      │
│ ┌──────────┴────────────────┐    │
│ │ Supporting Services       │    │
│ │  - Redis Cache            │    │
│ │  - Supabase PostgreSQL    │    │
│ └───────────────────────────┘    │
└──────────────────────────────────┘
```

### Azure Deployment

```
┌──────────────────────────────────────┐
│ Azure Container Instances / AKS      │
├──────────────────────────────────────┤
│ ┌────────────────────────────────┐   │
│ │ Application Gateway (SSL)      │   │
│ └──────────────┬─────────────────┘   │
│                │                     │
│ ┌──────────────▼─────────────────┐   │
│ │ Container Instance/Pod Group   │   │
│ │  - ML Service (4 replicas)     │   │
│ │  - Health probes (TCP:8000)    │   │
│ │  - Auto-scaling rules          │   │
│ └──────────────┬─────────────────┘   │
│                │                     │
│ ┌──────────────▼─────────────────┐   │
│ │ Azure PostgreSQL (Supabase)    │   │
│ │ Azure Cache (Redis)            │   │
│ └────────────────────────────────┘   │
└──────────────────────────────────────┘
```

## Data Flow Diagram

### Driver Matching Flow

```
1. Request from Laravel
   ↓
2. HTTP POST /predict/match-driver
   {
     "ride_request": { ... },
     "candidate_drivers": [ ... ]
   }
   ↓
3. FastAPI Endpoint (matching.py)
   ├─ Validate request
   ├─ Check model loaded
   └─ Call MatchingService
     ↓
4. MatchingService
   ├─ FeatureEngineeringService
   │  ├─ PreprocessingService
   │  │  ├─ Normalize distance
   │  │  ├─ Normalize ratings
   │  │  ├─ Normalize acceptance/cancellation
   │  │  ├─ Normalize behavior score
   │  │  └─ Output: 8-feature vector per driver
   │  │
   │  ├─ Seat compatibility
   │  ├─ Vehicle compatibility
   │  └─ Output: 10-feature vector per driver
   │
   ├─ Stack features into batch
   ├─ Model inference
   │  (1 call for all drivers)
   │
   ├─ Extract scores (0-1)
   └─ RankingService
      ├─ Sort by score
      └─ Output: ranked drivers
     ↓
5. Response to Laravel
   {
     "best_driver": {
       "driver_id": 1,
       "score": 0.97
     },
     "ranked_drivers": [ ... ]
   }
   ↓
6. Laravel assigns best driver to trip
```

## Request/Response Examples

### Matching Request

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
}
```

### Matching Response

```json
{
  "best_driver": {
    "driver_id": 1,
    "score": 0.9742
  },
  "ranked_drivers": [
    {
      "driver_id": 1,
      "score": 0.9742
    },
    {
      "driver_id": 2,
      "score": 0.8531
    }
  ]
}
```

## Performance Characteristics

### Latency

| Operation | Time | Notes |
|-----------|------|-------|
| Service startup | 10-20s | Model loading |
| Feature engineering | 5-10ms | Per driver |
| Model inference | 30-50ms | All drivers in batch |
| Response | <100ms | Total request time |

### Throughput

| Metric | Value |
|--------|-------|
| Requests per second | ~400 (4 workers × 100 req/worker) |
| Concurrent requests | ~400 (with connection pooling) |
| Max batch size | 100 drivers |

### Resource Usage

| Resource | Allocation |
|----------|-----------|
| Memory | 1.5GB (500MB model + 1GB working) |
| CPU | 2 cores (4 workers, ~50% utilization at load) |
| Storage | 500MB (model) |
| Network | Typically <1Mbps per request |

## Scaling Strategy

### Horizontal Scaling

1. **Increase Workers**: 4 → 8 workers in Dockerfile
2. **Load Balancing**: Nginx distributes across instances
3. **Container Replicas**: Kubernetes auto-scaling based on CPU/memory

### Vertical Scaling

1. **Model Optimization**: Quantization, pruning for faster inference
2. **Feature Caching**: Redis cache for repeated requests
3. **Batch Optimization**: Process larger batches

## Monitoring & Logging

### Health Checks

```bash
# HTTP health check
curl http://localhost:8000/health

# Docker health check
docker-compose ps  # HEALTHY/UNHEALTHY status

# Kubernetes probe
livenessProbe:
  httpGet:
    path: /health
    port: 8000
  initialDelaySeconds: 10
  periodSeconds: 30
```

### Logging

```bash
# View logs
docker-compose logs -f ml-service

# Filter logs
docker-compose logs ml-service | grep ERROR

# Real-time monitoring
docker stats rideconnect-ml-service
```

### Metrics

Track via structured logs:
- Request count per endpoint
- Response time distribution
- Model inference latency
- Batch sizes
- Error rates

## Deployment Steps

### 1. Build Image

```bash
cd ml-service
docker build -t rideconnect-ml:1.0.0 .
```

### 2. Test Locally

```bash
docker-compose up --build
curl http://localhost:8000/health
```

### 3. Push to Registry

```bash
docker tag rideconnect-ml:1.0.0 myregistry.azurecr.io/rideconnect-ml:1.0.0
docker push myregistry.azurecr.io/rideconnect-ml:1.0.0
```

### 4. Deploy to Production

```bash
# Kubernetes
kubectl apply -f k8s/deployment.yaml

# Docker Swarm
docker service create \
  --name ml-service \
  --publish 8000:8000 \
  myregistry.azurecr.io/rideconnect-ml:1.0.0

# Azure Container Instances
az container create \
  --resource-group rideconnect \
  --name ml-service \
  --image myregistry.azurecr.io/rideconnect-ml:1.0.0 \
  --ports 8000 \
  --environment-variables \
    LOG_LEVEL=WARN \
    DEBUG=false
```

### 5. Verify Deployment

```bash
# Health check
curl http://production-ml-service:8000/health

# View logs
kubectl logs -f deployment/ml-service

# Test API
curl -X POST http://production-ml-service:8000/predict/match-driver \
  -H "Content-Type: application/json" \
  -d '@test_payload.json'
```

## Security Considerations

### Docker Security

- ✅ Non-root user (appuser:1000)
- ✅ Read-only filesystem for app code
- ✅ Health checks enabled
- ✅ Resource limits configured

### Network Security

- ✅ CORS configured for Laravel origin
- ✅ Request validation (Pydantic)
- ✅ HTTPS via Nginx reverse proxy
- ✅ Optional API key authentication

### Data Security

- ✅ Model not trainable (inference-only)
- ✅ No sensitive data in logs
- ✅ Supabase credentials via environment variables
- ✅ Optional encryption for Redis

## Troubleshooting

### Service Won't Start

```
Error: Failed to load model

Check:
1. Model file exists: ls -la models/trained/rideconnect_v2_best.keras
2. Permissions: File must be readable
3. Disk space: 1GB free minimum
4. Memory: 2GB available
```

### Slow Predictions

```
Causes:
1. Redis not running (remove caching)
2. Large batch size (>100 drivers)
3. Insufficient CPU allocation
4. Network latency to Supabase

Solutions:
1. Increase worker count
2. Reduce batch size
3. Scale container resources
4. Use Redis for caching
```

### Memory Leaks

```
Monitor with:
docker stats rideconnect-ml-service

If memory increases over time:
1. Check for circular references
2. Verify connection pooling
3. Monitor Redis cache size
4. Restart service if needed
```

## Maintenance

### Regular Tasks

- **Weekly**: Review logs for errors
- **Monthly**: Performance metrics review
- **Quarterly**: Model retraining evaluation
- **Yearly**: Architecture review

### Updates

```bash
# Update dependencies
pip install --upgrade -r requirements.txt

# Rebuild container
docker build -t rideconnect-ml:1.1.0 .

# Rolling deployment
kubectl set image deployment/ml-service ml-service=myregistry.azurecr.io/rideconnect-ml:1.1.0

# Verify health
kubectl rollout status deployment/ml-service
```

---

For detailed information, see:
- Implementation Report: `ML_SERVICE_IMPLEMENTATION_REPORT.md`
- Quick Start: `ML_SERVICE_QUICKSTART.md`
- README: `ml-service/README.md`
