# Flutter Passenger App - Complete Implementation Example

## File Structure
```
lib/
├── services/
│   ├── api_service.dart
│   └── auth_service.dart
├── models/
│   ├── user_model.dart
│   └── auth_response_model.dart
├── screens/
│   ├── registration_screen.dart
│   ├── login_screen.dart
│   └── home_screen.dart
├── widgets/
│   ├── password_strength_widget.dart
│   └── form_text_field.dart
└── utils/
    ├── validators.dart
    ├── phone_formatter.dart
    └── constants.dart
```

---

## 1. API Service (services/api_service.dart)

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiService {
  static const String baseUrl = 'https://api.rideconnect.rw/api/v1';
  
  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'current_user';
  
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();
  
  // Get stored token
  Future<String?> getToken() async {
    return await _secureStorage.read(key: _tokenKey);
  }
  
  // Save token
  Future<void> saveToken(String token) async {
    await _secureStorage.write(key: _tokenKey, value: token);
  }
  
  // Delete token
  Future<void> deleteToken() async {
    await _secureStorage.delete(key: _tokenKey);
  }
  
  // Common headers
  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  
  // Headers with authentication
  Future<Map<String, String>> get _authHeaders async {
    final token = await getToken();
    return {
      ..._headers,
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }
  
  // API POST request
  Future<http.Response> post(
    String endpoint,
    Map<String, dynamic> body, {
    bool requiresAuth = false,
  }) async {
    try {
      final headers = requiresAuth ? await _authHeaders : _headers;
      final url = Uri.parse('$baseUrl$endpoint');
      
      return await http.post(
        url,
        headers: headers,
        body: jsonEncode(body),
      ).timeout(
        const Duration(seconds: 30),
        onTimeout: () => throw Exception('Request timeout'),
      );
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }
  
  // API GET request
  Future<http.Response> get(
    String endpoint, {
    bool requiresAuth = false,
  }) async {
    try {
      final headers = requiresAuth ? await _authHeaders : _headers;
      final url = Uri.parse('$baseUrl$endpoint');
      
      return await http.get(
        url,
        headers: headers,
      ).timeout(
        const Duration(seconds: 30),
        onTimeout: () => throw Exception('Request timeout'),
      );
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }
}
```

---

## 2. Auth Service (services/auth_service.dart)

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'api_service.dart';

class AuthService {
  final ApiService _apiService = ApiService();
  
  // Register new passenger
  Future<Map<String, dynamic>> registerPassenger({
    required String fullName,
    required String email,
    required String phoneNumber,
    required String password,
    required String passwordConfirmation,
  }) async {
    try {
      final response = await _apiService.post(
        '/auth/register/passenger',
        {
          'full_name': fullName.trim(),
          'email': email.trim().toLowerCase(),
          'phone_number': phoneNumber,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );
      
      if (response.statusCode == 201) {
        final data = jsonDecode(response.body);
        return {
          'success': true,
          'message': data['message'],
          'data': data['data'],
        };
      } else if (response.statusCode == 422) {
        final data = jsonDecode(response.body);
        return {
          'success': false,
          'message': 'Validation failed',
          'errors': data['errors'] ?? {},
        };
      } else {
        return {
          'success': false,
          'message': 'Registration failed. Please try again.',
          'errors': {},
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Network error: $e',
        'errors': {},
      };
    }
  }
  
  // Login with email or phone
  Future<Map<String, dynamic>> loginPassenger({
    required String login, // email or phone
    required String password,
    String deviceName = 'flutter-mobile',
  }) async {
    try {
      final response = await _apiService.post(
        '/auth/mobile/login',
        {
          'login': login.trim(),
          'password': password,
          'device_name': deviceName,
        },
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        // Save token
        if (data['data']['token'] != null) {
          await _apiService.saveToken(data['data']['token']);
        }
        
        return {
          'success': true,
          'message': 'Login successful',
          'data': data['data'],
        };
      } else if (response.statusCode == 403) {
        return {
          'success': false,
          'message': 'Your account is pending approval.',
          'error_code': 'ACCOUNT_PENDING',
        };
      } else if (response.statusCode == 401) {
        return {
          'success': false,
          'message': 'Invalid email/phone or password',
          'error_code': 'INVALID_CREDENTIALS',
        };
      } else {
        return {
          'success': false,
          'message': 'Login failed. Please try again.',
          'error_code': 'UNKNOWN_ERROR',
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'Network error: $e',
        'error_code': 'NETWORK_ERROR',
      };
    }
  }
  
  // Validate token
  Future<bool> validateToken() async {
    try {
      final response = await _apiService.get(
        '/auth/token/validate',
        requiresAuth: true,
      );
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }
  
  // Logout
  Future<void> logout() async {
    try {
      await _apiService.post(
        '/auth/logout',
        {},
        requiresAuth: true,
      );
    } catch (e) {
      print('Logout error: $e');
    } finally {
      await _apiService.deleteToken();
    }
  }
  
  // Check if user is logged in
  Future<bool> isLoggedIn() async {
    final token = await _apiService.getToken();
    if (token == null) return false;
    return await validateToken();
  }
}
```

---

## 3. Validators (utils/validators.dart)

```dart
class Validators {
  // Full name validation
  static String? validateFullName(String? value) {
    if (value == null || value.isEmpty) {
      return 'Full name is required';
    }
    if (value.length < 2) {
      return 'Full name must be at least 2 characters';
    }
    if (value.length > 255) {
      return 'Full name must be less than 255 characters';
    }
    if (!RegExp(r'^[a-zA-Z\s]+$').hasMatch(value)) {
      return 'Full name can only contain letters and spaces';
    }
    return null;
  }
  
  // Email validation
  static String? validateEmail(String? value) {
    if (value == null || value.isEmpty) {
      return 'Email is required';
    }
    const pattern =
        r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$';
    if (!RegExp(pattern).hasMatch(value)) {
      return 'Please enter a valid email address';
    }
    return null;
  }
  
  // Phone validation (E.164 format)
  static String? validatePhone(String? value) {
    if (value == null || value.isEmpty) {
      return 'Phone number is required';
    }
    if (!value.startsWith('+')) {
      return 'Phone number must start with +';
    }
    if (value.length < 8 || value.length > 20) {
      return 'Phone number must be 8-20 characters';
    }
    if (!RegExp(r'^\+[\d\s\-()]+$').hasMatch(value)) {
      return 'Invalid phone number format';
    }
    return null;
  }
  
  // Password validation
  static String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Password is required';
    }
    if (value.length < 8) {
      return 'Password must be at least 8 characters';
    }
    if (!RegExp(r'[A-Z]').hasMatch(value)) {
      return 'Password must include an uppercase letter';
    }
    if (!RegExp(r'[a-z]').hasMatch(value)) {
      return 'Password must include a lowercase letter';
    }
    if (!RegExp(r'[0-9]').hasMatch(value)) {
      return 'Password must include a number';
    }
    if (!RegExp(r'[!@#$%^&*()_+\-=\[\]{};:\'",.<>?/\\|`~]')
        .hasMatch(value)) {
      return 'Password must include a special character';
    }
    return null;
  }
  
  // Password confirmation validation
  static String? validatePasswordConfirmation(
    String? value,
    String password,
  ) {
    if (value == null || value.isEmpty) {
      return 'Please confirm your password';
    }
    if (value != password) {
      return 'Passwords do not match';
    }
    return null;
  }
  
  // Check password strength
  static PasswordStrength getPasswordStrength(String password) {
    int strength = 0;
    
    if (password.length >= 8) strength++;
    if (RegExp(r'[A-Z]').hasMatch(password)) strength++;
    if (RegExp(r'[a-z]').hasMatch(password)) strength++;
    if (RegExp(r'[0-9]').hasMatch(password)) strength++;
    if (RegExp(r'[!@#$%^&*()_+\-=\[\]{};:\'",.<>?/\\|`~]')
        .hasMatch(password)) strength++;
    
    switch (strength) {
      case 0:
      case 1:
        return PasswordStrength.weak;
      case 2:
      case 3:
        return PasswordStrength.medium;
      default:
        return PasswordStrength.strong;
    }
  }
}

