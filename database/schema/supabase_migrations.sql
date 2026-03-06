-- RideConnect Database Schema for Supabase PostgreSQL
-- Generated for Supabase project: svkhumefxncdtoellmns
-- Database: postgres
-- Host: aws-1-us-east-1.pooler.supabase.com

-- =====================================================
-- MIGRATION 1: drivers table
-- =====================================================
CREATE TABLE IF NOT EXISTS drivers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    license_number VARCHAR(255) NOT NULL UNIQUE,
    license_plate VARCHAR(255) NOT NULL UNIQUE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'suspended')),
    total_rides INTEGER DEFAULT 0,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    rating_count INTEGER DEFAULT 0,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    approved_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP(0) WITH TIME ZONE NULL,
    CONSTRAINT fk_drivers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_drivers_user_id ON drivers(user_id);
CREATE INDEX IF NOT EXISTS idx_drivers_status ON drivers(status);

-- =====================================================
-- MIGRATION 2: vehicles table
-- =====================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL,
    make VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INTEGER NOT NULL,
    color VARCHAR(50) NOT NULL,
    vehicle_type VARCHAR(20) NOT NULL CHECK (vehicle_type IN ('sedan', 'suv', 'hatchback', 'van', 'motorcycle', 'compact')),
    seats INTEGER DEFAULT 4,
    air_conditioning BOOLEAN DEFAULT true,
    is_active BOOLEAN DEFAULT true,
    photo_url TEXT NULL,
    verified_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicles_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_vehicles_driver_id ON vehicles(driver_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_is_active ON vehicles(is_active);

-- =====================================================
-- MIGRATION 3: rides table
-- =====================================================
CREATE TABLE IF NOT EXISTS rides (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL,
    vehicle_id BIGINT NOT NULL,
    origin_address TEXT NOT NULL,
    origin_lat DECIMAL(10, 8) NOT NULL,
    origin_lng DECIMAL(11, 8) NOT NULL,
    destination_address TEXT NOT NULL,
    destination_lat DECIMAL(10, 8) NOT NULL,
    destination_lng DECIMAL(11, 8) NOT NULL,
    departure_time TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    arrival_time_estimated TIMESTAMP(0) WITH TIME ZONE NULL,
    available_seats INTEGER NOT NULL,
    price_per_seat DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RWF',
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled')),
    ride_type VARCHAR(20) DEFAULT 'one-way' CHECK (ride_type IN ('one-way', 'round-trip')),
    luggage_allowed BOOLEAN DEFAULT true,
    pets_allowed BOOLEAN DEFAULT false,
    smoking_allowed BOOLEAN DEFAULT false,
    cancelled_at TIMESTAMP(0) WITH TIME ZONE NULL,
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rides_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_rides_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rides_driver_id ON rides(driver_id);
CREATE INDEX IF NOT EXISTS idx_rides_vehicle_id ON rides(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_rides_status ON rides(status);
CREATE INDEX IF NOT EXISTS idx_rides_departure_time ON rides(departure_time);

-- =====================================================
-- MIGRATION 4: bookings table
-- =====================================================
CREATE TABLE IF NOT EXISTS bookings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    ride_id BIGINT NOT NULL,
    seats_booked INTEGER DEFAULT 1,
    total_price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RWF',
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed', 'no_show')),
    pickup_address TEXT NULL,
    pickup_lat DECIMAL(10, 8) NULL,
    pickup_lng DECIMAL(11, 8) NULL,
    dropoff_address TEXT NULL,
    dropoff_lat DECIMAL(10, 8) NULL,
    dropoff_lng DECIMAL(11, 8) NULL,
    special_requests TEXT NULL,
    confirmed_at TIMESTAMP(0) WITH TIME ZONE NULL,
    cancelled_at TIMESTAMP(0) WITH TIME ZONE NULL,
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bookings_user_id ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_ride_id ON bookings(ride_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);

-- =====================================================
-- MIGRATION 5: payments table
-- =====================================================
CREATE TABLE IF NOT EXISTS payments (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    platform_fee DECIMAL(10, 2) DEFAULT 0.00,
    driver_amount DECIMAL(10, 2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'RWF',
    payment_method VARCHAR(50) NULL,
    transaction_id VARCHAR(255) NULL UNIQUE,
    supabase_payment_id VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'refunded')),
    payment_details TEXT NULL,
    paid_at TIMESTAMP(0) WITH TIME ZONE NULL,
    refunded_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payments_booking_id ON payments(booking_id);
CREATE INDEX IF NOT EXISTS idx_payments_user_id ON payments(user_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
CREATE INDEX IF NOT EXISTS idx_payments_transaction_id ON payments(transaction_id);

-- =====================================================
-- MIGRATION 6: reviews table
-- =====================================================
CREATE TABLE IF NOT EXISTS reviews (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    driver_id BIGINT NOT NULL,
    ride_id BIGINT NOT NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    safety_rating INTEGER NULL CHECK (safety_rating >= 1 AND safety_rating <= 5),
    punctuality_rating INTEGER NULL CHECK (punctuality_rating >= 1 AND punctuality_rating <= 5),
    communication_rating INTEGER NULL CHECK (communication_rating >= 1 AND communication_rating <= 5),
    vehicle_condition_rating INTEGER NULL CHECK (vehicle_condition_rating >= 1 AND vehicle_condition_rating <= 5),
    reviewer_type VARCHAR(20) DEFAULT 'passenger' CHECK (reviewer_type IN ('passenger', 'driver')),
    is_public BOOLEAN DEFAULT true,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_ride FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_reviews_booking_id ON reviews(booking_id);
CREATE INDEX IF NOT EXISTS idx_reviews_user_id ON reviews(user_id);
CREATE INDEX IF NOT EXISTS idx_reviews_driver_id ON reviews(driver_id);
CREATE INDEX IF NOT EXISTS idx_reviews_ride_id ON reviews(ride_id);

-- =====================================================
-- MIGRATION 7: notifications table
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSONB NULL,
    is_read BOOLEAN DEFAULT false,
    read_at TIMESTAMP(0) WITH TIME ZONE NULL,
    expires_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);

-- =====================================================
-- Add updated_at trigger function for all tables
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to all tables
DO $$
DECLARE
    table_names TEXT[] := ARRAY['drivers', 'vehicles', 'rides', 'bookings', 'payments', 'reviews', 'notifications'];
    t TEXT;
BEGIN
    FOREACH t IN ARRAY table_names
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS update_%I_updated_at ON %I', t, t);
        EXECUTE format('CREATE TRIGGER update_%I_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()', t, t);
    END LOOP;
END;
$$;

-- =====================================================
-- SEED DATA
-- =====================================================

-- Insert seed users (if not exists)
INSERT INTO users (name, email, email_verified_at, password, remember_token, created_at, updated_at)
VALUES 
    ('Admin User', 'admin@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('John Driver', 'john.driver@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Sarah Driver', 'sarah.driver@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Mike Driver', 'mike.driver@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Alice Passenger', 'alice.passenger@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Bob Passenger', 'bob.passenger@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Carol Passenger', 'carol.passenger@rideconnect.com', CURRENT_TIMESTAMP, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (email) DO NOTHING;

-- Insert seed drivers
INSERT INTO drivers (user_id, license_number, license_plate, status, total_rides, rating, rating_count, balance, approved_at, created_at, updated_at)
VALUES 
    (2, 'DL-2024-001', 'RAC-123-A', 'approved', 45, 4.80, 38, 250.00, CURRENT_TIMESTAMP - INTERVAL '30 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 'DL-2024-002', 'RAC-456-B', 'approved', 72, 4.92, 65, 480.50, CURRENT_TIMESTAMP - INTERVAL '60 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (4, 'DL-2024-003', 'RAC-789-C', 'approved', 28, 4.65, 22, 150.00, CURRENT_TIMESTAMP - INTERVAL '15 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (license_number) DO NOTHING;

-- Insert seed vehicles
INSERT INTO vehicles (driver_id, make, model, year, color, vehicle_type, seats, air_conditioning, is_active, verified_at, created_at, updated_at)
VALUES 
    (1, 'Toyota', 'Camry', 2022, 'Silver', 'sedan', 4, true, true, CURRENT_TIMESTAMP - INTERVAL '25 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 'Honda', 'CR-V', 2023, 'White', 'suv', 5, true, true, CURRENT_TIMESTAMP - INTERVAL '55 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 'Toyota', 'Corolla', 2021, 'Blue', 'compact', 4, true, true, CURRENT_TIMESTAMP - INTERVAL '10 days', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- Insert seed rides
INSERT INTO rides (driver_id, vehicle_id, origin_address, origin_lat, origin_lng, destination_address, destination_lat, destination_lng, departure_time, arrival_time_estimated, available_seats, price_per_seat, currency, description, status, ride_type, luggage_allowed, pets_allowed, smoking_allowed, created_at, updated_at)
VALUES 
    (1, 1, 'Kigali City Center, Rwanda', -1.9706, 30.0444, 'Musanze, Rwanda', -1.4995, 29.6333, CURRENT_TIMESTAMP + INTERVAL '2 days 8 hours', CURRENT_TIMESTAMP + INTERVAL '2 days 11 hours 30 minutes', 3, 25.00, 'RWF', 'Comfortable ride to Musanze. Stops at Nyabihu junction.', 'scheduled', 'one-way', true, false, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 2, 'Kigali International Airport, Rwanda', -1.9686, 30.1394, 'Huye, Rwanda', -2.5969, 29.5944, CURRENT_TIMESTAMP + INTERVAL '3 days 14 hours', CURRENT_TIMESTAMP + INTERVAL '3 days 18 hours 30 minutes', 2, 35.00, 'RWF', 'Airport transfer to Huye. Quick and safe journey.', 'scheduled', 'one-way', true, false, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 3, 'Rubavu, Rwanda', -1.6833, 29.2667, 'Kigali City Center, Rwanda', -1.9706, 30.0444, CURRENT_TIMESTAMP + INTERVAL '1 day 7 hours', CURRENT_TIMESTAMP + INTERVAL '1 day 11 hours', 3, 20.00, 'RWF', 'Morning ride from Rubavu to Kigali. Lake Kivu view on the way.', 'scheduled', 'one-way', true, true, false, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- Insert seed bookings
INSERT INTO bookings (user_id, ride_id, seats_booked, total_price, currency, status, pickup_address, pickup_lat, pickup_lng, dropoff_address, dropoff_lat, dropoff_lng, special_requests, confirmed_at, created_at, updated_at)
VALUES 
    (5, 1, 2, 50.00, 'RWF', 'confirmed', 'Kigali Heights, Kigali', -1.9721, 30.0578, 'Musanze Bus Park', -1.4995, 29.6333, 'We have two suitcases.', CURRENT_TIMESTAMP - INTERVAL '1 day', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (6, 2, 1, 35.00, 'RWF', 'pending', 'Kigali Marriott Hotel', -1.9744, 30.0978, 'Huye University', -2.5969, 29.5944, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (7, 3, 1, 20.00, 'RWF', 'confirmed', 'Rubavu Market', -1.6833, 29.2667, 'Kigali Convention Centre', -1.9692, 30.0878, 'Traveling with a small dog.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- Insert seed payments
INSERT INTO payments (booking_id, user_id, amount, platform_fee, driver_amount, currency, payment_method, transaction_id, status, payment_details, paid_at, created_at, updated_at)
VALUES 
    (1, 5, 50.00, 5.00, 45.00, 'RWF', 'mobile_money', 'TXN-' || md5(random()::text), 'completed', '{"provider": "MTN Mobile Money", "phone": "+250788000001"}', CURRENT_TIMESTAMP - INTERVAL '6 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 6, 35.00, 3.50, 31.50, 'RWF', 'card', 'TXN-' || md5(random()::text), 'pending', '{"card_last4": "4242", "brand": "Visa"}', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 7, 20.00, 2.00, 18.00, 'RWF', 'mobile_money', 'TXN-' || md5(random()::text), 'completed', '{"provider": "Airtel Money", "phone": "+250782000001"}', CURRENT_TIMESTAMP - INTERVAL '2 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- Insert seed reviews
INSERT INTO reviews (booking_id, user_id, driver_id, ride_id, rating, comment, safety_rating, punctuality_rating, communication_rating, vehicle_condition_rating, reviewer_type, is_public, created_at, updated_at)
VALUES 
    (1, 5, 1, 1, 5, 'Excellent driver! Very professional and friendly. The ride was smooth and on time.', 5, 5, 5, 5, 'passenger', true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 7, 3, 3, 4, 'Good ride overall. Driver was friendly and the vehicle was comfortable.', 4, 4, 5, 4, 'passenger', true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- Insert seed notifications
INSERT INTO notifications (user_id, type, title, message, data, is_read, read_at, created_at, updated_at)
VALUES 
    (5, 'booking_confirmed', 'Booking Confirmed', 'Your booking for Kigali to Musanze has been confirmed.', '{"booking_id": 1, "ride_id": 1}', true, CURRENT_TIMESTAMP - INTERVAL '12 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (5, 'payment_received', 'Payment Successful', 'Your payment of $50.00 has been processed successfully.', '{"payment_id": 1, "amount": 50.00}', true, CURRENT_TIMESTAMP - INTERVAL '6 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (6, 'booking_pending', 'Booking Pending', 'Your booking for Kigali Airport to Huye is pending confirmation.', '{"booking_id": 2, "ride_id": 2}', false, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 'new_booking', 'New Booking Request', 'You have a new booking request for your ride to Musanze.', '{"booking_id": 1, "passenger_id": 5}', true, CURRENT_TIMESTAMP - INTERVAL '1 day', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 'review_received', 'New Review', 'You received a 5-star review from a passenger!', '{"review_id": 1, "rating": 5}', true, CURRENT_TIMESTAMP - INTERVAL '5 hours', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (7, 'ride_reminder', 'Upcoming Ride', 'Reminder: Your ride from Rubavu to Kigali departs tomorrow at 7:00 AM.', '{"ride_id": 3}', false, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT DO NOTHING;

-- =====================================================
-- Summary
-- =====================================================
SELECT 'Database setup complete!' AS status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_drivers FROM drivers;
SELECT COUNT(*) AS total_vehicles FROM vehicles;
SELECT COUNT(*) AS total_rides FROM rides;
SELECT COUNT(*) AS total_bookings FROM bookings;
SELECT COUNT(*) AS total_payments FROM payments;
SELECT COUNT(*) AS total_reviews FROM reviews;
SELECT COUNT(*) AS total_notifications FROM notifications;
