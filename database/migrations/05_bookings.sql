CREATE TABLE IF NOT EXISTS bookings (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(50)    NOT NULL UNIQUE,
    user_id           INT            NULL,                         -- Registered user (nullable = walk-in / OTA)
    guest_name        VARCHAR(150)   NULL,
    guest_email       VARCHAR(255)   NULL,
    room_type_id      INT            NOT NULL,
    channel_id        INT            NULL,
    check_in          DATE           NOT NULL,
    check_out         DATE           NOT NULL,
    nights            TINYINT        GENERATED ALWAYS AS (DATEDIFF(check_out, check_in)) STORED,
    total_price       DECIMAL(10,2)  NOT NULL,
    status            VARCHAR(20)    NOT NULL DEFAULT 'confirmed',
    special_requests  TEXT           NULL,
    created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_user      FOREIGN KEY (user_id)      REFERENCES users(id)      ON DELETE SET NULL,
    CONSTRAINT fk_bookings_room_type FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_channel   FOREIGN KEY (channel_id)   REFERENCES channels(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Composite index for date-range availability queries
CREATE INDEX IF NOT EXISTS idx_bookings_dates      ON bookings (check_in, check_out);
CREATE INDEX IF NOT EXISTS idx_bookings_user       ON bookings (user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_room_type  ON bookings (room_type_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status     ON bookings (status);
