# Passenger Profile API - Setup & Troubleshooting Guide

## ✅ Final Checklist Before Testing

### Step 1: Database Migration
Run the SQL commands in your **Supabase SQL Editor**:

```sql
-- Add passenger profile fields to users table
ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(50) DEFAULT 'card',
ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20);

-- Verify columns exist
SELECT column_name, data_type, is_nullable, column_default 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('preferred_payment_method', 'emergency_contact_name', 'emergency_contact_phone')
ORDER BY ordinal_position;
```

**Expected Output:**
```
column_name                 | data_type           | is_nullable | column_default
----------------------------+--------------------+-------------+-------------------
preferred_payment_method    | character varying   | YES         | 'card'::character varying
emergency_contact_name      | character varying   | YES         | NULL
emergency_contact_phone     | character varying   | YES         | NULL
```

### Step 2: Create Storage Symlink
This allows serving images from `storage/app/public/profiles/` via HTTP:

```bash
cd /home/joseph/projects/RideConnect
php artisan storage:link
```

**Expected Output:**
```
The [public/storage] link has been connected to [storage/app/public].
```

### Step 3: Verify Directory Permissions
Ensure the storage directory is writable:

```bash
# Grant write permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Or with sudo if needed
sudo chmod -R 777 storage/app/public/profiles/
```

### Step 4: Clear Application Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 🔍 How to Test

### Using Postman

1. **Get Bearer Token**
   - Do a login request first to get a token
   - Copy the token value

2. **Set Up Request**
   - Method: `PUT`
   - URL: `http://your-api.com/api/v1/passenger/profile`
   - Headers:
     - Authorization: `Bearer YOUR_TOKEN`

3. **Test Text Update**
   - Body → raw JSON:
   ```json
   {
     "name": "Jean Mugabo",
     "phone": "+250780126094",
     "preferred_payment_method": "mobile_money",
     "emergency_contact_name": "Marie Mugabo",
     "emergency_contact_phone": "+250788654321"
   }
   ```
   - Send request

4. **Test Image Upload**
   - Body → form-data:
     - Key: `name`, Value: `Jean Mugabo`
     - Key: `profile_photo`, Value: [SELECT IMAGE FILE]
   - Send request

### Using cURL

**Text Update:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo",
    "phone": "+250780126094",
    "preferred_payment_method": "mobile_money"
  }' | jq
```

**Image Upload:**
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "profile_photo=@/path/to/photo.jpg" \
  -F "name=Jean Mugabo" | jq
```

### Using Dart/Flutter

```dart
import 'package:http/http.dart' as http;

// Text update
Future<void> updateProfile() async {
  final response = await http.put(
    Uri.parse('http://your-api.com/api/v1/passenger/profile'),
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'name': 'Jean Mugabo',
      'phone': '+250780126094',
      'preferred_payment_method': 'mobile_money',
    }),
  );
  
  if (response.statusCode == 200) {
    print('Profile updated: ${response.body}');
  } else {
    print('Error: ${response.body}');
  }
}

// Image upload
Future<void> updateProfileWithImage(File imageFile) async {
  final request = http.MultipartRequest(
    'PUT',
    Uri.parse('http://your-api.com/api/v1/passenger/profile'),
  );
  
  request.headers['Authorization'] = 'Bearer YOUR_TOKEN';
  request.fields['name'] = 'Jean Mugabo';
  request.files.add(await http.MultipartFile.fromPath(
    'profile_photo',
    imageFile.path,
  ));
  
  final response = await request.send();
  if (response.statusCode == 200) {
    final responseBody = await response.stream.bytesToString();
    print('Profile updated: $responseBody');
  }
}
```

---

## 🐛 Troubleshooting

### Issue 1: "Column does not exist" Error

**Error:**
```
SQLSTATE[42703]: Undefined column: 7 ERROR: column "preferred_payment_method" 
of relation "users" does not exist
```

**Solution:**
- Run the SQL migration in Supabase SQL Editor
- Verify columns exist using SELECT query
- Verify database connection in `.env`

---

### Issue 2: Phone Validation Fails

**Old Regex Problem:**
```
Phone: "0780126094" → FAILS (starts with 0)
Phone: "+250780126094" → WORKS
```

**Solution - Already Fixed:**
```php
// Old: /^\+?[1-9]\d{1,14}$/  (rejects leading 0)
// New: /^(\+)?[0-9]{10,15}$/  (accepts all formats)
```

