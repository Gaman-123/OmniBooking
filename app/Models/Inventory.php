<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Inventory
{
    /**
     * Get inventory matrix for room types within a date range
     */
    public static function getMatrix(string $startDate, string $endDate): array
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            SELECT i.*, rt.name as room_type_name, rt.total_units, rt.base_price
            FROM inventories i
            JOIN room_types rt ON i.room_type_id = rt.id
            WHERE i.date >= ? AND i.date <= ?
            ORDER BY rt.id ASC, i.date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Check if a room type has enough inventory across an entire date range
     */
    public static function checkAvailability(int $roomTypeId, string $checkIn, string $checkOut, int $roomsCount = 1): array
    {
        $db = Connection::getInstance();
        
        // Find minimum available rooms between check_in and check_out (exclusive of checkout day)
        $stmt = $db->prepare("
            SELECT MIN(available_rooms) as min_available, 
                   COUNT(*) as days_recorded,
                   SUM(price) as total_price,
                   AVG(price) as avg_price
            FROM inventories
            WHERE room_type_id = ? 
              AND date >= ? 
              AND date < ?
        ");
        $stmt->execute([$roomTypeId, $checkIn, $checkOut]);
        $row = $stmt->fetch();

        $minAvailable = isset($row['min_available']) ? (int) $row['min_available'] : 0;
        $isAvailable = ($minAvailable >= $roomsCount);

        return [
            'is_available' => $isAvailable,
            'min_available' => $minAvailable,
            'requested_rooms' => $roomsCount,
            'total_price' => (float) ($row['total_price'] ?? 0) * $roomsCount,
            'avg_nightly_price' => (float) ($row['avg_price'] ?? 0),
            'days_count' => (int) ($row['days_recorded'] ?? 0)
        ];
    }

    /**
     * Atomically decrement available rooms across date interval
     */
    public static function decrementRange(int $roomTypeId, string $checkIn, string $checkOut, int $roomsCount = 1): bool
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            UPDATE inventories
            SET available_rooms = available_rooms - ?
            WHERE room_type_id = ?
              AND date >= ?
              AND date < ?
              AND available_rooms >= ?
        ");
        $stmt->execute([$roomsCount, $roomTypeId, $checkIn, $checkOut, $roomsCount]);

        $days = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
        $expectedUpdates = max(1, $days);
        return $stmt->rowCount() >= $expectedUpdates;
    }

    /**
     * Upsert single date inventory
     */
    public static function upsert(int $roomTypeId, string $date, int $availableRooms, float $price): void
    {
        $db = Connection::getInstance();
        $driver = Connection::getActiveDriver();

        if ($driver === 'mysql') {
            $sql = "
                INSERT INTO inventories (room_type_id, date, available_rooms, price)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE available_rooms = VALUES(available_rooms), price = VALUES(price);
            ";
        } else if ($driver === 'pgsql') {
            $sql = "
                INSERT INTO inventories (room_type_id, date, available_rooms, price)
                VALUES (?, ?, ?, ?)
                ON CONFLICT (room_type_id, date) 
                DO UPDATE SET available_rooms = EXCLUDED.available_rooms, price = EXCLUDED.price;
            ";
        } else {
            $sql = "
                INSERT INTO inventories (room_type_id, date, available_rooms, price)
                VALUES (?, ?, ?, ?)
                ON CONFLICT(room_type_id, date)
                DO UPDATE SET available_rooms = excluded.available_rooms, price = excluded.price;
            ";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$roomTypeId, $date, $availableRooms, $price]);
    }
}
