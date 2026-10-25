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

    public function put(string $path, callable|array $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
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

        // Check defined route directly or via regex pattern match
        $handler = null;
        $matchedParams = [];

        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
        } else if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routePattern => $h) {
                if (str_contains($routePattern, '{')) {
                    $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePattern);
                    $pattern = '#^' . $pattern . '$#';
                    if (preg_match($pattern, $uri, $matches)) {
                        $handler = $h;
                        foreach ($matches as $k => $v) {
                            if (!is_int($k)) {
                                $matchedParams[$k] = $v;
                                $_GET[$k] = $v; // populate for controller access
                            }
                        }
                        break;
                    }
                }
            }
        }

        if ($handler !== null) {
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
