<?php

namespace App\Http\Controllers;

use App\Database\Connection;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\Inventory;
use App\Models\RoomType;
use App\Services\DagDispatcherService;
use Exception;

class BookingController
{
    private DagDispatcherService $dagDispatcher;

    public function __construct()
    {
        $this->dagDispatcher = new DagDispatcherService();
    }

    /**
     * GET /api/bookings
     */
    public function list(): array
    {
        $bookings = Booking::all(50);
        return [
            'status' => 'success',
            'data' => $bookings
        ];
    }

    /**
     * POST /api/simulate-booking
     * Simulates an incoming booking from an OTA (Booking.com, Agoda, Rakuten)
     */
    public function simulate(): array
    {
        $channels = Channel::all();
        if (empty($channels)) {
            return ['status' => 'error', 'message' => 'No active OTA channels found. Please run seeders.'];
        }

        $roomTypes = RoomType::all();
        if (empty($roomTypes)) {
            return ['status' => 'error', 'message' => 'No room types found. Please run seeders.'];
        }

        // Pick random room type and channel
        $selectedRoom = $roomTypes[array_rand($roomTypes)];
        $selectedChannel = $channels[array_rand($channels)];

        // Dates: Today for 2 nights
        $checkIn = date('Y-m-d');
        $checkOut = date('Y-m-d', strtotime('+2 days'));

        $simulatedGuestNames = [
            'Sarah Jenkins', 'Hiroshi Tanaka', 'David Miller', 
            'Elena Rostova', 'Carlos Mendes', 'Aarav Patel'
        ];
        $guestName = $simulatedGuestNames[array_rand($simulatedGuestNames)];

        return $this->processBooking([
            'room_type_id' => (int) $selectedRoom['id'],
            'channel_id' => (int) $selectedChannel['id'],
            'guest_name' => $guestName,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms_count' => 1
        ]);
    }

    /**
     * POST /api/bookings
     */
    public function create(): array
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $roomTypeId = (int) ($input['room_type_id'] ?? 1);
        $channelId = isset($input['channel_id']) ? (int) $input['channel_id'] : null;
        $guestName = trim($input['guest_name'] ?? 'Direct Guest');
        $checkIn = $input['check_in'] ?? date('Y-m-d');
        $checkOut = $input['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
        $roomsCount = (int) ($input['rooms_count'] ?? 1);

        return $this->processBooking([
            'room_type_id' => $roomTypeId,
            'channel_id' => $channelId,
            'guest_name' => $guestName,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'rooms_count' => $roomsCount
        ]);
    }

    /**
     * Core Atomic Booking Flow (Node A) followed by DAG Webhook Fan-Out (Nodes B, C, D)
     */
    private function processBooking(array $params): array
    {
        $db = Connection::getInstance();
        $db->beginTransaction();

        try {
            $roomTypeId = $params['room_type_id'];
            $checkIn = $params['check_in'];
            $checkOut = $params['check_out'];
            $roomsCount = $params['rooms_count'];

            // 1. Verify availability across interval
            $availability = Inventory::checkAvailability($roomTypeId, $checkIn, $checkOut, $roomsCount);
            if (!$availability['is_available']) {
                $db->rollBack();
                return [
                    'status' => 'conflict',
                    'message' => 'Zero room inventory available for requested dates. Overbooking prevented!',
                    'details' => $availability
                ];
            }

            // 2. Decrement inventory atomically (Node A: Core DB Commit)
            $decremented = Inventory::decrementRange($roomTypeId, $checkIn, $checkOut, $roomsCount);
            if (!$decremented) {
                $db->rollBack();
                return [
                    'status' => 'conflict',
                    'message' => 'Concurrent race conflict detected during reservation commit. Rolled back.',
                ];
            }

            // 3. Create booking record
            $bookingRef = 'OB-' . strtoupper(substr(uniqid(), -6));
            $totalPrice = $availability['total_price'] > 0 ? $availability['total_price'] : 150.00;

            $bookingId = Booking::create([
                'booking_reference' => $bookingRef,
                'guest_name' => $params['guest_name'],
                'room_type_id' => $roomTypeId,
                'channel_id' => $params['channel_id'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_price' => $totalPrice,
                'status' => 'confirmed'
            ]);

            $db->commit();

            // Fetch created booking for DAG fan-out
            $bookingRecord = Booking::find($bookingId);

            // 4. DAG Task Dispatcher: Asynchronous fan-out to external mock OTAs (Nodes B, C, D)
            $fanOutLogs = $this->dagDispatcher->dispatchFanOut(
                $bookingRecord, 
                $params['channel_id']
            );

            return [
                'status' => 'confirmed',
                'message' => 'Booking successfully committed and broadcasted to external channels.',
                'booking' => $bookingRecord,
                'dag_dispatcher' => [
                    'node_a_database_commit' => 'SUCCESS',
                    'worker_fan_out_nodes' => $fanOutLogs
                ]
            ];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'status' => 'error',
                'message' => 'Booking processing failed: ' . $e->getMessage()
            ];
        }
    }
}