enum PasswordStrength { weak, medium, strong }
```

---

## 4. Phone Formatter (utils/phone_formatter.dart)

```dart
class PhoneFormatter {
  // Convert various phone formats to E.164
  static String formatToE164(String phone) {
    // Remove all non-digit characters except +
    String cleaned = phone.replaceAll(RegExp(r'[^\d+]'), '');
    
    // If starts with 0 (local), replace with country code +250
    if (cleaned.startsWith('0')) {
      cleaned = '+250${cleaned.substring(1)}';
    }
    
    // If starts with 00250, replace with +250
    if (cleaned.startsWith('00250')) {
      cleaned = '+250${cleaned.substring(5)}';
    }
    
    // If no +, add +250 (Rwanda default)
    if (!cleaned.startsWith('+')) {
      cleaned = '+250$cleaned';
    }
    
    return cleaned;
  }
  
  // Format E.164 to display format: +250 (78) 800-0222
  static String formatForDisplay(String phone) {
    final e164 = formatToE164(phone);
    
    if (e164.length == 13 && e164.startsWith('+250')) {
      // Rwanda format
      final number = e164.substring(4); // 788000222
      return '+250 (${number.substring(0, 2)}) ${number.substring(2, 5)}-${number.substring(5)}';
    }
    
    return e164;
  }
  