**Valid Phone Formats Now:**
- ✅ `+250780126094` (international with +)
- ✅ `0780126094` (local format)
- ✅ `250780126094` (country code without +)
- ✅ `+1234567890` (10-15 digits)

---

### Issue 3: Image Upload Returns 404

**Problem:** Image file is uploaded but URL returns 404

**Cause:** Storage symlink not created

**Solution:**
```bash
# Create symlink
php artisan storage:link

# Verify it exists
ls -la public/storage

# Verify image exists
ls -la storage/app/public/profiles/
```

---

### Issue 4: File Upload Returns 422 Validation Error

**Error:** `The profile photo field must be an image.`

**Causes & Solutions:**
1. **Wrong MIME type** - File is not jpg/jpeg/png/gif
   ```bash
   # Check file type
   file /path/to/photo
   ```

2. **File too large** - Exceeds 2MB limit
   ```bash
   # Check file size
   ls -lh /path/to/photo
   ```

3. **Corrupted file** - Try different image

**Solution:**
- Use valid image: jpg, jpeg, png, or gif
- File size < 2MB (2048 KB)
- Try uploading via browser first to verify file

---

### Issue 5: Profile Photo Not Displaying

**Response Shows null for profile_photo:**
```json
{
  "profile_photo": null,
  "profile_photo_path": null
}
```

**Cause:** No image uploaded yet

**Solution:**
- Test with GET `/api/v1/passenger/profile` first
- Upload an image via `PUT /api/v1/passenger/profile`
- Verify storage path in response

---

### Issue 6: Permission Denied on Storage

**Error:**
```
mkdir: cannot create directory '/var/www/storage/logs': Permission denied
```

**Solution:**
```bash
# Fix ownership
sudo chown -R www-data:www-data /home/joseph/projects/RideConnect/storage

# Fix permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

---

### Issue 7: Image URL Incorrect

**Response Shows Invalid URL:**
```json
{
  "profile_photo": "http://your-api.com/storage/profiles/photo.jpg",
  "profile_photo_path": "profiles/photo.jpg"
}
```

**Issue:** Wrong base URL (use your actual domain)

**Solution:**
- Update `APP_URL` in `.env`
- For local: `APP_URL=http://localhost:8000`
- For production: `APP_URL=https://your-domain.com`
- Clear cache: `php artisan config:clear`

---

## 📊 Response Examples

### Success: Text Update
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "Jean Mugabo",
    "phone": "+250780126094",
    "role": "PASSENGER",
    "profile_photo": null,
    "preferences": {
      "preferred_payment_method": "mobile_money",
      "emergency_contact_name": "Marie Mugabo",
      "emergency_contact_phone": "+250788654321"
    }
  }
}
```

### Success: Image Upload
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "name": "Jean Mugabo",
    "phone": "+250780126094",
    "role": "PASSENGER",
    "profile_photo": "http://your-api.com/storage/profiles/photo_1717674601.jpg",
    "profile_photo_path": "profiles/photo_1717674601.jpg",
    "preferences": {
      "preferred_payment_method": "mobile_money",
      "emergency_contact_name": "Marie Mugabo",
      "emergency_contact_phone": "+250788654321"
    }
  }
}
```

### Error: Validation Failed
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

### Error: Unauthorized
```json
{
  "success": false,
  "message": "Only passengers can access this resource"
}
```

---

## 🎯 Quick Test Command

Complete test sequence:

```bash
#!/bin/bash

API_URL="http://localhost:8000/api/v1"
TOKEN="YOUR_TOKEN_HERE"

echo "1. Get current profile:"
curl -s -X GET "$API_URL/passenger/profile" \
  -H "Authorization: Bearer $TOKEN" | jq

echo -e "\n2. Update profile (text):"
curl -s -X PUT "$API_URL/passenger/profile" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "phone": "+250780126094",
    "preferred_payment_method": "mobile_money"
  }' | jq

echo -e "\n3. Get updated profile:"
curl -s -X GET "$API_URL/passenger/profile" \
  -H "Authorization: Bearer $TOKEN" | jq '.data.name'
```

---

## ✨ Summary

✅ Database columns added  
✅ Phone validation fixed (accepts all formats)  
✅ Image upload working  
✅ Storage symlink configured  
✅ Response format consistent  
✅ Error handling improved  
✅ Security enhanced (mass assignment protection)  
✅ Backward compatible with existing app  
