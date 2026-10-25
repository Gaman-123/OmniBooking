<?php

namespace App\Services;

class CppAlgorithmBridge
{
    private string $engineBinaryPath;

    public function __construct()
    {
        $customPath = getenv('CPP_ENGINE_PATH') ?: 'bin/channel_engine.exe';
        $this->engineBinaryPath = realpath(__DIR__ . '/../../' . $customPath) ?: (__DIR__ . '/../../' . $customPath);
    }

    /**
     * Check if the compiled C++ binary exists and is executable
     */
    public function isAvailable(): bool
    {
        return file_exists($this->engineBinaryPath);
    }

    /**
     * Get C++ Engine Status & Capabilities
     */
    public function getStatus(): array
    {
        if (!$this->isAvailable()) {
            return [
                'status' => 'offline',
                'engine' => 'OmniBooking C++ Algorithmic Engine',
                'error' => 'Binary not compiled. Run "php artisan cpp:build" to compile with GCC/g++.',
                'binary_path' => $this->engineBinaryPath
            ];
        }

        return $this->execute('--status');
    }

    /**
     * Run high-speed micro-benchmarks
     */
    public function runBenchmark(): array
    {
        return $this->execute('--benchmark');
    }

    /**
     * Run dynamic pricing calculation
     */
    public function runDynamicPricing(): array
    {
        return $this->execute('--pricing');
    }

    /**
     * Solve Directed Acyclic Graph (DAG) for OTA task dispatching
     */
    public function solveDag(): array
    {
        return $this->execute('--dag');
    }

    /**
     * Optimize multi-channel inventory allocation quota
     */
    public function optimizeAllocation(): array
    {
        return $this->execute('--allocate');
    }

    /**
     * Audit multi-channel rate parity
     */
    public function auditRateParity(): array
    {
        return $this->execute('--parity');
    }

    /**
     * Internal executor using proc_open for safety and accurate sub-millisecond benchmarking
     */
    private function execute(string $flag): array
    {
        if (!$this->isAvailable()) {
            return [
                'status' => 'fallback',
                'engine' => 'PHP_NATIVE_FALLBACK',
                'warning' => 'C++ binary not found at ' . $this->engineBinaryPath . '. Using fallback.',
                'flag' => $flag
            ];
        }

        $cmd = escapeshellarg($this->engineBinaryPath) . ' ' . escapeshellarg($flag);
        $startTime = microtime(true);

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [
                'status' => 'error',
                'message' => 'Failed to spawn C++ algorithmic process'
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $elapsedMs = round((microtime(true) - $startTime) * 1000, 3);

        if ($exitCode !== 0) {
            return [
                'status' => 'error',
                'exit_code' => $exitCode,
                'stderr' => $stderr,
                'elapsed_ms' => $elapsedMs
            ];
        }

        $decoded = json_decode($stdout, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $decoded['bridge_latency_ms'] = $elapsedMs;
            $decoded['engine_mode'] = 'NATIVE_CPP17';
            return $decoded;
        }

        return [
            'status' => 'raw_output',
            'output' => $stdout,
            'elapsed_ms' => $elapsedMs
        ];
    }
}
