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

        if ($this->driver === 'pgsql') {
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

        foreach ($tables as $table) {
            try {
                $this->db->exec("DROP TABLE IF EXISTS {$table} CASCADE;");
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

        return $logs;
    }
}
