<?php

namespace App\Database;

use PDO;
use PDOException;
use Exception;

class Connection
{
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';
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
     * Establish the connection, prioritizing MySQL with graceful fallback to Neon or SQLite
     */
    private static function connect(): void
    {
        $preferredDriver = getenv('DB_CONNECTION') ?: 'mysql';

        // 1. Try MySQL Connection if preferred or configured
        if ($preferredDriver === 'mysql') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'omni_booking';
            $user = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

            try {
                // First ensure database exists (or connect without db name first)
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 3,
                    ];
                    self::$instance = new PDO($dsn, $user, $password, $options);
                } catch (PDOException $ex) {
                    // If error is 1049 (Unknown database), try auto-creating database
                    if ($ex->getCode() == 1049 || str_contains($ex->getMessage(), 'Unknown database')) {
                        $serverDsn = "mysql:host={$host};port={$port};charset={$charset}";
                        $serverPdo = new PDO($serverDsn, $user, $password, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 3
                        ]);
                        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci;");
                        self::$instance = new PDO($dsn, $user, $password, $options);
                    } else {
                        throw $ex;
                    }
                }

                self::$activeDriver = 'mysql';
                self::$lastError = null;
                return;
            } catch (PDOException $e) {
                self::$lastError = "MySQL connection warning: " . $e->getMessage();
            }
        }

        // 2. Try Neon PostgreSQL if configured or if pgsql was preferred
        if ($preferredDriver === 'pgsql' || !empty(getenv('DATABASE_URL'))) {
            $dbUrl = getenv('DATABASE_URL');
            $host = getenv('DB_HOST') ?: (getenv('NEON_DB_HOST') ?: '127.0.0.1');
            $port = getenv('DB_PORT') ?: (getenv('NEON_DB_PORT') ?: '5432');
            $dbname = getenv('DB_DATABASE') ?: (getenv('NEON_DB_DATABASE') ?: 'neondb');
            $user = getenv('DB_USERNAME') ?: (getenv('NEON_DB_USERNAME') ?: 'postgres');
            $password = getenv('DB_PASSWORD') ?: (getenv('NEON_DB_PASSWORD') ?: '');
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

            try {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 4,
                ];

                self::$instance = new PDO($dsn, $user, $password, $options);
                self::$activeDriver = 'pgsql';
                self::$lastError = null;
                return;
            } catch (PDOException $e) {
                self::$lastError = (self::$lastError ? self::$lastError . " | " : "") . "Neon PostgreSQL connection warning: " . $e->getMessage();
            }
        }

        // 3. Resilient Fallback to SQLite (Instant zero-downtime sandbox)
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
            throw new Exception("Database connection failure across all drivers (MySQL, PostgreSQL, SQLite): " . $e->getMessage());
        }
    }

    /**
     * Get active driver ('mysql', 'pgsql', or 'sqlite')
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

            $targetLabel = match($driver) {
                'mysql' => 'MySQL Database Engine (InnoDB)',
                'pgsql' => 'Neon SQL (Serverless PostgreSQL)',
                default => 'SQLite (Local Sandbox Fallback)'
            };

            return [
                'status' => 'connected',
                'driver' => $driver,
                'target' => $targetLabel,
                'server_version' => $version,
                'notice' => self::$lastError,
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'database' => getenv('DB_DATABASE') ?: 'omni_booking',
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
