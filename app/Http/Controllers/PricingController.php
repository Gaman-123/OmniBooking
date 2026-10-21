<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;

class PricingController
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * POST /api/run-ai-pricing
     */
    public function runPricing(): array
    {
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
        $result = $this->inventoryService->computeDynamicPricing($days);

        return [
            'status' => 'success',
            'message' => 'AI Dynamic Pricing executed across inventory matrix successfully.',
            'data' => $result
        ];
    }
}
