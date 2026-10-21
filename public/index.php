<?php

// Autoload Composer dependencies
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Request path routing
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Serve static frontend dashboard if root or index.html is requested
if ($uri === '/' || $uri === '/index.html') {
    $staticHtmlPath = __DIR__ . '/index.html';
    if (file_exists($staticHtmlPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($staticHtmlPath);
        exit;
    }
}

// Check for static asset files in public/
$publicFile = __DIR__ . $uri;
if ($uri !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    $ext = pathinfo($publicFile, PATHINFO_EXTENSION);
    $mime = match ($ext) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        default => 'text/plain'
    };
    header("Content-Type: {$mime}");
    readfile($publicFile);
    exit;
}

// Load and dispatch API router
$router = require __DIR__ . '/../routes/api.php';
$router->dispatch();
