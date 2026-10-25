<?php

namespace App\Http\Controllers;

use App\Services\CppAlgorithmBridge;

class CppEngineController
{
    private CppAlgorithmBridge $bridge;

    public function __construct()
    {
        $this->bridge = new CppAlgorithmBridge();
    }

    /**
     * GET /api/cpp/status
     */
    public function status(): array
    {
        return $this->bridge->getStatus();
    }

    /**
     * POST /api/cpp/benchmark
     */
    public function benchmark(): array
    {
        return $this->bridge->runBenchmark();
    }

    /**
     * POST /api/cpp/pricing
     */
    public function pricing(): array
    {
        return $this->bridge->runDynamicPricing();
    }

    /**
     * POST /api/cpp/dag-solve
     */
    public function dag(): array
    {
        return $this->bridge->solveDag();
    }

    /**
     * POST /api/cpp/allocate
     */
    public function allocate(): array
    {
        return $this->bridge->optimizeAllocation();
    }

    /**
     * POST /api/cpp/rate-parity
     */
    public function rateParity(): array
    {
        return $this->bridge->auditRateParity();
    }
}
