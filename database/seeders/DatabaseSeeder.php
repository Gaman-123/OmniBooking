<?php

namespace Database\Seeders;

use App\Database\Connection;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Channel;
use App\Models\Inventory;
use DateTime;
use Exception;

class DatabaseSeeder
{
    public function run(): array
    {
        $logs = [];
        $db = Connection::getInstance();

        // 1. Seed Hotel
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM hotels");
        $hotelCount = (int) $stmt->fetch()['cnt'];

        if ($hotelCount === 0) {
            $hotelId = Hotel::create(
                'Grand Horizon Palace & Resorts',
                '777 Coastal Boulevard, Marina Bay'
            );
            $logs[] = "Seeded Hotel: 'Grand Horizon Palace & Resorts' (ID: {$hotelId})";
        } else {
            $stmt = $db->query("SELECT id FROM hotels LIMIT 1");
            $hotelId = (int) $stmt->fetch()['id'];
            $logs[] = "Hotel already exists (ID: {$hotelId})";
        }

        // 2. Seed Room Types
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM room_types");
        $rtCount = (int) $stmt->fetch()['cnt'];

        $roomTypeIds = [];
        if ($rtCount === 0) {
            $types = [
                ['name' => 'Deluxe Ocean Suite', 'units' => 12, 'price' => 240.00],
                ['name' => 'Executive Skyline King', 'units' => 18, 'price' => 185.00],
                ['name' => 'Garden Villa Classic', 'units' => 8, 'price' => 310.00]
            ];

            foreach ($types as $t) {
                $id = RoomType::create($hotelId, $t['name'], $t['units'], $t['price']);
                $roomTypeIds[] = ['id' => $id, 'units' => $t['units'], 'price' => $t['price']];
                $logs[] = "Seeded Room Type: '{$t['name']}' ({$t['units']} units, \${$t['price']})";
            }
        } else {
            $stmt = $db->query("SELECT id, total_units, base_price FROM room_types");
            while ($row = $stmt->fetch()) {
                $roomTypeIds[] = [
                    'id' => (int) $row['id'],
                    'units' => (int) $row['total_units'],
                    'price' => (float) $row['base_price']
                ];
            }
            $logs[] = "Found " . count($roomTypeIds) . " existing room types.";
        }

        // 3. Seed 30-Day Inventory Matrix
        $today = new DateTime();
        $seededDatesCount = 0;

        foreach ($roomTypeIds as $rt) {
            $cursor = clone $today;
            for ($d = 0; $d < 30; $d++) {
                $dateStr = $cursor->format('Y-m-d');
                $dow = (int) $cursor->format('w');

                // Simulate realistic availability variations
                $soldOutFactor = ($d === 3 || $d === 14) ? 0 : 1;
                $avail = max(0, $rt['units'] - ($d % 4) * $soldOutFactor);

                // Weekend slight price bump
                $price = $rt['price'] * (($dow === 5 || $dow === 6) ? 1.15 : 1.0);

                Inventory::upsert($rt['id'], $dateStr, $avail, round($price, 2));
                $seededDatesCount++;
                $cursor->modify('+1 day');
            }
        }
        $logs[] = "Seeded/Updated 30-day inventory matrix ({$seededDatesCount} date slots).";

        // 4. Seed OTA Channels
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM channels");
        $chanCount = (int) $stmt->fetch()['cnt'];

        if ($chanCount === 0) {
            $channels = [
                ['name' => 'Booking.com', 'url' => 'http://127.0.0.1:8000/api/mock-ota/booking-com'],
                ['name' => 'Agoda', 'url' => 'http://127.0.0.1:8000/api/mock-ota/agoda'],
                ['name' => 'Rakuten Travel', 'url' => 'http://127.0.0.1:8000/api/mock-ota/rakuten']
            ];

            foreach ($channels as $c) {
                Channel::create($c['name'], $c['url'], true);
                $logs[] = "Seeded OTA Channel: '{$c['name']}'";
            }
        } else {
            $logs[] = "Channels already exist.";
        }

        return $logs;
    }
}