  // Validate Rwanda phone number
  static bool isValidRwandaPhone(String phone) {
    final e164 = formatToE164(phone);
    // Rwanda: +250 followed by 9 digits
    return RegExp(r'^\+250\d{9}$').hasMatch(e164);
  }
}
```

---

## 5. Password Strength Widget (widgets/password_strength_widget.dart)

```dart
import 'package:flutter/material.dart';
import '../utils/validators.dart';

class PasswordStrengthWidget extends StatelessWidget {
  final String password;
  
  const PasswordStrengthWidget({
    Key? key,
    required this.password,
  }) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    if (password.isEmpty) return const SizedBox.shrink();
    
    final strength = Validators.getPasswordStrength(password);
    final color = _getColor(strength);
    final label = _getLabel(strength);
    
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text('Password Strength: $label'),
            const SizedBox(width: 12),
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: _getProgress(strength),
                  minHeight: 6,
                  backgroundColor: Colors.grey[300],
                  valueColor: AlwaysStoppedAnimation<Color>(color),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        _RequirementItem(
          text: 'At least 8 characters',
          met: password.length >= 8,
        ),
        _RequirementItem(
          text: 'Uppercase letter (A-Z)',
          met: RegExp(r'[A-Z]').hasMatch(password),
        ),
        _RequirementItem(
          text: 'Lowercase letter (a-z)',
          met: RegExp(r'[a-z]').hasMatch(password),
        ),
        _RequirementItem(
          text: 'Number (0-9)',
          met: RegExp(r'[0-9]').hasMatch(password),
        ),
        _RequirementItem(
          text: 'Special character (!@#\$%^&*)',
          met: RegExp(r'[!@#$%^&*()_+\-=\[\]{};:\'",.<>?/\\|`~]')
              .hasMatch(password),
        ),
      ],
    );
  }
  
  Color _getColor(PasswordStrength strength) {
    switch (strength) {
      case PasswordStrength.weak:
        return Colors.red;
      case PasswordStrength.medium:
        return Colors.orange;
      case PasswordStrength.strong:
        return Colors.green;
    }
  }
  
  String _getLabel(PasswordStrength strength) {
    switch (strength) {
      case PasswordStrength.weak:
        return 'Weak';
      case PasswordStrength.medium:
        return 'Medium';
      case PasswordStrength.strong:
        return 'Strong';
    }
  }
  
  double _getProgress(PasswordStrength strength) {
    switch (strength) {
      case PasswordStrength.weak:
        return 0.33;
      case PasswordStrength.medium:
        return 0.66;
      case PasswordStrength.strong:
        return 1.0;
    }
  }
}

