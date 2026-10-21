<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;

class SyncLogController
{
    /**
     * GET /api/sync-logs
     */
    public function list(): array
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
        $logs = SyncLog::all($limit);

        return [
            'status' => 'success',
            'count' => count($logs),
            'data' => $logs
        ];
    }
}
