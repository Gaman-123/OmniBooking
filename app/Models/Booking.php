<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Booking
{
    public static function all(int $limit = 50): array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT b.*, rt.name as room_type_name, c.name as channel_name
            FROM bookings b
            LEFT JOIN room_types rt ON b.room_type_id = rt.id
            LEFT JOIN channels c ON b.channel_id = c.id
            ORDER BY b.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT b.*, rt.name as room_type_name, c.name as channel_name
            FROM bookings b
            LEFT JOIN room_types rt ON b.room_type_id = rt.id
            LEFT JOIN channels c ON b.channel_id = c.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByReference(string $ref): ?array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT b.*, rt.name as room_type_name, c.name as channel_name
            FROM bookings b
            LEFT JOIN room_types rt ON b.room_type_id = rt.id
            LEFT JOIN channels c ON b.channel_id = c.id
            WHERE b.booking_reference = ?
        ");
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(): int
    {
        $stmt = Connection::getInstance()->query("SELECT COUNT(*) as cnt FROM bookings");
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public static function create(array $data): int
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            INSERT INTO bookings (
                booking_reference, guest_name, room_type_id, channel_id, 
                check_in, check_out, total_price, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['booking_reference'],
            $data['guest_name'] ?? 'Guest',
            $data['room_type_id'],
            $data['channel_id'] ?? null,
            $data['check_in'],
            $data['check_out'],
            $data['total_price'],
            $data['status'] ?? 'confirmed'
        ]);
        return (int) $db->lastInsertId();
    }
}
