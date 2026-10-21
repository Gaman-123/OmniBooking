<?php

namespace App\Database;

use PDO;
use PDOException;
use Exception;

class Connection
{
    private static ?PDO $instance = null;
    private static string $activeDriver = 'pgsql';
    private static ?string $lastError = null;

    /**
     * Get or initialize the singleton PDO connection
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Establish the connection, prioritizing Neon PostgreSQL with automatic fallback
     */
    private static function connect(): void
    {
        // 1. Try Neon PostgreSQL
        $dbUrl = getenv('DATABASE_URL');
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_DATABASE') ?: 'neondb';
        $user = getenv('DB_USERNAME') ?: 'postgres';
        $password = getenv('DB_PASSWORD') ?: '';
        $sslmode = getenv('DB_SSLMODE') ?: 'require';

        if (!empty($dbUrl)) {
            $parsed = parse_url($dbUrl);
            if ($parsed) {
                $host = $parsed['host'] ?? $host;
                $port = $parsed['port'] ?? $port;
                $user = $parsed['user'] ?? $user;
                $password = $parsed['pass'] ?? $password;
                $dbname = ltrim($parsed['path'] ?? $dbname, '/');
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $query);
                    if (isset($query['sslmode'])) {
                        $sslmode = $query['sslmode'];
                    }
                }
            }
        }

        // Only try pgsql if explicitly configured or host is not localhost with dummy values
        $preferredDriver = getenv('DB_CONNECTION') ?: 'pgsql';

        if ($preferredDriver === 'pgsql') {
            try {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5, // 5s timeout to not hang if Neon network changes
                ];

                self::$instance = new PDO($dsn, $user, $password, $options);
                self::$activeDriver = 'pgsql';
                self::$lastError = null;
                return;
            } catch (PDOException $e) {
                self::$lastError = "Neon PostgreSQL connection warning: " . $e->getMessage();
            }
        }

        // 2. Fallback to SQLite for instant local development / offline readiness
        try {
            $sqlitePath = getenv('DB_SQLITE_PATH') ?: __DIR__ . '/../../database/database.sqlite';
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $dsn = "sqlite:{$sqlitePath}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            self::$instance = new PDO($dsn, null, null, $options);
            self::$instance->exec("PRAGMA foreign_keys = ON;");
            self::$activeDriver = 'sqlite';
        } catch (PDOException $e) {
            throw new Exception("Database connection failure on both Neon PostgreSQL and SQLite: " . $e->getMessage());
        }
    }

    /**
     * Get active driver ('pgsql' for Neon or 'sqlite' for fallback)
     */
    public static function getActiveDriver(): string
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$activeDriver;
    }

    /**
     * Get details about the database connection
     */
    public static function getHealthStatus(): array
    {
        try {
            $pdo = self::getInstance();
            $driver = self::getActiveDriver();
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            return [
                'status' => 'connected',
                'driver' => $driver,
                'target' => $driver === 'pgsql' ? 'Neon SQL (Serverless PostgreSQL)' : 'SQLite (Local Sandbox Fallback)',
                'server_version' => $version,
                'notice' => self::$lastError,
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'database' => getenv('DB_DATABASE') ?: 'neondb',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'disconnected',
                'driver' => self::$activeDriver,
                'error' => $e->getMessage(),
                'notice' => self::$lastError,
            ];
        }
    }

    /**
     * Force reconnection
     */
    public static function reconnect(): PDO
    {
        self::$instance = null;
        return self::getInstance();
    }
}
