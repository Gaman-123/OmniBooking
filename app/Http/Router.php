<?php

namespace App\Http;

use App\Database\Connection;
use Throwable;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        // CORS & Content-Type Headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normalize trailing slash
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // Check defined route
        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
            try {
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $response = $controller->$action();
                } else {
                    $response = $handler();
                }

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } catch (Throwable $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_PRETTY_PRINT);
            }
            return;
        }

        // 404 Not Found
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => "Route not found: {$method} {$uri}"
        ], JSON_PRETTY_PRINT);
    }
}
