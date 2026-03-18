# Rwanda Data Anchors For AI Seeding

The synthetic AI training rows in `database/seeders/AIRwandaTrainingSeeder.php` use public Rwanda location anchors from:

- https://en.wikipedia.org/wiki/Kigali
- https://en.wikipedia.org/wiki/Kigali_International_Airport
- https://www.openstreetmap.org/search?query=Kigali%20Convention%20Centre

How this is used:

- Kigali mobility hotspots are used as pickup/dropoff anchor points.
- Coordinates are jittered slightly to create realistic request dispersion.
- Demand and traffic patterns are shaped by Kigali peak-hour windows and rainy-season months.
- Generated rows populate AI pipeline tables (`ride_requests`, `demand_logs`, `traffic_events`, `ride_events`, `ride_cancellations`, `ai_prediction_logs`) for local model training and dashboard validation.
