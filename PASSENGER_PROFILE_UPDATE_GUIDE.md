# PUT /v1/passenger/profile - Profile Update Endpoint

## Endpoint Overview
- **URL:** `PUT /api/v1/passenger/profile`
- **Authentication:** Required (Bearer Token)
- **Request Format:** JSON or Form Data (for file upload)
- **Response Format:** JSON
- **HTTP Status:** 200 OK

## Purpose
Updates passenger profile information including personal details, payment preferences, and emergency contact information. Returns the complete updated profile.

## Request

### Headers
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

Or for file upload:
```
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

### Request Body (JSON)

```json
{
  "name": "Jean Mugabo Updated",
  "phone": "+250780126094",
  "profile_photo": "https://cdn.example.com/profiles/jean_mugabo_new.jpg",
  "preferred_payment_method": "mobile_money",
  "emergency_contact_name": "Marie Mugabo",
  "emergency_contact_phone": "+250788654321"
}
```

### Updateable Fields

| Field | Type | Validation | Required | Description |
|-------|------|-----------|----------|-------------|
| `name` | string | max:255 | No | Full name (1-255 characters) |
| `phone` | string | max:20, regex | No | Phone with country code (+256...) |
| `profile_photo` | string/file | max:1000 chars or image | No | URL or image file |
| `preferred_payment_method` | string | in:card,mobile_money,cash,wallet | No | Default payment method |
| `emergency_contact_name` | string | max:255, nullable | No | Emergency contact person name |
| `emergency_contact_phone` | string | max:20, regex, nullable | No | Emergency contact phone |

## Response

### Success Response (200 OK)
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
    "profile_photo": "profiles/jean_mugabo_new.jpg",
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

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "phone": [
      "The phone field must be a valid phone number."
    ],
    "preferred_payment_method": [
      "The preferred payment method must be one of: card, mobile_money, cash, wallet."
    ]
  }
}
```

### Authorization Error (401)
```json
{
  "message": "Unauthenticated."
}
```

### Permission Error (403)
```json
{
  "success": false,
  "message": "Only passengers can access this resource"
}
```

## Update Examples

### Update Only Name
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo Updated"
  }'
```

### Update Name and Phone
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo",
    "phone": "+250780126094"
  }'
```

### Update Emergency Contact
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "emergency_contact_name": "Marie Mugabo",
    "emergency_contact_phone": "+250788654321"
  }'
```

### Update Payment Preference
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "preferred_payment_method": "mobile_money"
  }'
```

### Update Profile Photo (File Upload)
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "profile_photo=@/path/to/photo.jpg"
```

### Update Multiple Fields
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jean Mugabo",
    "phone": "+250780126094",
    "preferred_payment_method": "card",
    "emergency_contact_name": "Marie Mugabo",
    "emergency_contact_phone": "+250788654321"
  }'
```

## Flutter Implementation

### Basic Profile Update
```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class PassengerService {
  final String baseUrl = 'http://localhost:8000';
  
  Future<Map<String, dynamic>> updateProfile({
    required String token,
    String? name,
    String? phone,
    String? profilePhoto,
    String? preferredPaymentMethod,
    String? emergencyContactName,
    String? emergencyContactPhone,
  }) async {
    final Map<String, dynamic> body = {};
    
    if (name != null) body['name'] = name;
    if (phone != null) body['phone'] = phone;
    if (profilePhoto != null) body['profile_photo'] = profilePhoto;
    if (preferredPaymentMethod != null) {
      body['preferred_payment_method'] = preferredPaymentMethod;
    }
    if (emergencyContactName != null) {
      body['emergency_contact_name'] = emergencyContactName;
    }
    if (emergencyContactPhone != null) {
      body['emergency_contact_phone'] = emergencyContactPhone;
    }

    final response = await http.put(
      Uri.parse('$baseUrl/api/v1/passenger/profile'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else if (response.statusCode == 422) {
      throw ValidationException(jsonDecode(response.body));
    } else if (response.statusCode == 401) {
      throw UnauthorizedException('Invalid or expired token');
    } else {
      throw Exception('Failed to update profile');
    }
  }
}
```

### Update with File Upload
```dart
import 'package:http/http.dart' as http;
import 'dart:io';

Future<Map<String, dynamic>> updateProfileWithPhoto({
  required String token,
  required File photoFile,
  String? name,
}) async {
  final request = http.MultipartRequest(
    'PUT',
    Uri.parse('$baseUrl/api/v1/passenger/profile'),
  );

  request.headers.addAll({
    'Authorization': 'Bearer $token',
  });

  request.files.add(
    await http.MultipartFile.fromPath(
      'profile_photo',
      photoFile.path,
    ),
  );

  if (name != null) {
    request.fields['name'] = name;
  }

  final streamedResponse = await request.send();
  final response = await http.Response.fromStream(streamedResponse);

  if (response.statusCode == 200) {
    return jsonDecode(response.body);
  } else {
    throw Exception('Failed to update profile');
  }
}
```

