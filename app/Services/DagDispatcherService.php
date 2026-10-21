<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\SyncLog;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Throwable;

class DagDispatcherService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 3.0,
            'http_errors' => false,
        ]);
    }

    /**
     * Dispatch DAG task fan-out to external mock OTAs after Node A (DB Commit) succeeds
     *
     * DAG Node Structure:
     *      [Node A: DB Commit] (Pre-requisite, already completed)
     *             |
     *      +------+------+
     *      |      |      |
     *      v      v      v
     *   [Node B] [Node C] [Node D]
     *  Booking  Agoda   Rakuten
     */
    public function dispatchFanOut(array $booking, ?int $originChannelId = null): array
    {
        $channels = Channel::all();
        $dispatchedLogs = [];

        foreach ($channels as $channel) {
            // Avoid echoing back to the exact originating channel if it was an inbound OTA booking
            if ($originChannelId !== null && (int)$channel['id'] === $originChannelId) {
                continue;
            }

            $webhookUrl = $channel['webhook_url'];
            $channelId = (int) $channel['id'];
            $channelName = $channel['name'];

            $payload = [
                'event' => 'inventory.updated',
                'timestamp' => date('c'),
                'origin' => 'OmniBooking-ChannelManager',
                'booking_reference' => $booking['booking_reference'],
                'room_type_id' => $booking['room_type_id'],
                'check_in' => $booking['check_in'],
                'check_out' => $booking['check_out'],
                'remaining_inventory_adjustment' => -1,
                'channel_target' => $channelName
            ];

            $startTime = microtime(true);
            $responseStatus = 200;

            try {
                // If it's a local mock webhook URL, we can invoke directly or via HTTP
                if (str_starts_with($webhookUrl, 'http://127.0.0.1') || str_starts_with($webhookUrl, 'http://localhost')) {
                    // Fast internal simulation for local environment
                    usleep(rand(15000, 45000)); // simulate 15-45ms network latency
                    $responseStatus = 200;
                } else {
                    $response = $this->client->post($webhookUrl, [
                        'json' => $payload
                    ]);
                    $responseStatus = $response->getStatusCode();
                }
            } catch (Throwable $e) {
                $responseStatus = 502; // Bad Gateway
            }

            $executionTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            // Record into sync_logs table (Neon SQL PostgreSQL)
            $logId = SyncLog::record(
                (int) $booking['id'],
                $channelId,
                $payload,
                $responseStatus,
                $executionTimeMs
            );

            $dispatchedLogs[] = [
                'sync_log_id' => $logId,
                'channel_id' => $channelId,
                'channel_name' => $channelName,
                'status_code' => $responseStatus,
                'execution_time_ms' => $executionTimeMs,
                'status' => ($responseStatus >= 200 && $responseStatus < 300) ? 'SUCCESS' : 'FAILED'
            ];
        }

        return $dispatchedLogs;
    }
}
