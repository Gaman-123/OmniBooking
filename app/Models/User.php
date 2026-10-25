<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

/**
 * User Model
 * Handles registered guests, staff, and admins.
 * Passwords are stored as bcrypt hashes — never plaintext.
 */
class User
{
    // ─── Fetch ────────────────────────────────────────────────────────────────

    public static function all(int $limit = 100): array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT id, name, email, phone, role, is_active, avatar_url, created_at
            FROM users
            ORDER BY id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT id, name, email, phone, role, is_active, avatar_url, created_at, updated_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT * FROM users WHERE email = ? LIMIT 1
        ");
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(): int
    {
        $stmt = Connection::getInstance()->query("SELECT COUNT(*) as cnt FROM users");
        return (int) ($stmt->fetch()['cnt'] ?? 0);
    }

    // ─── Bookings by User ─────────────────────────────────────────────────────

    public static function bookings(int $userId, int $limit = 20): array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT b.*, rt.name as room_type_name, c.name as channel_name
            FROM bookings b
            LEFT JOIN room_types rt ON b.room_type_id = rt.id
            LEFT JOIN channels c    ON b.channel_id   = c.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * Create a new user.
     * @param array{name: string, email: string, password: string, phone?: string, role?: string} $data
     */
    public static function create(array $data): int
    {
        $db = Connection::getInstance();
        $stmt = $db->prepare("
            INSERT INTO users (name, email, phone, password, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($data['name']),
            strtolower(trim($data['email'])),
            $data['phone']  ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            $data['role']   ?? 'guest',
        ]);
        return (int) $db->lastInsertId();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public static function update(int $id, array $fields): bool
    {
        $allowed = ['name', 'phone', 'avatar_url', 'is_active', 'role'];
        $sets    = [];
        $values  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[]  = "{$col} = ?";
                $values[] = $fields[$col];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $values[] = $id;
        $db  = Connection::getInstance();
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        return $db->prepare($sql)->execute($values);
    }

    public static function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = Connection::getInstance()->prepare("
            UPDATE users SET password = ? WHERE id = ?
        ");
        return $stmt->execute([
            password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            $id,
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    /**
     * Verify a user's plain-text password against their bcrypt hash.
     * Returns the user row on success, null on failure.
     */
    public static function authenticate(string $email, string $password): ?array
    {
        $user = static::findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!$user['is_active']) {
            return null; // Suspended account
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Unset password before returning
        unset($user['password']);
        return $user;
    }

    // ─── Delete / Deactivate ─────────────────────────────────────────────────

    /**
     * Soft-delete: deactivate the user (preserves booking history).
     */
    public static function deactivate(int $id): bool
    {
        return static::update($id, ['is_active' => 0]);
    }

    /**
     * Hard-delete. Only use when no bookings reference the user.
     */
    public static function delete(int $id): bool
    {
        $stmt = Connection::getInstance()->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function emailExists(string $email): bool
    {
        $stmt = Connection::getInstance()->prepare(
            "SELECT COUNT(*) as cnt FROM users WHERE email = ?"
        );
        $stmt->execute([strtolower(trim($email))]);
        return (int) ($stmt->fetch()['cnt'] ?? 0) > 0;
    }
}