### Complete Widget Example
```dart
class PassengerProfileForm extends StatefulWidget {
  final String token;
  final Map<String, dynamic> initialData;

  const PassengerProfileForm({
    required this.token,
    required this.initialData,
  });

  @override
  State<PassengerProfileForm> createState() => _PassengerProfileFormState();
}

class _PassengerProfileFormState extends State<PassengerProfileForm> {
  late TextEditingController nameController;
  late TextEditingController phoneController;
  late TextEditingController emergencyNameController;
  late TextEditingController emergencyPhoneController;
  
  String? selectedPaymentMethod;
  bool isLoading = false;
  File? selectedPhoto;

  @override
  void initState() {
    super.initState();
    final data = widget.initialData['data'];
    nameController = TextEditingController(text: data['name']);
    phoneController = TextEditingController(text: data['phone']);
    emergencyNameController = TextEditingController(
      text: data['preferences']['emergency_contact_name'] ?? '',
    );
    emergencyPhoneController = TextEditingController(
      text: data['preferences']['emergency_contact_phone'] ?? '',
    );
    selectedPaymentMethod = data['preferences']['preferred_payment_method'];
  }

  Future<void> pickPhoto() async {
    // Implement image picker logic
  }

  Future<void> updateProfile() async {
    setState(() => isLoading = true);
    
    try {
      final service = PassengerService();
      final result = await service.updateProfile(
        token: widget.token,
        name: nameController.text,
        phone: phoneController.text,
        preferredPaymentMethod: selectedPaymentMethod,
        emergencyContactName: emergencyNameController.text,
        emergencyContactPhone: emergencyPhoneController.text,
      );
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['message'])),
      );
      
      Navigator.pop(context, result);
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            // Profile Photo
            GestureDetector(
              onTap: pickPhoto,
              child: CircleAvatar(
                radius: 60,
                backgroundImage: selectedPhoto != null
                    ? FileImage(selectedPhoto!)
                    : null,
                child: selectedPhoto == null
                    ? const Icon(Icons.camera_alt, size: 40)
                    : null,
              ),
            ),
            const SizedBox(height: 16),
            
            // Name Field
            TextField(
              controller: nameController,
              decoration: const InputDecoration(
                labelText: 'Full Name',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            
            // Phone Field
            TextField(
              controller: phoneController,
              decoration: const InputDecoration(
                labelText: 'Phone Number',
                hintText: '+250788123456',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            
            // Payment Method Dropdown
            DropdownButtonFormField<String>(
              value: selectedPaymentMethod,
              decoration: const InputDecoration(
                labelText: 'Preferred Payment Method',
                border: OutlineInputBorder(),
              ),
              items: const [
                DropdownMenuItem(value: 'card', child: Text('Card')),
                DropdownMenuItem(value: 'mobile_money', child: Text('Mobile Money')),
                DropdownMenuItem(value: 'cash', child: Text('Cash')),
                DropdownMenuItem(value: 'wallet', child: Text('Wallet')),
              ],
              onChanged: (value) {
                setState(() => selectedPaymentMethod = value);
              },
            ),
            const SizedBox(height: 12),
            
            // Emergency Contact Name
            TextField(
              controller: emergencyNameController,
              decoration: const InputDecoration(
                labelText: 'Emergency Contact Name',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            
            // Emergency Contact Phone
            TextField(
              controller: emergencyPhoneController,
              decoration: const InputDecoration(
                labelText: 'Emergency Contact Phone',
                hintText: '+250788654321',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 24),
            
            // Update Button
            ElevatedButton(
              onPressed: isLoading ? null : updateProfile,
              child: isLoading
                  ? const CircularProgressIndicator()
                  : const Text('Update Profile'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    nameController.dispose();
    phoneController.dispose();
    emergencyNameController.dispose();
    emergencyPhoneController.dispose();
    super.dispose();
  }
}
```

## Postman Setup

### Set Environment Variables
```
{{base_url}} = http://localhost:8000
{{passenger_token}} = <your_auth_token>
```

### Basic Update Request
```
PUT {{base_url}}/api/v1/passenger/profile
Authorization: Bearer {{passenger_token}}
Content-Type: application/json

{
  "name": "Jean Mugabo Updated",
  "phone": "+250780126094",
  "preferred_payment_method": "mobile_money",
  "emergency_contact_name": "Marie Mugabo",
  "emergency_contact_phone": "+250788654321"
}
```

## Validation Rules

| Field | Validation | Example |
|-------|-----------|---------|
| `name` | 1-255 characters | "Jean Mugabo" |
| `phone` | International format | "+250780126094" |
| `profile_photo` | URL or image file (max 1MB) | "profiles/photo.jpg" |
| `preferred_payment_method` | One of: card, mobile_money, cash, wallet | "mobile_money" |
| `emergency_contact_name` | 1-255 characters, nullable | "Marie Mugabo" |
| `emergency_contact_phone` | International format, nullable | "+250788654321" |

## Response Fields

All response fields match the GET `/v1/passenger/profile` endpoint:

- **Basic Info**: name, email, phone, profile_photo, role, approval/verification status
- **Statistics**: trips, bookings, spending, rating, reliability metrics (read-only)
- **Preferences**: Updated payment method, emergency contact, saved locations
- **Verification**: Verification and approval status with timestamps

## Error Handling

### Validation Errors
- Invalid phone format: Returns 422 with field errors
- Invalid payment method: Returns 422 with field errors
- Unauthorized request: Returns 401

### Common Issues
1. **Invalid Phone Format**: Must be international (+country_code)
2. **File Too Large**: Profile photo must be < 1MB
3. **Payment Method Invalid**: Must be one of the allowed values
4. **Expired Token**: Get new token and retry

## Security Notes

✅ Validates all input fields
✅ Phone number format validated
✅ File upload handled securely
✅ Only passengers can update their own profile
✅ Returns updated full profile (no data loss)
✅ Atomic updates (all or nothing)

## Integration Checklist

- [ ] Create PassengerService class
- [ ] Implement updateProfile method
- [ ] Create ProfileEditForm widget
- [ ] Handle validation errors
- [ ] Add photo picker functionality
- [ ] Test with all field combinations
- [ ] Implement offline caching
- [ ] Add loading indicators
- [ ] Handle network errors
