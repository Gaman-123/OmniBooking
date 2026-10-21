<?php

namespace App\Http\Controllers;

class MockOtaController
{
    /**
     * POST /api/mock-ota/booking-com
     */
    public function bookingCom(): array
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        return [
            'status' => 'acknowledged',
            'ota' => 'Mock Booking.com',
            'received_event' => $payload['event'] ?? 'unknown',
            'booking_ref' => $payload['booking_reference'] ?? null,
            'processed_at' => date('c'),
            'http_code' => 200
        ];
    }

    /**
     * POST /api/mock-ota/agoda
     */
    public function agoda(): array
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        return [
            'status' => 'acknowledged',
            'ota' => 'Mock Agoda',
            'received_event' => $payload['event'] ?? 'unknown',
            'booking_ref' => $payload['booking_reference'] ?? null,
            'processed_at' => date('c'),
            'http_code' => 200
        ];
    }

    /**
     * POST /api/mock-ota/rakuten
     */
    public function rakuten(): array
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        return [
            'status' => 'acknowledged',
            'ota' => 'Mock Rakuten Travel',
            'received_event' => $payload['event'] ?? 'unknown',
            'booking_ref' => $payload['booking_reference'] ?? null,
            'processed_at' => date('c'),
            'http_code' => 200
        ];
    }
}
