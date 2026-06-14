# Firebase Configuration Guide

## Overview

RideConnect uses Firebase for real-time data synchronization and push notifications. This guide covers setup for local development, Render deployment, and the Supabase + Firebase architecture.

---

## Architecture

### Supabase + Firebase Integration

```
┌─────────────┐         ┌─────────────┐         ┌─────────────┐
│  Supabase   │────────▶│   Laravel   │────────▶│  Firebase   │
│  (Source)   │  Events │   Backend   │  Sync   │  (Realtime) │
└─────────────┘         └─────────────┘         └─────────────┘
     │                        │                        │
     │                        │                        │
     ▼                        ▼                        ▼
  PostgreSQL          Queue Jobs              Firestore + FCM
   Database           (Async Sync)           (Realtime + Push)
```

**Data Flow:**
1. **Supabase** is the source of truth for all data
2. **Laravel** listens to Supabase events via webhooks or polling
3. **Firebase** receives synchronized data for real-time updates
4. **FCM** delivers push notifications to mobile apps

---

## Service Account Setup

### 1. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project"
3. Enter project name (e.g., `rideconnect-production`)
4. Enable Google Analytics (optional)
5. Click "Create project"

### 2. Generate Service Account Key

1. Go to **Project Settings** → **Service Accounts**
2. Click **Generate New Private Key**
3. Save the JSON file as `credentials.json`
4. **IMPORTANT:** Never commit this file to version control

### 3. Configure Firestore Database

1. Go to **Firestore Database** in Firebase Console
2. Click **Create Database**
3. Select a location (choose closest to your users)
4. Start in **Test Mode** (we'll secure rules later)
5. Select **native mode** (not Datastore mode)

### 4. Enable Cloud Messaging (FCM)

1. Go to **Project Settings** → **Cloud Messaging**
2. Copy the **Server Key** and **Sender ID**
3. Add these to your `.env` file

---

## Local Development Setup

### Step 1: Store Credentials

```bash
# Create Firebase credentials directory
mkdir -p storage/firebase

# Copy your service account JSON
cp ~/Downloads/credentials.json storage/firebase/

# Set proper permissions
chmod 600 storage/firebase/credentials.json
```

### Step 2: Configure Environment

Add to `.env`:

```bash
# Firebase Configuration
FIREBASE_ENABLED=true
FIREBASE_BOOTSTRAP_ENABLED=true
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_CREDENTIALS_PATH=storage/firebase/credentials.json
FIREBASE_FIRESTORE_DATABASE=(default)

# FCM Configuration
FCM_SERVER_KEY=your-fcm-server-key
```

### Step 3: Bootstrap Firestore Schema

```bash
# Run bootstrap command
php artisan firebase:bootstrap

# Verify schema health
php artisan firebase:schema-health
```

### Step 4: Validate Configuration

```bash
# Run full validation
php artisan firebase:validate

# Expected output: 95%+ readiness score
```

---

## Render Deployment Setup

### Step 1: Store Credentials in Render

1. Go to your Render dashboard
2. Navigate to your service
3. Go to **Environment Variables**
4. Add the following variables:

```bash
FIREBASE_ENABLED=true
FIREBASE_BOOTSTRAP_ENABLED=true
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_CREDENTIALS_PATH=/var/data/firebase/credentials.json
FIREBASE_FIRESTORE_DATABASE=(default)
FCM_SERVER_KEY=your-fcm-server-key
```

### Step 2: Upload Credentials File

**Option A: Using Render Dashboard**
1. Go to your service's **Files** tab
2. Upload `credentials.json` to `/var/data/firebase/`

**Option B: Using Render CLI**
```bash
render-cli files upload credentials.json /var/data/firebase/
```

**Option C: Using Base64 (Recommended)**
```bash
# Encode credentials
base64 -i credentials.json > credentials.base64

# Add to Render environment
FIREBASE_CREDENTIALS_BASE64=$(cat credentials.base64)

# Add to deployment script
echo $FIREBASE_CREDENTIALS_BASE64 | base64 -d > /var/data/firebase/credentials.json
```

### Step 3: Deploy Hook

Add to your `render.yaml` or deployment script:

```yaml
hooks:
  postDeploy: |
    php artisan firebase:bootstrap --force
    php artisan firebase:validate
```

---

## Firestore Security Rules

### Development Rules (Test Mode)

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if true;
    }
  }
}
```

### Production Rules

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Users can read their own data
    match /users/{userId} {
      allow read: if request.auth != null && request.auth.uid == userId;
      allow write: if false; // Written by Laravel only
    }
    
    // Drivers can be read by authenticated users
    match /drivers/{driverId} {
      allow read: if request.auth != null;
      allow write: if false; // Written by Laravel only
    }
    
    // Active trips - read by authenticated users
    match /active_trips/{tripId} {
      allow read: if request.auth != null;
      allow write: if false; // Written by Laravel only
    }
    
    // System documents - read-only
    match /_system/{document=**} {
      allow read: if request.auth != null;
      allow write: if false;
    }
  }
}
```

