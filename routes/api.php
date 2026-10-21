<?php

use App\Http\Router;
use App\Database\Connection;
use App\Database\Migrator;
require_once __DIR__ . '/../database/seeders/DatabaseSeeder.php';
use Database\Seeders\DatabaseSeeder;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\MockOtaController;

$router = new Router();

// 1. Health & Database Diagnostics
$router->get('/api/health', function() {
    return [
        'status' => 'healthy',
        'app' => 'Omni Booking Channel Manager',
        'timestamp' => date('c'),
        'database' => Connection::getHealthStatus()
    ];
});

// 2. Migration & Seeder Management Endpoints
$router->post('/api/migrate', function() {
    $migrator = new Migrator();
    $logs = $migrator->migrate();
    return [
        'status' => 'success',
        'message' => 'Database migrations applied.',
        'logs' => $logs
    ];
});

$router->post('/api/seed', function() {
    $seeder = new DatabaseSeeder();
    $logs = $seeder->run();
    return [
        'status' => 'success',
        'message' => 'Database seeding completed.',
        'logs' => $logs
    ];
});

// 3. Inventory & Availability Matrix
$router->get('/api/inventory-matrix', [InventoryController::class, 'getMatrix']);
$router->post('/api/check-availability', [InventoryController::class, 'checkAvailability']);

// 4. Bookings & Concurrency Protection
$router->get('/api/bookings', [BookingController::class, 'list']);
$router->post('/api/bookings', [BookingController::class, 'create']);
$router->post('/api/simulate-booking', [BookingController::class, 'simulate']);

// 5. Dynamic Pricing Engine
$router->post('/api/run-ai-pricing', [PricingController::class, 'runPricing']);

// 6. External OTA Sync Logs (Audit Trail)
$router->get('/api/sync-logs', [SyncLogController::class, 'list']);

// 7. Mock OTA Webhook Receivers
$router->post('/api/mock-ota/booking-com', [MockOtaController::class, 'bookingCom']);
$router->post('/api/mock-ota/agoda', [MockOtaController::class, 'agoda']);
$router->post('/api/mock-ota/rakuten', [MockOtaController::class, 'rakuten']);

return $router;
