# 🚀 Passenger Profile Update - Flutter Quick Start

## Endpoint
```
PUT /api/v1/passenger/profile
```

## 3-Minute Setup

### 1. Copy Service Class
```dart
class PassengerService {
  final String baseUrl = 'http://localhost:8000';
  
  Future<Map<String, dynamic>> updateProfile({
    required String token,
    String? name,
    String? phone,
    String? preferredPaymentMethod,
    String? emergencyContactName,
    String? emergencyContactPhone,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) body['name'] = name;
    if (phone != null) body['phone'] = phone;
    if (preferredPaymentMethod != null) body['preferred_payment_method'] = preferredPaymentMethod;
    if (emergencyContactName != null) body['emergency_contact_name'] = emergencyContactName;
    if (emergencyContactPhone != null) body['emergency_contact_phone'] = emergencyContactPhone;

    final response = await http.put(
      Uri.parse('$baseUrl/api/v1/passenger/profile'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(body),
    );

    if (response.statusCode == 200) return jsonDecode(response.body);
    throw Exception('Failed: ${response.statusCode}');
  }
}
```

### 2. Use in Widget
```dart
final service = PassengerService();
final result = await service.updateProfile(
  token: userToken,
  name: nameController.text,
  phone: phoneController.text,
  preferredPaymentMethod: 'mobile_money',
);
```

### 3. Handle Response
```dart
print(result['data']['name']);  // Updated name
print(result['data']['phone']);  // Updated phone
print(result['data']['statistics']['rating']);  // Auto-calculated
```

## All Updateable Fields

| Field | Format | Required |
|-------|--------|----------|
| name | "Jean Mugabo" | No |
| phone | "+250780126094" | No |
| preferred_payment_method | "card", "mobile_money", "cash", "wallet" | No |
| emergency_contact_name | "Marie Mugabo" | No |
| emergency_contact_phone | "+250788654321" | No |

## Example: Update Payment Method Only
```dart
final result = await service.updateProfile(
  token: authToken,
  preferredPaymentMethod: 'card',
);
// Response includes full updated profile
print(result['data']['preferences']['preferred_payment_method']); // "card"
```

## Full Response Includes
- ✅ Basic info (name, phone, email, role, photo)
- ✅ Statistics (trips, bookings, rating, spent)
- ✅ Preferences (payment method, emergency contact)
- ✅ Verification status

## Error Handling
```dart
try {
  final result = await service.updateProfile(
    token: token,
    phone: '+invalid',
  );
} on Exception catch (e) {
  print('Error: $e');
  // Shows validation errors
}
```

## Validation Rules
- **Phone**: Must match +country_code format
- **Payment**: Must be one of 4 allowed values
- **Name**: Max 255 characters
- **Emergency Contact**: Optional but validated if provided

## Testing Quick Command
```bash
curl -X PUT http://localhost:8000/api/v1/passenger/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Updated Name"}'
```

## 📚 Full Documentation
See `PASSENGER_PROFILE_UPDATE_GUIDE.md` for complete details including:
- File upload examples
- Complete widget implementation
- State management integration
- Error handling patterns

---

**Ready to implement?** Copy the service class above and start using the endpoint!
