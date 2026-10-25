<?php

namespace App\Database;

use PDO;
use Exception;

class Migrator
{
    private PDO $db;
    private string $driver;

    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->driver = Connection::getActiveDriver();
    }

    /**
     * Run all migrations
     */
    public function migrate(): array
    {
        $logs = [];

        if ($this->driver === 'mysql') {
            // Native MySQL (InnoDB) DDL
            $queries = [
                'hotels' => "
                    CREATE TABLE IF NOT EXISTS hotels (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        address VARCHAR(255) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'room_types' => "
                    CREATE TABLE IF NOT EXISTS room_types (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        hotel_id INT NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        total_units INT NOT NULL,
                        base_price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_room_types_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'inventories' => "
                    CREATE TABLE IF NOT EXISTS inventories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        room_type_id INT NOT NULL,
                        date DATE NOT NULL,
                        available_rooms INT NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_room_date (room_type_id, date),
                        INDEX idx_inventories_date_lookup (room_type_id, date),
                        CONSTRAINT fk_inventories_room_type FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'channels' => "
                    CREATE TABLE IF NOT EXISTS channels (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        webhook_url TEXT NOT NULL,
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'bookings' => "
                    CREATE TABLE IF NOT EXISTS bookings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        booking_reference VARCHAR(50) NOT NULL UNIQUE,
                        guest_name VARCHAR(150) NULL,
                        room_type_id INT NOT NULL,
                        channel_id INT NULL,
                        check_in DATE NOT NULL,
                        check_out DATE NOT NULL,
                        total_price DECIMAL(10,2) NOT NULL,
                        status VARCHAR(20) DEFAULT 'confirmed',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_bookings_room_type FOREIGN KEY (room_type_id) REFERENCES room_types(id),
                        CONSTRAINT fk_bookings_channel FOREIGN KEY (channel_id) REFERENCES channels(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'sync_logs' => "
                    CREATE TABLE IF NOT EXISTS sync_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        booking_id INT NOT NULL,
                        channel_id INT NOT NULL,
                        payload JSON NULL,
                        response_status INT NULL,
                        execution_time_ms INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_sync_logs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                        CONSTRAINT fk_sync_logs_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            ];
        } else if ($this->driver === 'pgsql') {
            // Native Neon Serverless PostgreSQL DDL
            $queries = [
                'hotels' => "
                    CREATE TABLE IF NOT EXISTS hotels (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        address VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );",
                'room_types' => "
                    CREATE TABLE IF NOT EXISTS room_types (
                        id SERIAL PRIMARY KEY,
                        hotel_id INT REFERENCES hotels(id) ON DELETE CASCADE,
                        name VARCHAR(255) NOT NULL,
                        total_units INT NOT NULL,
                        base_price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );",
                'inventories' => "
                    CREATE TABLE IF NOT EXISTS inventories (
                        id SERIAL PRIMARY KEY,
                        room_type_id INT REFERENCES room_types(id) ON DELETE CASCADE,
                        date DATE NOT NULL,
                        available_rooms INT NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(room_type_id, date)
                    );
                    CREATE INDEX IF NOT EXISTS idx_inventories_date_lookup ON inventories(room_type_id, date);",
                'channels' => "
                    CREATE TABLE IF NOT EXISTS channels (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        webhook_url TEXT NOT NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );",
                'bookings' => "
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
                    );",
                'sync_logs' => "
                    CREATE TABLE IF NOT EXISTS sync_logs (
                        id SERIAL PRIMARY KEY,
                        booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
                        channel_id INT REFERENCES channels(id) ON DELETE CASCADE,
                        payload JSONB,
                        response_status INT,
                        execution_time_ms INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );"
            ];
        } else {
            // SQLite schema
            $queries = [
                'hotels' => "
                    CREATE TABLE IF NOT EXISTS hotels (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name VARCHAR(255) NOT NULL,
                        address VARCHAR(255),
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );",
                'room_types' => "
                    CREATE TABLE IF NOT EXISTS room_types (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        hotel_id INTEGER REFERENCES hotels(id) ON DELETE CASCADE,
                        name VARCHAR(255) NOT NULL,
                        total_units INTEGER NOT NULL,
                        base_price NUMERIC(10,2) NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );",
                'inventories' => "
                    CREATE TABLE IF NOT EXISTS inventories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        room_type_id INTEGER REFERENCES room_types(id) ON DELETE CASCADE,
                        date DATE NOT NULL,
                        available_rooms INTEGER NOT NULL,
                        price NUMERIC(10,2) NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(room_type_id, date)
                    );
                    CREATE INDEX IF NOT EXISTS idx_inventories_date_lookup ON inventories(room_type_id, date);",
                'channels' => "
                    CREATE TABLE IF NOT EXISTS channels (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name VARCHAR(100) NOT NULL,
                        webhook_url TEXT NOT NULL,
                        is_active BOOLEAN DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );",
                'bookings' => "
                    CREATE TABLE IF NOT EXISTS bookings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        booking_reference VARCHAR(50) UNIQUE NOT NULL,
                        guest_name VARCHAR(150),
                        room_type_id INTEGER REFERENCES room_types(id),
                        channel_id INTEGER REFERENCES channels(id),
                        check_in DATE NOT NULL,
                        check_out DATE NOT NULL,
                        total_price NUMERIC(10,2) NOT NULL,
                        status VARCHAR(20) DEFAULT 'confirmed',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );",
                'sync_logs' => "
                    CREATE TABLE IF NOT EXISTS sync_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        booking_id INTEGER REFERENCES bookings(id) ON DELETE CASCADE,
                        channel_id INTEGER REFERENCES channels(id) ON DELETE CASCADE,
                        payload TEXT,
                        response_status INTEGER,
                        execution_time_ms INTEGER,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );"
            ];
        }

        foreach ($queries as $table => $sql) {
            try {
                $this->db->exec($sql);
                $logs[] = "Table '{$table}' migrated successfully on {$this->driver}.";
            } catch (Exception $e) {
                $logs[] = "Error migrating '{$table}': " . $e->getMessage();
            }
        }

        return $logs;
    }

    /**
     * Reset / drop all tables
     */
    public function reset(): array
    {
        $tables = ['sync_logs', 'bookings', 'inventories', 'channels', 'room_types', 'hotels'];
        $logs = [];

        if ($this->driver === 'mysql') {
            try {
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            } catch (Exception $e) {
                // ignore
            }
        }

        foreach ($tables as $table) {
            try {
                if ($this->driver === 'pgsql') {
                    $this->db->exec("DROP TABLE IF EXISTS {$table} CASCADE;");
                } else {
                    $this->db->exec("DROP TABLE IF EXISTS `{$table}`;");
                }
                $logs[] = "Dropped {$table}";
            } catch (Exception $e) {
                try {
                    $this->db->exec("DROP TABLE IF EXISTS {$table};");
                    $logs[] = "Dropped {$table}";
                } catch (Exception $e2) {
                    $logs[] = "Error dropping {$table}: " . $e2->getMessage();
                }
            }
        }

        if ($this->driver === 'mysql') {
            try {
                $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            } catch (Exception $e) {
                // ignore
            }
        }

        return $logs;
    }
}
