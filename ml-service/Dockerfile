# Use a lightweight Python image suitable for production containers.
FROM python:3.11-slim

# Set the application working directory inside the container.
WORKDIR /app

# Improve container behavior for production logging and startup.
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# Copy dependency list first to leverage Docker layer caching.
COPY requirements.txt /app/requirements.txt

# Install Python dependencies without pip cache to keep image size smaller.
RUN pip install --no-cache-dir -r /app/requirements.txt

# Copy API entrypoint and service code.
COPY api.py /app/api.py
COPY main.py /app/main.py

# Copy trained model artifacts.
COPY models /app/models

# Expose the service port used by Uvicorn.
EXPOSE 8080

# Start the FastAPI application with Uvicorn.
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8080"]
