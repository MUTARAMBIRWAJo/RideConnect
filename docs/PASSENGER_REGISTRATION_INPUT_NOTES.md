# Passenger Registration - Input Fields Quick Reference

## Registration Form - Input Field Guide

### Field 1: Full Name
```
Label: Full Name
Type: Text Input
Required: Yes
Max Length: 255 characters
Pattern: Letters and spaces only
Example: "John Doe"
Validation Rules:
  - Required
  - Minimum 2 characters
  - Maximum 255 characters
  - No special characters except spaces
Error Messages:
  - "Full name is required"
  - "Full name must be at least 2 characters"
  - "Full name contains invalid characters"
Hint Text: "Enter your first and last name"
```

### Field 2: Email Address
```
Label: Email Address
Type: Email Input
Required: Yes
Max Length: 255 characters
Pattern: Valid email format (RFC 5322)
Example: "john.passenger@example.com"
Validation Rules:
  - Required
  - Valid email format
  - Unique (not already registered)
  - Maximum 255 characters
Error Messages:
  - "Email is required"
  - "Please enter a valid email address"
  - "This email is already registered"
  - "Email must be less than 255 characters"
Hint Text: "example@domain.com"
```

### Field 3: Phone Number
```
Label: Phone Number
Type: Phone Input (E.164 format)
Required: Yes
Max Length: 20 characters
Pattern: International format with + prefix
Example: "+250788000222" (Rwanda)
Validation Rules:
  - Required
  - Valid phone number format
  - International format (starts with +)
  - Maximum 20 characters
  - Should be unique per user
Error Messages:
  - "Phone number is required"
  - "Please enter a valid phone number"
  - "Phone number must include country code (+)"
  - "Phone number must be 8-20 characters"
Hint Text: "+250788000222"
Input Format:
  - Show phone input keyboard on mobile
  - Allow +, -, (, ) characters for formatting
  - Store/send in E.164 format: "+250788000222"
  - Display formatted: "+250 (78) 800-0222"
```

### Field 4: Password
```
Label: Password
Type: Password Input (masked)
Required: Yes
Min Length: 8 characters
Complexity: Uppercase + Lowercase + Number + Special Character
Example: "SecurePass@123"
Validation Rules:
  - Required
  - Minimum 8 characters
  - At least 1 UPPERCASE letter (A-Z)
  - At least 1 lowercase letter (a-z)
  - At least 1 number (0-9)
  - At least 1 special character (!@#$%^&*)
Error Messages:
  - "Password is required"
  - "Password must be at least 8 characters"
  - "Password must include an uppercase letter"
  - "Password must include a lowercase letter"
  - "Password must include a number"
  - "Password must include a special character (!@#$%^&*)"
Hint Text: "Minimum 8 characters with uppercase, lowercase, number, and symbol"
Security Tips:
  - Hide password by default (show toggle)
  - Show password strength meter (weak/medium/strong)
  - Prevent autocomplete on this field
  - Do not log or display password
```

### Field 5: Confirm Password
```
Label: Confirm Password
Type: Password Input (masked)
Required: Yes
Match: Must match Password field exactly
Example: "SecurePass@123"
Validation Rules:
  - Required
  - Must match Password field exactly
  - Case-sensitive comparison
Error Messages:
  - "Confirm password is required"
  - "Passwords do not match"
  - "Please ensure both password fields are identical"
Hint Text: "Re-enter your password"
Security Tips:
  - Show password strength comparison
  - Enable/disable submit button based on match
  - Validate on change (real-time feedback)
```

---

## Registration Form - Complete Validation Summary

### On Focus
- Show field description/help text
- Clear previous error messages

### On Input (Real-time)
- **Full Name**: Check length and character types
- **Email**: Basic format validation
- **Phone**: Length and format validation
- **Password**: Show strength meter and requirements checklist
- **Confirm Password**: Show match indicator

### On Blur (Field Loss of Focus)
- **Full Name**: Trim whitespace, validate
- **Email**: Format validation
- **Phone**: Format validation, normalize to E.164
- **Password**: Full complexity validation
- **Confirm Password**: Match validation

### On Submit (Form Submission)
- Validate all required fields
- Validate all format requirements
- Check for unique email
- Check for unique phone (recommended)
- Submit only if all validations pass
- Show loading indicator
- Disable submit button during request

---

## Login Form - Input Field Guide

### Field 1: Login (Email or Phone)
```
Label: Email or Phone Number
Type: Text Input (flexible)
Required: Yes
Example: "john@example.com" or "+250788000222"
Validation Rules:
  - Required
  - Valid email OR valid phone format
  - User must exist in system
Error Messages:
  - "Email or phone is required"
  - "Please enter a valid email or phone number"
  - "Invalid credentials"
Hint Text: "Enter your email or phone number"
Behavior:
  - Accept both email and phone
  - Auto-detect format
  - Show email keyboard if email-like
  - Show phone keyboard if phone-like
```

