<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use App\Models\Inventory;
use App\Models\RoomType;

class InventoryController
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * GET /api/inventory-matrix
     */
    public function getMatrix(): array
    {
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
        $days = max(3, min(30, $days));

        $matrix = $this->inventoryService->getMatrix($days);

        return [
            'status' => 'success',
            'data' => $matrix
        ];
    }

    /**
     * POST /api/check-availability
     */
    public function checkAvailability(): array
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $roomTypeId = (int) ($input['room_type_id'] ?? 1);
        $checkIn = $input['check_in'] ?? date('Y-m-d');
        $checkOut = $input['check_out'] ?? date('Y-m-d', strtotime('+2 days'));
        $roomsCount = (int) ($input['rooms_count'] ?? 1);

        $result = Inventory::checkAvailability($roomTypeId, $checkIn, $checkOut, $roomsCount);

        return [
            'status' => 'success',
            'data' => $result
        ];
    }
}
