-- RideConnect Database Schema for Supabase PostgreSQL
-- Complete migration for all tables

-- =====================================================
-- MIGRATION 1: users table (Laravel default)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP(0) WITH TIME ZONE NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- =====================================================
-- MIGRATION 2: drivers table
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
-- MIGRATION 3: vehicles table
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
-- MIGRATION 4: rides table
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
-- MIGRATION 5: bookings table
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
-- MIGRATION 6: payments table
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
-- MIGRATION 7: reviews table
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
-- MIGRATION 8: notifications table
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
-- MIGRATION 9: mobile_users table
-- =====================================================
CREATE TABLE IF NOT EXISTS mobile_users (
    id BIGSERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('DRIVER', 'PASSENGER')),
    profile_photo TEXT NULL,
    is_verified BOOLEAN DEFAULT false,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_mobile_users_email ON mobile_users(email);
CREATE INDEX IF NOT EXISTS idx_mobile_users_role ON mobile_users(role);

-- =====================================================
-- MIGRATION 10: managers table
-- =====================================================
CREATE TABLE IF NOT EXISTS managers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('SUPER_ADMIN', 'ADMIN', 'OFFICER', 'ACCOUNTANT')),
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_managers_email ON managers(email);

-- =====================================================
-- MIGRATION 11: vehicles_v2 table
-- =====================================================
CREATE TABLE IF NOT EXISTS vehicles_v2 (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    color VARCHAR(30) NOT NULL,
    capacity INTEGER NOT NULL DEFAULT 4,
    status VARCHAR(20) DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE', 'INACTIVE', 'SUSPENDED')),
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicles_v2_driver FOREIGN KEY (driver_id) REFERENCES mobile_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_vehicles_v2_driver_id ON vehicles_v2(driver_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_v2_status ON vehicles_v2(status);

-- =====================================================
-- MIGRATION 12: trips table
-- =====================================================
CREATE TABLE IF NOT EXISTS trips (
    id BIGSERIAL PRIMARY KEY,
    passenger_id BIGINT NOT NULL,
    driver_id BIGINT NULL,
    pickup_location TEXT NOT NULL,
    dropoff_location TEXT NOT NULL,
    pickup_lat DECIMAL(10, 7) NOT NULL,
    pickup_lng DECIMAL(10, 7) NOT NULL,
    dropoff_lat DECIMAL(10, 7) NOT NULL,
    dropoff_lng DECIMAL(10, 7) NOT NULL,
    fare DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED')),
    requested_at TIMESTAMP(0) WITH TIME ZONE NULL,
    started_at TIMESTAMP(0) WITH TIME ZONE NULL,
    completed_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trips_passenger FOREIGN KEY (passenger_id) REFERENCES mobile_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_trips_driver FOREIGN KEY (driver_id) REFERENCES mobile_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_trips_passenger_id ON trips(passenger_id);
CREATE INDEX IF NOT EXISTS idx_trips_driver_id ON trips(driver_id);
CREATE INDEX IF NOT EXISTS idx_trips_status ON trips(status);

-- =====================================================
-- MIGRATION 13: payments_v2 table
-- =====================================================
CREATE TABLE IF NOT EXISTS payments_v2 (
    id BIGSERIAL PRIMARY KEY,
    trip_id BIGINT NOT NULL,
    passenger_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING' CHECK (status IN ('PENDING', 'PAID', 'FAILED', 'REFUNDED')),
    transaction_reference VARCHAR(255) NULL,
    paid_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_v2_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_v2_passenger FOREIGN KEY (passenger_id) REFERENCES mobile_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_payments_v2_trip_id ON payments_v2(trip_id);
CREATE INDEX IF NOT EXISTS idx_payments_v2_passenger_id ON payments_v2(passenger_id);
CREATE INDEX IF NOT EXISTS idx_payments_v2_status ON payments_v2(status);

-- =====================================================
-- MIGRATION 14: driver_earnings table
-- =====================================================
CREATE TABLE IF NOT EXISTS driver_earnings (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL,
    trip_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    commission DECIMAL(10, 2) NOT NULL,
    net_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_driver_earnings_driver FOREIGN KEY (driver_id) REFERENCES mobile_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_driver_earnings_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_driver_earnings_driver_id ON driver_earnings(driver_id);
CREATE INDEX IF NOT EXISTS idx_driver_earnings_trip_id ON driver_earnings(trip_id);

-- =====================================================
-- MIGRATION 15: tickets table
-- =====================================================
CREATE TABLE IF NOT EXISTS tickets (
    id BIGSERIAL PRIMARY KEY,
    trip_id BIGINT NULL,
    issued_by BIGINT NOT NULL,
    reason TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    issued_at TIMESTAMP(0) WITH TIME ZONE NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_issued_by FOREIGN KEY (issued_by) REFERENCES managers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tickets_trip_id ON tickets(trip_id);
CREATE INDEX IF NOT EXISTS idx_tickets_issued_by ON tickets(issued_by);

-- =====================================================
-- MIGRATION 16: activity_logs table
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGSERIAL PRIMARY KEY,
    manager_id BIGINT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_manager FOREIGN KEY (manager_id) REFERENCES managers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_activity_logs_manager_id ON activity_logs(manager_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON activity_logs(action);

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
    table_names TEXT[] := ARRAY['users', 'drivers', 'vehicles', 'rides', 'bookings', 'payments', 'reviews', 'notifications', 'mobile_users', 'managers', 'vehicles_v2', 'trips', 'payments_v2', 'driver_earnings', 'tickets', 'activity_logs'];
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
-- Enable UUID extension
-- =====================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

SELECT 'All tables created successfully!' AS status;