---

## Required Collections

The system automatically creates these collections via `FirebaseBootstrapService`:

| Collection | Purpose | TTL |
|------------|---------|-----|
| `users` | User profiles | None |
| `drivers` | Driver profiles | None |
| `active_trips` | Active trip data | 30 days |
| `trip_events` | Trip event log | None |
| `driver_locations` | Real-time driver locations | None |
| `trip_tracking` | Trip tracking data | None |
| `notifications` | Push notification history | 30 days |
| `presence` | User online/offline status | None |
| `device_tokens` | FCM device tokens | 90 days |
| `payments` | Payment records | None |
| `ratings` | Driver/passenger ratings | None |
| `chat_rooms` | Chat room metadata | None |
| `chat_messages` | Chat messages | None |

**Note:** Collections are created automatically. No manual setup required.

---

## Graceful Degradation

The system is designed to work without Firebase:

### When Firebase is Disabled (`FIREBASE_ENABLED=false`)

- Laravel continues to function normally
- All data stored in Supabase
- Real-time features disabled
- Push notifications disabled
- Commands return meaningful status without crashing

### Commands with Firebase Disabled

```bash
# All commands work gracefully
php artisan firebase:bootstrap
# Output: Firebase not enabled

php artisan firebase:validate
# Output: Firebase not configured (0% score, no crash)

php artisan firebase:reconcile --dry-run
# Output: Firebase not enabled
```

---

## Troubleshooting

### Issue: "Firebase credentials file not found"

**Solution:**
```bash
# Check file exists
ls -la storage/firebase/credentials.json

# Check .env path
echo $FIREBASE_CREDENTIALS_PATH

# Verify permissions
chmod 600 storage/firebase/credentials.json
```

### Issue: "Permission denied on credentials.json"

**Solution:**
```bash
# Fix permissions
chmod 600 storage/firebase/credentials.json
chown www-data:www-data storage/firebase/credentials.json
```

### Issue: "Firestore connection failed"

**Solution:**
```bash
# Test connectivity
php artisan firebase:schema-health

# Check if project ID is correct
echo $FIREBASE_PROJECT_ID

# Verify service account has Firestore permissions
```

### Issue: "Collections not created"

**Solution:**
```bash
# Force bootstrap
php artisan firebase:bootstrap --force

# Check bootstrap enabled
echo $FIREBASE_BOOTSTRAP_ENABLED

# Enable bootstrap if needed
export FIREBASE_BOOTSTRAP_ENABLED=true
```

---

## Monitoring

### Health Checks

```bash
# Full validation
php artisan firebase:validate

# Schema health
php artisan firebase:schema-health

# Production readiness
php artisan rideconnect:production-check
```

### Logs

```bash
# Firebase-specific logs
tail -f storage/logs/laravel.log | grep Firebase

# Sync failures
tail -f storage/logs/laravel.log | grep "sync failed"
```

---

## Best Practices

1. **Never commit credentials** to version control
2. **Use environment-specific credentials** (dev, staging, production)
3. **Enable bootstrap only in production** or when first setting up
4. **Monitor sync failures** via logs and health checks
5. **Use graceful degradation** - system should work without Firebase
6. **Secure Firestore rules** before going to production
7. **Rotate service account keys** every 90 days
8. **Test in staging** before production deployment

---

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run validation: `php artisan firebase:validate`
3. Check Firebase Console for project status
4. Review this guide for common issues
