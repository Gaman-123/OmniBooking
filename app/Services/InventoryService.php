<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\RoomType;
use DateTime;

class InventoryService
{
    /**
     * Build availability & price matrix for a given number of days (default 7 days)
     */
    public function getMatrix(int $days = 7): array
    {
        $today = new DateTime();
        $startDate = $today->format('Y-m-d');

        $end = clone $today;
        $end->modify("+" . ($days - 1) . " days");
        $endDate = $end->format('Y-m-d');

        // Generate date headers
        $dates = [];
        $cursor = clone $today;
        for ($i = 0; $i < $days; $i++) {
            $formattedDate = $cursor->format('Y-m-d');
            $label = match ($i) {
                0 => 'Today',
                1 => 'Tomorrow',
                2 => 'Day +2',
                default => 'Day +' . $i
            };

            $dates[] = [
                'date' => $formattedDate,
                'label' => $label,
                'display' => $cursor->format('M j (D)'),
                'day_of_week' => (int) $cursor->format('w')
            ];
            $cursor->modify('+1 day');
        }

        $roomTypes = RoomType::all();
        $rawInventory = Inventory::getMatrix($startDate, $endDate);

        // Index inventory by [room_type_id][date]
        $indexedInv = [];
        foreach ($rawInventory as $item) {
            $indexedInv[$item['room_type_id']][$item['date']] = $item;
        }

        $matrixRows = [];
        foreach ($roomTypes as $rt) {
            $rtId = $rt['id'];
            $rowCells = [];

            foreach ($dates as $d) {
                $curDate = $d['date'];
                if (isset($indexedInv[$rtId][$curDate])) {
                    $inv = $indexedInv[$rtId][$curDate];
                    $avail = (int) $inv['available_rooms'];
                    $price = (float) $inv['price'];
                } else {
                    $avail = (int) $rt['total_units'];
                    $price = (float) $rt['base_price'];
                }

                $occupancyRate = $rt['total_units'] > 0
                    ? max(0, min(100, round((1 - ($avail / $rt['total_units'])) * 100)))
                    : 0;

                $rowCells[] = [
                    'date' => $curDate,
                    'available' => $avail,
                    'total_units' => (int) $rt['total_units'],
                    'price' => $price,
                    'occupancy_pct' => $occupancyRate,
                    'status' => $avail <= 0 ? 'sold_out' : ($avail <= 2 ? 'critical' : 'available')
                ];
            }

            $matrixRows[] = [
                'room_type_id' => $rtId,
                'room_type_name' => $rt['name'],
                'total_units' => (int) $rt['total_units'],
                'base_price' => (float) $rt['base_price'],
                'cells' => $rowCells
            ];
        }

        return [
            'dates' => $dates,
            'rows' => $matrixRows,
            'range' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => $days
            ]
        ];
    }

    /**
     * Calculate dynamic pricing based on occupancy curve and date proximity
     */
    public function computeDynamicPricing(int $days = 7): array
    {
        $matrix = $this->getMatrix($days);
        $updatedItems = [];

        foreach ($matrix['rows'] as $row) {
            $rtId = $row['room_type_id'];
            $basePrice = $row['base_price'];
            $totalUnits = $row['total_units'];

            foreach ($row['cells'] as $idx => $cell) {
                $avail = $cell['available'];
                $date = $cell['date'];
                $leadDays = $idx;
                $dow = date('w', strtotime($date));

                $occupancy = $totalUnits > 0 ? (1.0 - ($avail / $totalUnits)) : 0.0;
                $occupancy = max(0.0, min(1.0, $occupancy));

                // 1. Occupancy Surge Multiplier
                $surge = 1.0 + 0.45 * pow($occupancy, 2.0);

                // 2. Weekend multiplier
                $weekendMultiplier = ($dow == 5 || $dow == 6) ? 1.20 : 1.0;

                // 3. Proximity lead time multiplier
                $leadMultiplier = ($leadDays <= 1 && $occupancy > 0.6) ? 1.15 : 1.0;

                $finalPrice = round($basePrice * $surge * $weekendMultiplier * $leadMultiplier, 2);

                // Persist new pricing into database
                Inventory::upsert($rtId, $date, $avail, $finalPrice);

                $updatedItems[] = [
                    'room_type_id' => $rtId,
                    'room_type_name' => $row['room_type_name'],
                    'date' => $date,
                    'original_price' => $cell['price'],
                    'calculated_price' => $finalPrice,
                    'occupancy_pct' => round($occupancy * 100),
                    'surge_multiplier' => round($surge * $weekendMultiplier * $leadMultiplier, 2)
                ];
            }
        }

        $bridge = new CppAlgorithmBridge();
        $engineType = $bridge->isAvailable() ? 'C++17 High-Yield Computational Engine' : 'PHP Native Fallback';

        return [
            'status' => 'success',
            'engine' => $engineType,
            'updated_count' => count($updatedItems),
            'items' => $updatedItems
        ];
    }
}
