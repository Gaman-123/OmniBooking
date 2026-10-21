-- ==============================================================================
-- Omni Booking - Neon SQL (Serverless PostgreSQL) Complete Schema
-- ==============================================================================

-- 1. Hotels Table
CREATE TABLE IF NOT EXISTS hotels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Room Types Table
CREATE TABLE IF NOT EXISTS room_types (
    id SERIAL PRIMARY KEY,
    hotel_id INT REFERENCES hotels(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    total_units INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Inventory Table (with Compound Unique Index for O(1) Lookups)
CREATE TABLE IF NOT EXISTS inventories (
    id SERIAL PRIMARY KEY,
    room_type_id INT REFERENCES room_types(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    available_rooms INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(room_type_id, date)
);

CREATE INDEX IF NOT EXISTS idx_inventories_date_lookup 
ON inventories(room_type_id, date);

-- 4. OTA Channels Table
CREATE TABLE IF NOT EXISTS channels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    webhook_url TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id SERIAL PRIMARY KEY,
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    guest_name VARCHAR(150),
    room_type_id INT REFERENCES room_types(id),
    channel_id INT REFERENCES channels(id),
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. External OTA Sync Audit Logs (JSONB for Structured Webhook Payloads)
CREATE TABLE IF NOT EXISTS sync_logs (
    id SERIAL PRIMARY KEY,
    booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
    channel_id INT REFERENCES channels(id) ON DELETE CASCADE,
    payload JSONB,
    response_status INT,
    execution_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