### Field 2: Password
```
Label: Password
Type: Password Input (masked)
Required: Yes
Example: "SecurePass@123"
Validation Rules:
  - Required
  - Minimum 1 character (full validation on backend)
Error Messages:
  - "Password is required"
  - "Invalid credentials"
Hint Text: "Enter your password"
Features:
  - Show/hide toggle button
  - Prevent autocomplete
  - Focus on field automatically
```

---

## Response Handling - Error Messages for User

### Registration Errors

| Error Code | HTTP Status | User-Friendly Message | Action |
|-----------|------------|----------------------|--------|
| VALIDATION_ERROR | 422 | "Please check your information and try again" | Show field-level errors |
| EMAIL_EXISTS | 422 | "This email is already registered. Please log in instead." | Show login link |
| PHONE_INVALID | 422 | "Please enter a valid phone number" | Focus phone field |
| PASSWORD_WEAK | 422 | "Password doesn't meet security requirements" | Show requirements |
| SERVER_ERROR | 500 | "Something went wrong. Please try again later." | Show retry button |
| NETWORK_ERROR | N/A | "Check your internet connection" | Show retry button |

### Login Errors

| Error Code | HTTP Status | User-Friendly Message | Action |
|-----------|------------|----------------------|--------|
| INVALID_CREDENTIALS | 401 | "Invalid email/phone or password" | Clear fields, focus login |
| ACCOUNT_PENDING | 403 | "Your account is being reviewed. You'll receive an SMS when approved." | Show wait message |
| ACCOUNT_SUSPENDED | 403 | "Your account has been suspended. Contact support." | Show support contact |
| NOT_FOUND | 404 | "Account not found" | Show registration link |
| SERVER_ERROR | 500 | "Login service unavailable. Please try again." | Show retry button |
| NETWORK_ERROR | N/A | "No internet connection. Please try again." | Show retry button |

---

## Success Messages

### Registration Success
```
"Welcome! Your account has been created successfully.
Waiting for admin approval to activate your account.
You will receive an SMS notification when your account is ready."
```

### Login Success
```
"Welcome back, [User Name]!
You are now logged in."
```

---

## Input Formatting Examples

### Phone Number Formatting
```
User Input: 0788000222
Display: +250 (78) 800-0222
Store/Send: +250788000222

User Input: +250 788 000 222
Display: +250 (78) 800-0222
Store/Send: +250788000222

User Input: 00250788000222
Display: +250 (78) 800-0222
Store/Send: +250788000222
```

### Email Formatting
```
User Input: JOHN@EXAMPLE.COM
Store/Send: john@example.com

User Input:  john.passenger@example.com  
Store/Send: john.passenger@example.com
(Trim whitespace)
```

---

## Password Strength Indicator

```
Display in Registration:

Password Strength: ▯▯▯ (Weak)
Requirements:
  ✓ At least 8 characters
  ✗ Uppercase letter (A-Z)
  ✗ Lowercase letter (a-z)
  ✗ Number (0-9)
  ✗ Special character (!@#$%^&*)

As user types:

Password Strength: ██▯ (Medium)
Requirements:
  ✓ At least 8 characters
  ✓ Uppercase letter (A-Z)
  ✓ Lowercase letter (a-z)
  ✓ Number (0-9)
  ✗ Special character (!@#$%^&*)

Final:

Password Strength: ███ (Strong)
Requirements:
  ✓ At least 8 characters
  ✓ Uppercase letter (A-Z)
  ✓ Lowercase letter (a-z)
  ✓ Number (0-9)
  ✓ Special character (!@#$%^&*)
```

---

## Mobile Form UI Recommendations

### Responsive Layout
- Single column on mobile
- 48px minimum touch target height
- 16px padding around fields
- Full-width inputs on mobile

### Keyboard Type by Input
- **Full Name**: Text keyboard
- **Email**: Email keyboard (@, .com visible)
- **Phone**: Phone keyboard (+, numbers)
- **Password**: Default (masked text)

### Form Submission
- Submit button text: "Create Account" (registration) or "Sign In" (login)
- Button remains disabled until all required fields valid
- Show spinner during API request
- Disable button during request to prevent duplicate submissions

### Error Display
- Show inline errors below field (red text)
- Highlight error fields with red border
- Show field-level error messages, not generic errors
- Clear error when user starts fixing field

---

## API Integration Checklist

- [ ] Strip/trim whitespace from all inputs
- [ ] Convert email to lowercase before sending
- [ ] Format phone to E.164 format before sending
- [ ] Validate password strength on client before sending
- [ ] Show loading state during request
- [ ] Handle 201 (registration success) with message
- [ ] Handle 422 (validation error) with field errors
- [ ] Handle 403 (pending approval) with specific message
- [ ] Handle 401 (invalid login) with generic message
- [ ] Store token in secure storage after login
- [ ] Redirect to home/dashboard after successful login
- [ ] Show logout button after successful login
- [ ] Implement retry logic for network errors

---

*Generated: May 4, 2026*
*Version: 1.0*
