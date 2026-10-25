<?php

namespace Database\Seeders;

use App\Database\Connection;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Channel;
use App\Models\Inventory;
use App\Models\User;
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

        $channelIds = [];
        if ($chanCount === 0) {
            $channels = [
                ['name' => 'Booking.com', 'url' => 'http://127.0.0.1:8000/api/mock-ota/booking-com'],
                ['name' => 'Agoda', 'url' => 'http://127.0.0.1:8000/api/mock-ota/agoda'],
                ['name' => 'Rakuten Travel', 'url' => 'http://127.0.0.1:8000/api/mock-ota/rakuten']
            ];

            foreach ($channels as $c) {
                $cId = Channel::create($c['name'], $c['url'], true);
                $channelIds[] = $cId;
                $logs[] = "Seeded OTA Channel: '{$c['name']}' (ID: {$cId})";
            }
        } else {
            $stmt = $db->query("SELECT id FROM channels");
            while ($row = $stmt->fetch()) {
                $channelIds[] = (int) $row['id'];
            }
            $logs[] = "Found " . count($channelIds) . " existing channels.";
        }

        // 5. Seed Users
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
        $userCount = (int) $stmt->fetch()['cnt'];

        $userIds = [];
        if ($userCount === 0) {
            $seedUsers = [
                [
                    'name'     => 'Admin User',
                    'email'    => 'admin@omnibooking.com',
                    'password' => 'Admin@1234',
                    'phone'    => '+91-9000000001',
                    'role'     => 'admin',
                ],
                [
                    'name'     => 'Alexander Wright',
                    'email'    => 'alexander.wright@example.com',
                    'password' => 'Guest@1234',
                    'phone'    => '+1-555-010-0001',
                    'role'     => 'guest',
                ],
                [
                    'name'     => 'Mei-Ling Zhou',
                    'email'    => 'meiling.zhou@example.com',
                    'password' => 'Guest@1234',
                    'phone'    => '+86-13800138001',
                    'role'     => 'guest',
                ],
                [
                    'name'     => 'Carlos Hernandez',
                    'email'    => 'carlos.hernandez@example.com',
                    'password' => 'Guest@1234',
                    'phone'    => '+52-55-1234-5678',
                    'role'     => 'guest',
                ],
            ];

            foreach ($seedUsers as $u) {
                $uid = User::create($u);
                $userIds[] = $uid;
                $logs[] = "Seeded User: '{$u['name']}' <{$u['email']}> [{$u['role']}] (ID: {$uid})";
            }
        } else {
            $stmt = $db->query("SELECT id FROM users WHERE role = 'guest' LIMIT 3");
            while ($row = $stmt->fetch()) {
                $userIds[] = (int) $row['id'];
            }
            $logs[] = "Found {$userCount} existing users.";
        }

        // 6. Seed Initial Sample Bookings if none exist
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM bookings");
        $bookingCount = (int) $stmt->fetch()['cnt'];

        if ($bookingCount === 0 && !empty($roomTypeIds) && !empty($channelIds)) {
            // Guest user IDs (skip index 0 = admin)
            $guestUserIds = array_slice($userIds, 1);

            $sampleGuests = [
                ['name' => 'Alexander Wright', 'email' => 'alexander.wright@example.com', 'days_offset' => 1, 'nights' => 3, 'price' => 720.00,  'special' => 'Ocean-view room preferred'],
                ['name' => 'Mei-Ling Zhou',    'email' => 'meiling.zhou@example.com',    'days_offset' => 4, 'nights' => 2, 'price' => 370.00,  'special' => 'Early check-in requested'],
                ['name' => 'Carlos Hernandez', 'email' => 'carlos.hernandez@example.com','days_offset' => 7, 'nights' => 4, 'price' => 1240.00, 'special' => 'Airport transfer needed'],
            ];

            foreach ($sampleGuests as $idx => $sg) {
                $checkIn  = (clone $today)->modify("+{$sg['days_offset']} days")->format('Y-m-d');
                $checkOut = (clone $today)->modify("+" . ($sg['days_offset'] + $sg['nights']) . " days")->format('Y-m-d');
                $rtId     = $roomTypeIds[$idx % count($roomTypeIds)]['id'];
                $chId     = $channelIds[$idx % count($channelIds)];
                $uid      = $guestUserIds[$idx % count($guestUserIds)] ?? null;

                $bId = \App\Models\Booking::create([
                    'booking_reference' => 'OB-INIT' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT),
                    'user_id'           => $uid,
                    'guest_name'        => $sg['name'],
                    'guest_email'       => $sg['email'],
                    'room_type_id'      => $rtId,
                    'channel_id'        => $chId,
                    'check_in'          => $checkIn,
                    'check_out'         => $checkOut,
                    'total_price'       => $sg['price'],
                    'status'            => 'confirmed',
                    'special_requests'  => $sg['special'],
                ]);

                \App\Models\SyncLog::record($bId, $chId, [
                    'event'       => 'inventory.seeded',
                    'timestamp'   => date('c'),
                    'booking_ref' => 'OB-INIT' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT),
                    'channel'     => 'Initial Seed Sync',
                    'message'     => 'Initial inventory broadcast established'
                ], 200, rand(18, 42));

                $logs[] = "Seeded Booking: '{$sg['name']}' (user_id: {$uid}) [{$checkIn} → {$checkOut}]";
            }
        }

        return $logs;
    }
}
