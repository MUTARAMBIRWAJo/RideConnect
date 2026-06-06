# Passenger Profile Update API - Fixed & Improved

## ✅ Fixes Applied

### 1. **Phone Validation Improved**
- **Old Regex:** `/^\+?[1-9]\d{1,14}$/` (too restrictive)
- **New Regex:** `/^(\+)?[0-9]{10,15}$/` (flexible)
- **Now Accepts:**
  - ✅ `+250780126094` (international with +)
  - ✅ `0780126094` (local format)
  - ✅ `250780126094` (country code without +)
  - ✅ Any 10-15 digit combinations

### 2. **Profile Photo Upload Fixed**
- **File Upload:** `image|mimes:jpg,jpeg,png,gif|max:2048`
- **Storage Location:** `storage/app/public/profiles/`
- **Database:** Stores only the path (e.g., `profiles/filename_timestamp.jpg`)
- **Response:** Returns both path and full URL

### 3. **Validation Rules Improved**
```php
'name' => 'sometimes|string|max:255',
'phone' => 'sometimes|string|max:20|regex:/^(\+)?[0-9]{10,15}$/',
'profile_photo' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
'preferred_payment_method' => 'sometimes|string|in:card,mobile_money,cash,wallet',
'emergency_contact_name' => 'sometimes|nullable|string|max:255',
'emergency_contact_phone' => 'sometimes|nullable|string|max:20|regex:/^(\+)?[0-9]{10,15}$/',
```

### 4. **Mass Assignment Protection**
- Only updates allowed fields
- Prevents accidental modification of system fields
- Prevents modification of read-only statistics

### 5. **Enhanced Response**
- Returns complete updated profile
- Includes both file path and URL
- Clear preference and verification sections
- Statistics auto-calculated (read-only)

---

## 🧪 Testing the API

### Prerequisites
1. **Database Migration Applied**
   ```sql
   ALTER TABLE public.users
   ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(50) DEFAULT 'card',
   ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255),
   ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20);
   ```

2. **Storage Symlink**
   ```bash
   php artisan storage:link
   ```

### Test 1: Update Text Fields Only

**Request:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo Updated",
    "phone": "+250780126094",
    "preferred_payment_method": "mobile_money",
    "emergency_contact_name": "Marie Mugabo",
    "emergency_contact_phone": "+250788654321"
  }'
```

**Expected Response (200 OK):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "Jean Mugabo Updated",
    "email": "jean.mugabo@example.com",
    "phone": "+250780126094",
    "role": "PASSENGER",
    "profile_photo": null,
    "profile_photo_path": null,
    "is_approved": true,
    "is_verified": true,
    "member_since": "2026-03-29T18:14:29+00:00",
    "statistics": {
      "total_trips": 28,
      "total_bookings": 7,
      "completed_bookings": 0,
      "total_spent": 49317.8,
      "average_spent_per_trip": 7045.4,
      "rating": 5,
      "reliability_score": 1,
      "cancellation_rate": 0
    },
    "preferences": {
      "preferred_payment_method": "mobile_money",
      "emergency_contact_name": "Marie Mugabo",
      "emergency_contact_phone": "+250788654321",
      "saved_locations_count": 0
    },
    "verification": {
      "verified": true,
      "approved": true,
      "verified_at": null,
      "approved_at": null
    }
  }
}
```

### Test 2: Upload Profile Photo (Multipart Form Data)

**Request:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "name=Jean Mugabo" \
  -F "profile_photo=@/path/to/photo.jpg"
```

**Expected Response (200 OK):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "Jean Mugabo",
    "email": "jean.mugabo@example.com",
    "phone": "+250780126094",
    "role": "PASSENGER",
    "profile_photo": "http://localhost:8000/storage/profiles/photo_1717674601.jpg",
    "profile_photo_path": "profiles/photo_1717674601.jpg",
    "is_approved": true,
    "is_verified": true,
    "member_since": "2026-03-29T18:14:29+00:00",
    "statistics": {
      "total_trips": 28,
      "total_bookings": 7,
      "completed_bookings": 0,
      "total_spent": 49317.8,
      "average_spent_per_trip": 7045.4,
      "rating": 5,
      "reliability_score": 1,
      "cancellation_rate": 0
    },
    "preferences": {
      "preferred_payment_method": "mobile_money",
      "emergency_contact_name": "Marie Mugabo",
      "emergency_contact_phone": "+250788654321",
      "saved_locations_count": 0
    },
    "verification": {
      "verified": true,
      "approved": true,
      "verified_at": null,
      "approved_at": null
    }
  }
}
```