class _RequirementItem extends StatelessWidget {
  final String text;
  final bool met;
  
  const _RequirementItem({
    required this.text,
    required this.met,
  });
  
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(
            met ? Icons.check_circle : Icons.radio_button_unchecked,
            color: met ? Colors.green : Colors.grey,
            size: 18,
          ),
          const SizedBox(width: 8),
          Text(
            text,
            style: TextStyle(
              color: met ? Colors.green : Colors.grey,
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }
}
```

---

## 6. Registration Screen (screens/registration_screen.dart)

```dart
import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../utils/validators.dart';
import '../utils/phone_formatter.dart';
import '../widgets/password_strength_widget.dart';

class RegistrationScreen extends StatefulWidget {
  const RegistrationScreen({Key? key}) : super(key: key);
  
  @override
  State<RegistrationScreen> createState() => _RegistrationScreenState();
}

class _RegistrationScreenState extends State<RegistrationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _authService = AuthService();
  
  late TextEditingController _nameController;
  late TextEditingController _emailController;
  late TextEditingController _phoneController;
  late TextEditingController _passwordController;
  late TextEditingController _confirmPasswordController;
  
  bool _isLoading = false;
  bool _passwordVisible = false;
  bool _confirmPasswordVisible = false;
  String _passwordStrengthLabel = '';
  
  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController();
    _emailController = TextEditingController();
    _phoneController = TextEditingController();
    _passwordController = TextEditingController();
    _confirmPasswordController = TextEditingController();
    
