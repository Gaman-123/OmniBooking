<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class Channel
{
    public static function all(): array
    {
        $stmt = Connection::getInstance()->query("SELECT * FROM channels WHERE is_active = TRUE OR is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare("SELECT * FROM channels WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Connection::getInstance()->prepare("SELECT * FROM channels WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $webhookUrl, bool $isActive = true): int
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("INSERT INTO channels (name, webhook_url, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$name, $webhookUrl, $isActive ? 1 : 0]);
        return (int) $db->lastInsertId();
    }
}