### Test 3: Update Multiple Fields with Image

**Request (Multipart Form Data):**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "name=Jean Updated" \
  -F "phone=0780126094" \
  -F "preferred_payment_method=card" \
  -F "emergency_contact_name=Marie" \
  -F "emergency_contact_phone=0788654321" \
  -F "profile_photo=@profile.png"
```

### Test 4: Phone Validation - Different Formats

**All Valid Phone Formats:**
```bash
# Format 1: International with +
"phone": "+250780126094"

# Format 2: Local format
"phone": "0780126094"

# Format 3: Country code without +
"phone": "250780126094"

# Format 4: Other international
"phone": "+1234567890"
```

### Test 5: Validation Errors

**Invalid Phone Format:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "12345"
  }'
```

**Expected Response (422 Unprocessable Entity):**
```json
{
  "message": "The phone field format is invalid. (and 0 more errors)",
  "errors": {
    "phone": [
      "The phone field format is invalid."
    ]
  }
}
```

**Invalid Image Format:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "profile_photo=@document.pdf"
```

**Expected Response (422):**
```json
{
  "message": "The profile photo field must be an image. (and 0 more errors)",
  "errors": {
    "profile_photo": [
      "The profile photo field must be an image."
    ]
  }
}
```

**Image Too Large:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "profile_photo=@large_file_5mb.jpg"
```

**Expected Response (422):**
```json
{
  "message": "The profile photo field must not be greater than 2048 kilobytes. (and 0 more errors)",
  "errors": {
    "profile_photo": [
      "The profile photo field must not be greater than 2048 kilobytes."
    ]
  }
}
```

---

## 📋 API Endpoint Details

**Endpoint:** `PUT /api/v1/passenger/profile`

**Authentication:** Required (Bearer token via Sanctum)

**Content-Type:** 
- `application/json` for text-only updates
- `multipart/form-data` for file uploads

**Request Fields (all optional):**

| Field | Type | Validation | Example |
|-------|------|-----------|---------|
| `name` | string | max:255 | "Jean Mugabo" |
| `phone` | string | 10-15 digits, international or local | "+250780126094" |
| `profile_photo` | file | image, jpg/jpeg/png/gif, max 2MB | [binary file] |
| `preferred_payment_method` | string | card, mobile_money, cash, wallet | "mobile_money" |
| `emergency_contact_name` | string | max:255, nullable | "Marie Mugabo" |
| `emergency_contact_phone` | string | 10-15 digits, nullable | "+250788654321" |

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always true on success |
| `message` | string | "Profile updated successfully" |
| `data.id` | integer | User ID |
| `data.profile_photo` | string/null | Full URL to profile photo |
| `data.profile_photo_path` | string/null | Storage path |
| `data.statistics` | object | Read-only, auto-calculated |
| `data.preferences` | object | User preferences |
| `data.verification` | object | Verification status |

---

## 🔐 Security Features

✅ **Authentication Required** - Sanctum bearer token validation  
✅ **Role Validation** - Only passengers can update their profile  
✅ **Input Validation** - Strict validation for all fields  
✅ **Phone Format** - International format enforced  
✅ **File Upload** - Image type and size validation  
✅ **Mass Assignment Protection** - Only allowed fields updated  
✅ **Read-only Fields** - Statistics and verification never modified  
✅ **Proper Error Codes** - 422 for validation, 403 for authorization, 401 for auth  

---

## 🚀 Database Setup Required

Make sure these columns exist in the `users` table:

```sql
-- Run this in Supabase SQL Editor
ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(50) DEFAULT 'card',
ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20);
```

---

## 📁 File Upload Storage

**Storage Path:** `storage/app/public/profiles/`

**File Naming:** `{original_name}_{timestamp}.{extension}`

**Access URL:** `http://your-domain.com/storage/profiles/{filename}`

**Symlink:** Requires `php artisan storage:link` to be run

---

## ✨ Key Improvements

1. ✅ Phone validation now accepts international and local formats
2. ✅ Profile photo properly uploaded to storage with timestamped filenames
3. ✅ Database stores only the relative path
4. ✅ Response includes both path and full URL
5. ✅ Mass assignment protection for security
6. ✅ Proper validation error messages
7. ✅ Statistics remain read-only and auto-calculated
8. ✅ Backward compatible with existing Flutter app
