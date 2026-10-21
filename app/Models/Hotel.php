<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Hotel
{
    public static function all(): array
    {
        $stmt = Connection::getInstance()->query("SELECT * FROM hotels ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare("SELECT * FROM hotels WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, ?string $address = null): int
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("INSERT INTO hotels (name, address) VALUES (?, ?)");
        $stmt->execute([$name, $address]);
        return (int) $db->lastInsertId();
    }
}
