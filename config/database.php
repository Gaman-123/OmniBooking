<?php

return [
    'default' => getenv('DB_CONNECTION') ?: 'pgsql',

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => getenv('DATABASE_URL'),
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'neondb',
            'username' => getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PASSWORD') ?: '',
            'sslmode' => getenv('DB_SSLMODE') ?: 'require',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => getenv('DB_SQLITE_PATH') ?: __DIR__ . '/../database/database.sqlite',
            'prefix' => '',
        ],
    ],
];
