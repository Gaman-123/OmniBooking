<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class RoomType
{
    public static function all(): array
    {
        $stmt = Connection::getInstance()->query("
            SELECT rt.*, h.name as hotel_name 
            FROM room_types rt
            LEFT JOIN hotels h ON rt.hotel_id = h.id
            ORDER BY rt.id ASC
        ");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare("SELECT * FROM room_types WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $hotelId, string $name, int $totalUnits, float $basePrice): int
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            INSERT INTO room_types (hotel_id, name, total_units, base_price) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$hotelId, $name, $totalUnits, $basePrice]);
        return (int) $db->lastInsertId();
    }
}
