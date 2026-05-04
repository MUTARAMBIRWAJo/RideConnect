# Mobile App Design & Flow Documentation

**RideConnect Mobile Application**  
**Version:** 2.0  
**Design System:** Material 3 & Cupertino  
**Last Updated:** May 2026

---

## Table of Contents

1. [Design Principles](#design-principles)
2. [Navigation Structure](#navigation-structure)
3. [Passenger App Flows](#passenger-app-flows)
4. [Driver App Flows](#driver-app-flows)
5. [Shared Components](#shared-components)
6. [Screen Specifications](#screen-specifications)
7. [Data Display Patterns](#data-display-patterns)

---

## Design Principles

### Accessibility First
- WCAG 2.1 AA compliance
- Minimum touch target size: 44x44 dp
- Proper color contrast ratios (4.5:1 for text)
- Support for screen readers and voice navigation

### Performance
- Maximum screen load time: 2 seconds
- Smooth 60fps animations
- Lazy loading for lists
- Efficient image caching

### Clarity
- Clear primary and secondary actions
- Single primary button per screen when possible
- Consistent navigation patterns
- Real-time feedback for user actions

### Delight
- Micro-interactions for feedback
- Smooth transitions between screens
- Contextual help and tooltips
- Success celebrations for key actions

---

## Navigation Structure

### Bottom Tab Navigation (Passenger)

```
╔════════════════════════════════════════════════════════════╗
║                        Main Content Area                    ║
║                                                              ║
║                                                              ║
║                                                              ║
║                                                              ║
║                                                              ║
║ ┌────────────────────────────────────────────────────────┐ ║
║ │ 🏠 Home  │ 📍 Rides  │ 📋 Bookings  │ 👤 Profile      │ ║
║ └────────────────────────────────────────────────────────┘ ║
╚════════════════════════════════════════════════════════════╝
```

**Tabs:**
1. **Home** - Quick actions, recent trips, promotions
2. **Rides** - Browse and search available rides
3. **Bookings** - Manage active and past bookings
4. **Profile** - Account settings, wallet, support

### Drawer Navigation (Driver)

```
╔════════════════════════════════════════════════════════════╗
║ ☰                                                     [👤]  ║
║                                                              ║
║ Main Content Area                                             ║
║                                                              ║
╚════════════════════════════════════════════════════════════╝

Drawer:
├── Dashboard
├── My Trips
├── Earnings
├── Rides
├── Profile
├── Documents
├── Support
├── Settings
└── Logout
```

---

## Passenger App Flows

### Flow 1: Browse & Book Scheduled Ride

```
┌─────────────────────────────────────────────────────────┐
│                   Passenger Home Screen                   │
│  ┌──────────────────────────────────────────────────┐   │
│  │ Quick Search                                      │   │
│  │ From: [Select pickup]    To: [Select dropoff]   │   │
│  │ [Search Rides]                                   │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  Recent Activity                                        │
│  ├─ Trip to Kigali City Tower (May 3)                │
│  └─ Payment: 2,500 RWF ✓                             │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│              Rides Search Results Screen                 │
│  Filter: [Transport] [Date] [Price]                     │
│  ┌──────────────────────────────────────────────────┐   │
│  │ 🚖 SCHEDULED CAR                                 │   │
│  │ Kimihurura → Kigali City Tower                  │   │
│  │ May 4 at 2:30 PM                                │   │
│  │ Jane Smith ★ 4.9                                │   │
│  │ Available: 3 seats                              │   │
│  │ 2,500 RWF/seat  [Book Now]                      │   │
│  └──────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │ 🚌 SCHEDULED BUS                                │   │
│  │ CBD → Nyarutarama                              │   │
│  │ May 4 at 3:00 PM                                │   │
│  │ Operator: City Transport ★ 4.6                  │   │
│  │ Available: 12 seats                             │   │
│  │ 1,500 RWF/seat  [Book Now]                      │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Book Now")
         ▼
┌─────────────────────────────────────────────────────────┐
│              Booking Details Screen                      │
│  🚖 Jane's Car - Toyota Prius (Silver)                   │
│  RAJ123A                                                 │
│                                                          │
│  Route Details                                           │
│  From: Kimihurura Roundabout                            │
│  To: Kigali City Tower                                  │
│  May 4, 2:30 PM                                         │
│                                                          │
│  Number of Seats: [- 1 +]  (Selected: 1)               │
│                                                          │
│  Price Breakdown                                         │
│  Base Fare: 1,500 RWF                                  │
│  Seats (1): 2,500 RWF                                  │
│  ─────────────────────────────────                      │
│  Total: 2,500 RWF                                       │
│                                                          │
│  Special Requests (Optional)                            │
│  [Please wait at main entrance]                         │
│                                                          │
│  Payment Method: [Select Method ▼]                      │
│  Mobile Money ✓                                         │
│                                                          │
│                              [Cancel]  [Confirm Booking] │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Confirm Booking")
         ▼
┌─────────────────────────────────────────────────────────┐
│           Payment Confirmation Screen                    │
│                                                          │
│  💳 Payment Method: Mobile Money                        │
│  Phone Number: +250788123456                            │
│                                                          │
│  Amount: 2,500 RWF                                      │
│                                                          │
│  [Enter PIN]                                            │
│                                                          │
│  ⓘ Your booking confirmation will be sent              │
│    to your email and SMS                                │
│                                                          │
│                      [Cancel]  [Pay 2,500 RWF]         │
└─────────────────────────────────────────────────────────┘
         │ (Payment Complete)
         ▼
┌─────────────────────────────────────────────────────────┐
│        Booking Confirmation Screen (Success)             │
│                                                          │
│  ✓ Booking Confirmed!                                   │
│                                                          │
│  Confirmation Code: RIDE-101-5001                       │
│                                                          │
│  📍 Kimihurura Roundabout to Kigali City Tower         │
│  📅 May 4, 2:30 PM                                     │
│  👤 Jane Smith ★ 4.9                                   │
│  🚖 Toyota Prius (RAJ123A)                             │
│                                                          │
│  Your seat is reserved. Payment confirmed.              │
│                                                          │
│  [View Booking Details]                                 │
│  [Share Confirmation]                                   │
│  [Continue Shopping]                                    │
└─────────────────────────────────────────────────────────┘
```

### Flow 2: Request On-Demand Trip

```
┌─────────────────────────────────────────────────────────┐
│                   Passenger Home Screen                   │
│  ┌──────────────────────────────────────────────────┐   │
│  │ Ride Now?                                        │   │
│  │ From: [📍 Your Location]  To: [📍 Where to?]   │   │
│  │                                                   │   │
│  │ Transport Type:                                  │   │
│  │ ○ Car  ○ Motorcycle  ○ Bus                      │   │
│  │                                                   │   │
│  │                     [Request Ride]               │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Request Ride")
         ▼
┌─────────────────────────────────────────────────────────┐
│          Location Selection Screen                       │
│                                                          │
│  Where are you?                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │ [📍 Pick up location]  [Use Current Location]   │   │
│  │                                                   │   │
│  │ Recent:                                          │   │
│  │ • Home - Kimihurura Roundabout                 │   │
│  │ • Work - Kigali Business Park                  │   │
│  │ • Gym - Kisimenti Health Club                  │   │
│  │ • Other                                          │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  Where to?                                              │
│  ┌──────────────────────────────────────────────────┐   │
│  │ [📍 Drop off location]                          │   │
│  │                                                   │   │
│  │ Recent:                                          │   │
│  │ • Work - Kigali Business Park                  │   │
│  │ • Home - Kimihurura Roundabout                 │   │
│  │ • Other                                          │   │
│  └──────────────────────────────────────────────────┘   │
│                                  [Cancel]  [Continue]   │
└─────────────────────────────────────────────────────────┘
         │ (Confirm Locations)
         ▼
┌─────────────────────────────────────────────────────────┐
│        Ride Request - Matching in Progress              │
│                                                          │
│  🔄 Finding available drivers...                        │
│                                                          │
│  Pickup: Kimihurura Roundabout                          │
│  Dropoff: Kigali City Tower                             │
│                                                          │
│  Estimated Fare: 2,500 RWF                             │
│  ETA: 5 - 12 minutes                                    │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │                                                     │ │
│  │  ┌──────────────────────────────────────────────┐ │ │
│  │  │           [Animated Map]                     │ │ │
│  │  │  Shows pickup/dropoff locations             │ │ │
│  │  │                                              │ │ │
│  │  └──────────────────────────────────────────────┘ │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│                          [Cancel Request]               │
└─────────────────────────────────────────────────────────┘
         │ (Driver Accepts)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Driver Accepted - Live Tracking Screen           │
│                                                          │
│  ✓ Trip Accepted                                         │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │                                                     │ │
│  │  ┌──────────────────────────────────────────────┐ │ │
│  │  │  🗺️ Live Map with Driver Location          │ │ │
│  │  │  (Blue dot = Driver, Red = Pickup)          │ │ │
│  │  │                                              │ │ │
│  │  │  Distance: 1.2 km away                       │ │ │
│  │  │  ETA to Pickup: 4 min                        │ │ │
│  │  └──────────────────────────────────────────────┘ │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Driver Details                                         │
│  🚖 Jane Smith ★ 4.9 (287 trips)                       │
│  Silver Toyota Prius (RAJ123A)                         │
│                                                          │
│  ☎️ [Call Driver]  📍 [Share ETA]                     │
│                                                          │
│  Estimated Trip Cost: 2,500 RWF                        │
│                                          [Cancel]       │
└─────────────────────────────────────────────────────────┘
         │ (Driver Arrives)
         ▼
┌─────────────────────────────────────────────────────────┐
│            Driver Arrived Screen                         │
│                                                          │
│  ✓ Driver Arrived!                                      │
│                                                          │
│  I'm at Kimihurura Roundabout                           │
│  Please hurry, I have limited time to wait              │
│                                                          │
│  🚖 Jane Smith                                          │
│  Silver Toyota Prius (RAJ123A)                         │
│  Driver Photo                                           │
│                                                          │
│  Waiting: 1 min (Will wait 5 more minutes)             │
│                                                          │
│  ☎️ [Call Driver]  📍 [GPS Navigation]                │
│                                                          │
│  [Passenger Arrived]                                    │
└─────────────────────────────────────────────────────────┘
         │ (Passenger Gets In)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Trip in Progress - Tracking Screen               │
│                                                          │
│  🚗 Trip in Progress                                    │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  🗺️ Live Route Map                               │ │
│  │  Driver heading to: Kigali City Tower             │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Trip Progress                                          │
│  Distance: 1.8 km / 2.5 km (72%)                       │
│  ETA: 3 minutes                                        │
│                                                          │
│  Route:                                                 │
│  KN 1 Ave → Nelson Mandela Ave → Main Street           │
│                                                          │
│  Current Fare: 2,300 RWF                              │
│                                                          │
│  🎵 [Music Control]  ☎️ [Call]  ⓘ [More Options]     │
└─────────────────────────────────────────────────────────┘
         │ (Driver Completes Trip)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Trip Completed - Payment Screen                  │
│                                                          │
│  ✓ Trip Completed!                                      │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  From: Kimihurura Roundabout                       │ │
│  │  To: Kigali City Tower                             │ │
│  │  Distance: 2.5 km                                  │ │
│  │  Duration: 12 minutes                              │ │
│  │                                                     │ │
│  │  Trip Fare:              2,500 RWF                 │ │
│  │  Surge Multiplier (1.0):  1.0x                     │ │
│  │  ─────────────────────────────────────────         │ │
│  │  Total Fare:             2,500 RWF                 │ │
│  │                                                     │ │
│  │  Payment: Mobile Money ✓                           │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  [Tip Driver (+200 RWF)]                               │
│                          [Next Ride]  [Rate Driver]     │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Rate Driver")
         ▼
┌─────────────────────────────────────────────────────────┐
│          Rate Driver & Trip Screen                       │
│                                                          │
│  How was your trip?                                     │
│                                                          │
│  Driver Rating:                                         │
│  ⭐⭐⭐⭐⭐  (Click to rate)                            │
│                                                          │
│  Rate in Categories:                                    │
│  🧹 Cleanliness:        ⭐⭐⭐⭐⭐                     │
│  🔒 Safety:              ⭐⭐⭐⭐⭐                     │
│  💬 Communication:       ⭐⭐⭐⭐⭐                     │
│  🚗 Driving Skill:       ⭐⭐⭐⭐⭐                     │
│                                                          │
│  Comments (Optional):                                   │
│  [Great driver, very professional and friendly!]      │
│                                                          │
│  Would you recommend this driver?                       │
│  ◉ Yes   ○ No                                          │
│                                                          │
│                              [Cancel]  [Submit Rating]  │
└─────────────────────────────────────────────────────────┘
```

### Flow 3: Trip Cancellation & Support

```
Trip Details Screen → [⋮ More Options]
                         │
                         ▼
                   [Cancel Trip]
                         │
                         ▼
        ┌─────────────────────────────────┐
        │   Why do you want to cancel?    │
        │                                 │
        │  ○ Found another ride           │
        │  ○ Driver taking too long       │
        │  ○ Changed my mind              │
        │  ○ Vehicle issue                │
        │  ○ Other: [text field]          │
        │                                 │
        │ [Cancel]  [Confirm Cancellation]│
        └─────────────────────────────────┘
                         │
                         ▼
        ┌─────────────────────────────────┐
        │  Cancellation Confirmed         │
        │                                 │
        │  ✓ Your trip has been cancelled │
        │  Refund: 2,500 RWF              │
        │  Processing time: 24-48 hours   │
        │                                 │
        │  Need help?                     │
        │  [Contact Support]              │
        │                                 │
        │  [Back to Home]                 │
        └─────────────────────────────────┘
```

---

## Driver App Flows

### Flow 1: Driver Registration & Onboarding

```
┌─────────────────────────────────────────────────────────┐
│              Driver Registration Screen 1                │
│                                                          │
│  Become a RideConnect Driver                             │
│                                                          │
│  Personal Information                                    │
│  Name: [Jane Smith________________]                     │
│  Email: [jane@example.com_______________________]       │
│  Phone: [+250 788 654 321_________________]             │
│  Password: [••••••••••]                                 │
│                                                          │
│  Date of Birth: [Select Date ▼]                        │
│                                                          │
│  License Information                                     │
│  License Number: [DR123456_____________________]       │
│  Expiry Date: [Select Date ▼]                          │
│                                                          │
│                          [Continue]                      │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│              Driver Registration Screen 2                │
│                                                          │
│  Vehicle Information                                     │
│                                                          │
│  Vehicle Type:                                           │
│  ○ Car  ○ Motorcycle  ○ Bus                            │
│                                                          │
│  Vehicle Details                                        │
│  Make: [Toyota_________________]                        │
│  Model: [Prius_________________]                        │
│  Year: [2023]                                           │
│  Color: [Silver_________________]                       │
│  License Plate: [RAJ123A_________________]             │
│  Number of Seats: [4]                                  │
│                                                          │
│  Vehicle Photo                                          │
│  [📷 Take Photo / Upload Photo]                        │
│                                                          │
│                          [Continue]                      │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│              Driver Registration Screen 3                │
│                                                          │
│  Documents                                              │
│                                                          │
│  Upload your documents for verification:                │
│                                                          │
│  📄 Driver License                                      │
│  ☐ [Upload Document] / [📷 Take Photo]                │
│                                                          │
│  📄 National ID                                         │
│  ☐ [Upload Document] / [📷 Take Photo]                │
│                                                          │
│  📄 Vehicle Registration                               │
│  ☐ [Upload Document] / [📷 Take Photo]                │
│                                                          │
│  📄 Insurance Certificate                               │
│  ☐ [Upload Document] / [📷 Take Photo]                │
│                                                          │
│  ℹ️ Documents will be verified within 24 hours          │
│                                                          │
│                          [Continue]                      │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│              Driver Registration Screen 4                │
│                                                          │
│  Bank Account Information                               │
│                                                          │
│  Bank Name: [Rwanda Development Bank_______________]   │
│                                                          │
│  Account Number: [1234567890____________________]       │
│                                                          │
│  Account Holder Name: [Jane Smith____________________] │
│                                                          │
│  Account Type:                                          │
│  ○ Checking  ○ Savings                                 │
│                                                          │
│  ℹ️ Your earnings will be transferred to this account   │
│                                                          │
│  □ I agree to the Terms & Conditions                    │
│  □ I agree to the Privacy Policy                        │
│                                                          │
│                     [Cancel]  [Complete Registration]  │
└─────────────────────────────────────────────────────────┘
         │ (Submitted)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Driver Registration Complete Screen              │
│                                                          │
│  ✓ Welcome to RideConnect!                              │
│                                                          │
│  Your application has been received.                    │
│  Verification typically takes 24 hours.                 │
│                                                          │
│  What happens next:                                     │
│  1. ✓ Personal information verified                     │
│  2. ⏳ Documents under review                           │
│  3. ⏳ Background check in progress                     │
│  4. ⏳ Approval email sent                               │
│                                                          │
│  You'll receive an email when your account is           │
│  approved. Until then, you can explore the app.         │
│                                                          │
│  [Next: Complete Your Profile]                         │
└─────────────────────────────────────────────────────────┘
```

### Flow 2: Accept & Complete Trip

```
┌─────────────────────────────────────────────────────────┐
│            Driver Dashboard (Online Status)              │
│                                                          │
│  Status: ● ONLINE                                       │
│                                                          │
│  Today's Earnings: 45,000 RWF                          │
│  Trips Completed: 8                                     │
│  Rating: ★ 4.9 (287 trips)                             │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  🗺️ Live Map (Blue dot = Your Location)           │ │
│  │                                                     │ │
│  │  Tapping map updates your location in real-time    │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Available Trip Requests: 3                             │
│  ───────────────────────────────────────────           │
│  [Tap to see trip requests]                             │
│                                                          │
│                       [Go Offline]                       │
└─────────────────────────────────────────────────────────┘
         │ (Trip Requests Appear)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Trip Request - Arrival Alert Screen              │
│                                                          │
│  🔔 NEW TRIP REQUEST                                    │
│                                                          │
│  👤 John Doe ★ 4.8 (42 trips)                          │
│                                                          │
│  📍 Kimihurura Roundabout                               │
│  📍 Kigali City Tower                                   │
│                                                          │
│  Distance from you: 0.8 km (3 min drive)               │
│  Estimated fare: 2,500 RWF                             │
│  Your earnings: 2,000 RWF                              │
│                                                          │
│  ⏱️ Expires in: 30 seconds                              │
│                                                          │
│                  [Reject]  [Accept Trip]               │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Accept Trip")
         ▼
┌─────────────────────────────────────────────────────────┐
│      Trip Accepted - Heading to Pickup Screen            │
│                                                          │
│  ✓ Trip Accepted!                                       │
│                                                          │
│  Passenger: John Doe ★ 4.8                             │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  🗺️ Route to Pickup                               │ │
│  │  Current location → Kimihurura Roundabout          │ │
│  │                                                     │ │
│  │  Distance: 0.8 km                                  │ │
│  │  ETA: 3 minutes                                    │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  📍 Pickup: Kimihurura Roundabout                       │
│  📍 Dropoff: Kigali City Tower                         │
│                                                          │
│  Trip Details                                           │
│  Estimated Distance: 2.5 km                            │
│  Estimated Duration: 12 min                            │
│  Estimated Fare: 2,500 RWF                            │
│                                                          │
│  ☎️ [Call Passenger]  ⓘ [Cancel Trip]                 │
└─────────────────────────────────────────────────────────┘
         │ (Arrived at Pickup)
         ▼
┌─────────────────────────────────────────────────────────┐
│         Arrived at Pickup - Waiting Screen               │
│                                                          │
│  ✓ You Arrived!                                         │
│                                                          │
│  I'm at Kimihurura Roundabout                          │
│                                                          │
│  Passenger: John Doe ★ 4.8                             │
│  Waiting time: 0:30                                     │
│  (Will wait 5 more minutes)                             │
│                                                          │
│  ☎️ [Call Passenger]                                   │
│  📍 [Share Your Location]                              │
│  🔄 [Wait Longer]  ✗ [Cancel Trip]                    │
│                                                          │
│  ⓘ After 5 minutes, you can cancel without penalty    │
└─────────────────────────────────────────────────────────┘
         │ (Passenger Gets In)
         ▼
┌─────────────────────────────────────────────────────────┐
│      Trip in Progress - Navigation Screen                │
│                                                          │
│  🚗 Trip in Progress                                    │
│                                                          │
│  Passenger: John Doe                                    │
│  Destination: Kigali City Tower                        │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  🗺️ Navigation Map with Turn-by-Turn Directions  │ │
│  │  "In 200m, turn right on KN 1 Ave"               │ │
│  │                                                     │ │
│  │  ETA: 10 minutes                                   │ │
│  │  Distance remaining: 2.1 km                        │ │
│  │                                                     │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  Trip Status                                            │
│  Progress: ▓▓▓▓░░░░░░ 45%                              │
│  Current Fare: 2,100 RWF                              │
│                                                          │
│  🎵 [Quiet Mode]  ☎️ [Mute Call]  ⓘ [More]            │
└─────────────────────────────────────────────────────────┘
         │ (Arrived at Destination)
         ▼
┌─────────────────────────────────────────────────────────┐
│      Trip Completed - Confirmation Screen                │
│                                                          │
│  ✓ Trip Completed!                                      │
│                                                          │
│  Passenger has been notified.                           │
│                                                          │
│  Trip Summary                                           │
│  Distance: 2.5 km                                      │
│  Duration: 12 minutes                                  │
│  Final Fare: 2,500 RWF                                │
│  Your Earnings: 2,000 RWF                             │
│                                                          │
│  Passenger Rating: ⭐ (Waiting for rating)             │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │ Cash Collected: ○ No  ● Yes                        │ │
│  │ Enter Cash Amount: [2,500 RWF_________________]   │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  [Mark as Complete]  [View Trip Details]              │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Mark as Complete")
         ▼
┌─────────────────────────────────────────────────────────┐
│        Trip Completed - Ready for Next Trip              │
│                                                          │
│  ✓ Trip Complete!                                       │
│                                                          │
│  You've earned 2,000 RWF                               │
│  Total today: 45,000 RWF (8 trips)                     │
│                                                          │
│  New Trip Requests: 2                                   │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  🚗 Request 1                                      │ │
│  │  Sarah Lee ★ 4.6                                  │ │
│  │  Kimihurura → CBD (0.6 km away)                   │ │
│  │  Fare: 2,200 RWF | [Accept]  [Reject]            │ │
│  │                                                     │ │
│  │  🏍️ Request 2                                     │ │
│  │  Michael Brown ★ 4.5                              │ │
│  │  Remera → Kimihurura (1.2 km away)                │ │
│  │  Fare: 1,800 RWF | [Accept]  [Reject]            │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
│  [Back to Dashboard]                                    │
└─────────────────────────────────────────────────────────┘
```

### Flow 3: Earnings & Withdrawal

```
┌─────────────────────────────────────────────────────────┐
│            Driver Earnings Dashboard                     │
│                                                          │
│  💰 Earnings                                             │
│                                                          │
│  Today                        This Month                │
│  45,000 RWF                   890,000 RWF              │
│  8 trips                      287 trips                │
│                                                          │
│  [Day] [Week] [Month] [Year]                           │
│                                                          │
│  Earnings Chart                                         │
│  ╔════════════════════════════════════════════╗        │
│  ║  📊 Line chart showing daily earnings trend ║       │
│  ║     Week: Mon Tue Wed Thu Fri Sat Sun      ║       │
│  ║                                            ║        │
│  ║     Earnings per day range from 35k-50k    ║       │
│  ╚════════════════════════════════════════════╝        │
│                                                          │
│  Account Balance                                        │
│  Total: 125,000 RWF                                    │
│  Pending: 2,500 RWF                                    │
│  Available: 122,500 RWF                                │
│                                                          │
│  [Withdraw Funds]                                       │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Withdraw Funds")
         ▼
┌─────────────────────────────────────────────────────────┐
│           Withdraw Funds Screen                          │
│                                                          │
│  Withdraw Earnings                                      │
│                                                          │
│  Available Balance: 122,500 RWF                         │
│                                                          │
│  Amount to Withdraw:                                    │
│  [100,000 RWF_________________________]                │
│                                                          │
│  Preset Amounts:                                        │
│  [10K]  [25K]  [50K]  [100K]  [All]                   │
│                                                          │
│  Withdrawal Method:                                     │
│  ○ Bank Transfer (2-3 hours)                           │
│  ○ Mobile Money (5-10 minutes)                         │
│  ○ Crypto Wallet                                        │
│                                                          │
│  Bank Account:                                          │
│  Rwanda Development Bank - ****1234                    │
│                                                          │
│  [Cancel]  [Review Withdrawal]                         │
└─────────────────────────────────────────────────────────┘
         │ (Tap "Review Withdrawal")
         ▼
┌─────────────────────────────────────────────────────────┐
│         Withdrawal Confirmation Screen                   │
│                                                          │
│  Confirm Withdrawal                                     │
│                                                          │
│  Amount: 100,000 RWF                                    │
│  Method: Bank Transfer                                 │
│  Destination: Rwanda Development Bank - ****1234       │
│                                                          │
│  Fee: Free                                              │
│  Processing time: 2-3 hours                            │
│                                                          │
│  You will receive:                                      │
│  100,000 RWF                                            │
│                                                          │
│  □ I confirm this withdrawal                            │
│                                                          │
│  [Cancel]  [Confirm & Withdraw]                        │
└─────────────────────────────────────────────────────────┘
         │ (Confirmed)
         ▼
┌─────────────────────────────────────────────────────────┐
│      Withdrawal Request Submitted Screen                 │
│                                                          │
│  ✓ Withdrawal Submitted                                 │
│                                                          │
│  Transaction ID: WITHDRAW-20260504-001                 │
│  Amount: 100,000 RWF                                    │
│  Status: PROCESSING                                     │
│                                                          │
│  Expected Arrival:                                      │
│  May 4, 2026 at 5:30 PM                                │
│                                                          │
│  You'll receive an SMS when funds arrive.              │
│                                                          │
│  New Available Balance: 22,500 RWF                      │
│                                                          │
│  [View Transaction Details]                            │
│  [Back to Earnings]                                     │
└─────────────────────────────────────────────────────────┘
```

---

## Shared Components

### Location Selector

```
┌──────────────────────────────────────────┐
│ [📍 Select Location]                     │
│                                          │
│ [Search box with MRU/favorites]          │
│                                          │
│ ┌──────────────────────────────────────┐ │
│ │  🗺️ Map with user's current location │ │
│ │  (Tap to set custom location)         │ │
│ └──────────────────────────────────────┘ │
│                                          │
│ Recent Locations:                        │
│ • Home - Kimihurura                      │
│ • Work - Business Park                   │
│ • Gym - Health Club                      │
│                                          │
│ Favorite Places:                         │
│ • ⭐ Sister's House                      │
│ • ⭐ Hospital                             │
│                                          │
│                     [Cancel]  [Confirm]  │
└──────────────────────────────────────────┘
```

### Payment Method Selector

```
┌──────────────────────────────────────────┐
│ Select Payment Method                    │
│                                          │
│ ◉ Mobile Money                           │
│   MTN Money / Airtel Money               │
│   Phone: +250788123456                   │
│                                          │
│ ○ Credit/Debit Card                      │
│   Visa/Mastercard saved                  │
│                                          │
│ ○ Wallet                                 │
│   Balance: 15,000 RWF                    │
│   [Add Funds]                            │
│                                          │
│ ○ Cash                                   │
│   Pay driver at end of trip              │
│                                          │
│ [Add New Payment Method]                 │
│                                          │
│                     [Cancel]  [Confirm]  │
└──────────────────────────────────────────┘
```

### Trip Rating Component

```
┌──────────────────────────────────────────┐
│ How was your trip?                       │
│                                          │
│ Overall Rating:                          │
│ ⭐⭐⭐⭐⭐ (5/5)                         │
│                                          │
│ Rate by Category:                        │
│ 🧹 Cleanliness: ⭐⭐⭐⭐⭐ (5)           │
│ 🔒 Safety:      ⭐⭐⭐⭐⭐ (5)           │
│ 💬 Comm:        ⭐⭐⭐⭐⭐ (5)           │
│ 🚗 Driving:     ⭐⭐⭐⭐⭐ (5)           │
│                                          │
│ Comment:                                 │
│ [Great driver!________________         ] │
│                                          │
│ Would recommend? ○ Yes ○ No              │
│                                          │
│                 [Cancel]  [Submit Rating]│
└──────────────────────────────────────────┘
```

### Real-time Tracking Map

```
┌──────────────────────────────────────────┐
│ 🗺️ Live Tracking Map                     │
│                                          │
│ ┌──────────────────────────────────────┐ │
│ │                                       │ │
│ │  🔴 Pickup Location                  │ │
│ │  ───────────                         │ │
│ │  → → → Driver Route                  │ │
│ │  ← ← ← (Realtime updated)            │ │
│ │  ───────────                         │ │
│ │  🔵 Destination                      │ │
│ │                                       │ │
│ │  Zoom: [+] [-]                       │ │
│ │  My Location: [◉]                    │ │
│ │                                       │ │
│ └──────────────────────────────────────┘ │
│                                          │
│ Status: Driver 0.8 km away (3 min)      │
│ ETA to destination: 12 min               │
│                                          │
│ [Full Screen Map]  [Directions]         │
└──────────────────────────────────────────┘
```

### Notifications

```
Realtime Notifications (Push & In-app):

1. Trip Request Alert:
   🔔 "John Doe wants a ride from Kimihurura 
       to CBD - 3 min away [Accept] [Reject]"

2. Driver Arrival:
   🔔 "Jane Smith is 2 minutes away
       Toyota Prius (RAJ123A) [Map] [Call]"

3. Payment Confirmation:
   ✓ "2,500 RWF payment confirmed
     Transaction ID: MTN-20260504-001"

4. Earnings Update:
   💰 "Earned 2,000 RWF from completed trip
      Total today: 45,000 RWF"

5. Document Status:
   📄 "Your driver license has been verified ✓"
```

---

## Screen Specifications

### Typography Scale

```
Headline 1 (Large Title):
  Font: 28-32 sp, Bold (700)
  Line Height: 1.3x
  Use: Main screen titles

Headline 2 (Title):
  Font: 24-26 sp, Bold (600)
  Line Height: 1.3x
  Use: Section titles, card titles

Headline 3 (Subtitle):
  Font: 20-22 sp, Semi-bold (600)
  Line Height: 1.4x
  Use: Trip details, prominent labels

Body Large:
  Font: 16-18 sp, Regular (400)
  Line Height: 1.5x
  Use: Primary text content

Body Medium:
  Font: 14-16 sp, Regular (400)
  Line Height: 1.5x
  Use: Secondary text, descriptions

Body Small:
  Font: 12-14 sp, Regular (400)
  Line Height: 1.5x
  Use: Helper text, captions, metadata

Label:
  Font: 12 sp, Medium (500)
  Line Height: 1.4x
  Use: Button labels, tags
```

### Color Palette

```
Primary Colors:
  Primary: #007AFF (Vibrant Blue)
  Primary Dark: #0051CC (Dark Blue)
  Primary Light: #E3F2FD (Light Blue)

Semantic Colors:
  Success: #34C759 (Green)
  Warning: #FF9500 (Orange)
  Error: #FF3B30 (Red)
  Info: #00B4D8 (Cyan)

Neutral Colors:
  Text Primary: #000000
  Text Secondary: #6B7280
  Text Tertiary: #9CA3AF
  Background: #FFFFFF
  Surface: #F9FAFB
  Border: #E5E7EB
  Disabled: #D1D5DB

Dark Mode:
  Background: #121212
  Surface: #1E1E1E
  Text Primary: #FFFFFF
  Text Secondary: #B3B3B3
```

### Spacing Scale

```
xs:  4px   - Tight spacing between related elements
sm:  8px   - Small gaps
md:  16px  - Default spacing between elements
lg:  24px  - Large section spacing
xl:  32px  - Extra large spacing
2xl: 48px  - Spacing between major sections
```

### Border Radius

```
xs:    4px   - Input fields, small components
sm:    8px   - Cards, buttons
md:    12px  - Large buttons, modals
lg:    16px  - Large cards, bottom sheets
full:  999px - Pills, circular avatars
```

---

## Data Display Patterns

### Trip Card (List View)

```
┌──────────────────────────────────────────┐
│ 🚖 May 4, 2026 • 2:30 PM                 │
│                                          │
│ From: Kimihurura Roundabout              │
│ To:   Kigali City Tower                  │
│                                          │
│ Driver: Jane Smith ⭐ 4.9                │
│ Vehicle: Silver Toyota Prius (RAJ123A)   │
│                                          │
│ 2,500 RWF  •  12 min  •  2.5 km         │
│                                          │
│ Status: ✓ Completed                      │
│                                          │
│                              [View Details]
└──────────────────────────────────────────┘
```

### Empty State

```
┌──────────────────────────────────────────┐
│                                          │
│            ╔════════════╗               │
│            ║     📭     ║               │
│            ╚════════════╝               │
│                                          │
│        No trips yet                      │
│                                          │
│  Start your first journey with           │
│  RideConnect today!                      │
│                                          │
│          [Request a Ride Now]            │
│                                          │
└──────────────────────────────────────────┘
```

### Loading State

```
┌──────────────────────────────────────────┐
│                                          │
│          🔄 Finding Drivers...          │
│                                          │
│      ⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏ (Animated spinner)    │
│                                          │
│   Searching nearby drivers to match      │
│   your request...                        │
│                                          │
│          [Cancel Request]                │
│                                          │
└──────────────────────────────────────────┘
```

### Error State

```
┌──────────────────────────────────────────┐
│                                          │
│            ╔════════════╗               │
│            ║     ⚠️     ║               │
│            ╚════════════╝               │
│                                          │
│        Something went wrong              │
│                                          │
│  Payment failed. Please check your       │
│  payment method and try again.           │
│                                          │
│          [Retry Payment]                 │
│          [Use Different Method]          │
│          [Contact Support]               │
│                                          │
└──────────────────────────────────────────┘
```

---

**Design System Version:** 2.0  
**Last Updated:** May 2026  
**Maintained By:** RideConnect Design Team
