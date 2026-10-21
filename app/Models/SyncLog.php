<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class SyncLog
{
    public static function all(int $limit = 50): array
    {
        $stmt = Connection::getInstance()->prepare("
            SELECT sl.*, c.name as channel_name, b.booking_reference
            FROM sync_logs sl
            LEFT JOIN channels c ON sl.channel_id = c.id
            LEFT JOIN bookings b ON sl.booking_id = b.id
            ORDER BY sl.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            if (isset($row['payload']) && is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['payload'] = $decoded;
                }
            }
        }

        return $rows;
    }

    public static function record(int $bookingId, int $channelId, array $payload, int $responseStatus, int $executionTimeMs): int
    {
        $db = Connection::getInstance();
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $stmt = $db->prepare("
            INSERT INTO sync_logs (booking_id, channel_id, payload, response_status, execution_time_ms)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bookingId,
            $channelId,
            $payloadJson,
            $responseStatus,
            $executionTimeMs
        ]);
        return (int) $db->lastInsertId();
    }
}