    _passwordController.addListener(_updatePasswordStrength);
  }
  
  void _updatePasswordStrength() {
    setState(() {
      _passwordStrengthLabel = _passwordController.text;
    });
  }
  
  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }
  
  Future<void> _handleRegistration() async {
    if (!_formKey.currentState!.validate()) return;
    
    setState(() => _isLoading = true);
    
    try {
      final phoneE164 = PhoneFormatter.formatToE164(_phoneController.text);
      
      final result = await _authService.registerPassenger(
        fullName: _nameController.text,
        email: _emailController.text,
        phoneNumber: phoneE164,
        password: _passwordController.text,
        passwordConfirmation: _confirmPasswordController.text,
      );
      
      if (!mounted) return;
      
      if (result['success']) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message']),
            backgroundColor: Colors.green,
          ),
        );
        
        // Navigate to login
        Navigator.of(context).pushReplacementNamed('/login');
      } else {
        _showErrorDialog(result);
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      setState(() => _isLoading = false);
    }
  }
  
  void _showErrorDialog(Map<String, dynamic> result) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Registration Failed'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(result['message']),
            if ((result['errors'] as Map).isNotEmpty) ...[
              const SizedBox(height: 16),
              const Text('Errors:'),
              ...(result['errors'] as Map).entries.map((e) => Text(
                '• ${e.key}: ${e.value}',
                style: const TextStyle(color: Colors.red, fontSize: 12),
              )),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Account'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Passenger Registration',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 24),
              
              // Full Name
              TextFormField(
                controller: _nameController,
                decoration: InputDecoration(
                  labelText: 'Full Name',
                  hintText: 'John Doe',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.person),
                ),
                textInputAction: TextInputAction.next,
                validator: Validators.validateFullName,
              ),
              const SizedBox(height: 16),
              
              // Email
              TextFormField(
                controller: _emailController,
                decoration: InputDecoration(
                  labelText: 'Email Address',
                  hintText: 'john@example.com',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.email),
                ),
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: Validators.validateEmail,
              ),
              const SizedBox(height: 16),
              
              // Phone
              TextFormField(
                controller: _phoneController,
                decoration: InputDecoration(
                  labelText: 'Phone Number',
                  hintText: '+250788000222',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.phone),
                ),
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                validator: Validators.validatePhone,
              ),
              const SizedBox(height: 16),
              
              // Password
              TextFormField(
                controller: _passwordController,
                decoration: InputDecoration(
                  labelText: 'Password',
                  hintText: 'SecurePass@123',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.lock),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _passwordVisible
                          ? Icons.visibility
                          : Icons.visibility_off,
                    ),
                    onPressed: () {
                      setState(() => _passwordVisible = !_passwordVisible);
                    },
                  ),
                ),
                obscureText: !_passwordVisible,
                textInputAction: TextInputAction.next,
                validator: Validators.validatePassword,
              ),
              const SizedBox(height: 8),
              
              // Password Strength Indicator
              PasswordStrengthWidget(password: _passwordStrengthLabel),
              const SizedBox(height: 16),
              
              // Confirm Password
              TextFormField(
                controller: _confirmPasswordController,
                decoration: InputDecoration(
                  labelText: 'Confirm Password',
                  hintText: 'SecurePass@123',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.lock),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _confirmPasswordVisible
                          ? Icons.visibility
                          : Icons.visibility_off,
                    ),
                    onPressed: () {
                      setState(
                        () =>
                            _confirmPasswordVisible = !_confirmPasswordVisible,
                      );
                    },
                  ),
                ),
                obscureText: !_confirmPasswordVisible,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _handleRegistration(),
                validator: (value) => Validators.validatePasswordConfirmation(
                  value,
                  _passwordController.text,
                ),
              ),
              const SizedBox(height: 24),
              
              // Register Button
              ElevatedButton(
                onPressed: _isLoading ? null : _handleRegistration,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: Colors.blue,
                  disabledBackgroundColor: Colors.grey,
                ),
                child: _isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      )
                    : const Text(
                        'Create Account',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
              const SizedBox(height: 16),
              
              // Login Link
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('Already have an account? '),
                  GestureDetector(
                    onTap: () =>
                        Navigator.of(context).pushReplacementNamed('/login'),
                    child: const Text(
                      'Sign In',
                      style: TextStyle(
                        color: Colors.blue,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

---

## 7. Login Screen (screens/login_screen.dart)

```dart
import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../utils/validators.dart';
import '../utils/phone_formatter.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);
  
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _authService = AuthService();
  
  late TextEditingController _loginController;
  late TextEditingController _passwordController;
  
  bool _isLoading = false;
  bool _passwordVisible = false;
  
  @override
  void initState() {
    super.initState();
    _loginController = TextEditingController();
    _passwordController = TextEditingController();
  }
  
  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }
  
  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;
    
    setState(() => _isLoading = true);
    
    try {
      // Try to parse as phone and format if needed
      String login = _loginController.text;
      if (!login.contains('@')) {
        login = PhoneFormatter.formatToE164(login);
      }
      
      final result = await _authService.loginPassenger(
        login: login,
        password: _passwordController.text,
        deviceName: 'flutter-mobile',
      );
      
      if (!mounted) return;
      
      if (result['success']) {
        // Navigate to home
        Navigator.of(context).pushReplacementNamed('/home');
      } else {
        if (result['error_code'] == 'ACCOUNT_PENDING') {
          _showPendingDialog();
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message']),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      setState(() => _isLoading = false);
    }
  }
  
  void _showPendingDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Account Pending Approval'),
        content: const Text(
          'Your account is currently being reviewed. '
          'You will receive an SMS notification when your account is ready to use.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 80),
              
              Text(
                'RideConnect',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: Colors.blue,
                ),
              ),
              const SizedBox(height: 8),
              
              Text(
                'Passenger Sign In',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 48),
              
              // Email or Phone
              TextFormField(
                controller: _loginController,
                decoration: InputDecoration(
                  labelText: 'Email or Phone',
                  hintText: 'john@example.com or +250788000222',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.person),
                ),
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Email or phone is required';
                  }
                  // Accept either email or phone
                  if (!value.contains('@') &&
                      !PhoneFormatter.isValidRwandaPhone(value)) {
                    return 'Please enter a valid email or phone';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              
              // Password
              TextFormField(
                controller: _passwordController,
                decoration: InputDecoration(
                  labelText: 'Password',
                  hintText: 'Enter your password',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.lock),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _passwordVisible
                          ? Icons.visibility
                          : Icons.visibility_off,
                    ),
                    onPressed: () {
                      setState(() => _passwordVisible = !_passwordVisible);
                    },
                  ),
                ),
                obscureText: !_passwordVisible,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _handleLogin(),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Password is required';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 8),
              
              // Forgot Password
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () {
                    // TODO: Implement forgot password
                  },
                  child: const Text(
                    'Forgot Password?',
                    style: TextStyle(color: Colors.blue),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              
              // Login Button
              ElevatedButton(
                onPressed: _isLoading ? null : _handleLogin,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: Colors.blue,
                  disabledBackgroundColor: Colors.grey,
                ),
                child: _isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      )
                    : const Text(
                        'Sign In',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
              const SizedBox(height: 24),
              
              // Divider
              Row(
                children: [
                  Expanded(child: Container(height: 1, color: Colors.grey)),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Text('or sign up with'),
                  ),
                  Expanded(child: Container(height: 1, color: Colors.grey)),
                ],
              ),
              const SizedBox(height: 24),
              
              // Social Login (placeholder)
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _SocialLoginButton(
                    icon: Icons.g_mobiledata,
                    label: 'Google',
                    onPressed: () {
                      // TODO: Implement Google login
                    },
                  ),
                  _SocialLoginButton(
                    icon: Icons.facebook,
                    label: 'Facebook',
                    onPressed: () {
                      // TODO: Implement Facebook login
                    },
                  ),
                  _SocialLoginButton(
                    icon: Icons.apple,
                    label: 'Apple',
                    onPressed: () {
                      // TODO: Implement Apple login
                    },
                  ),
                ],
              ),
              const SizedBox(height: 32),
              
              // Sign Up Link
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text('Don\'t have an account? '),
                  GestureDetector(
                    onTap: () => Navigator.of(context)
                        .pushReplacementNamed('/register'),
                    child: const Text(
                      'Sign Up',
                      style: TextStyle(
                        color: Colors.blue,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SocialLoginButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onPressed;
  
  const _SocialLoginButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });
  
  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      ),
    );
  }
}
```

---

## 8. pubspec.yaml Dependencies

```yaml
dependencies:
  flutter:
    sdk: flutter
  
  # HTTP & JSON
  http: ^1.1.0
  
  # Secure Storage
  flutter_secure_storage: ^9.0.0
  
  # State Management (optional but recommended)
  provider: ^6.0.0
  
  # Routing (optional)
  go_router: ^12.0.0

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0
```

---

## 9. main.dart Setup

```dart
import 'package:flutter/material.dart';
import 'screens/login_screen.dart';
import 'screens/registration_screen.dart';
import 'screens/home_screen.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);
  
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RideConnect Passenger',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        useMaterial3: true,
      ),
      home: const LoginScreen(),
      routes: {
        '/login': (_) => const LoginScreen(),
        '/register': (_) => const RegistrationScreen(),
        '/home': (_) => const HomeScreen(),
      },
    );
  }
}
```

---

*Complete Flutter Implementation Guide*
*Version: 1.0*
*Date: May 4, 2026*
