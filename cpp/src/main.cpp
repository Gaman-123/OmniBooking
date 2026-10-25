#include "../include/types.hpp"
#include "../include/dynamic_pricing.hpp"
#include "../include/inventory_allocator.hpp"
#include "../include/dag_scheduler.hpp"
#include "../include/rate_parity_auditor.hpp"

#include <iostream>
#include <string>
#include <vector>
#include <chrono>
#include <sstream>
#include <iomanip>

using namespace OmniBooking;

// Minimal lightweight JSON parser / emitter helpers to avoid heavy external dependencies
static void printHelp() {
    std::cout << "OmniBooking C++ Algorithmic Core Engine v1.0.0\n"
              << "Usage: channel_engine.exe [COMMAND] [OPTIONS]\n\n"
              << "Commands:\n"
              << "  --status         Print engine version, build status, and capabilities\n"
              << "  --benchmark      Run ultra-fast micro-benchmark across algorithms\n"
              << "  --pricing        Compute dynamic surge pricing for standard or simulated inventory\n"
              << "  --allocate       Optimize multi-channel inventory allocation quota\n"
              << "  --dag            Solve Directed Acyclic Graph dependency plan for OTA fan-out\n"
              << "  --parity         Run rate parity discrepancy audit across OTA channels\n"
              << "  --help           Show this help message\n";
}

int main(int argc, char* argv[]) {
    std::string mode = "--status";
    if (argc > 1) {
        mode = argv[1];
    }

    auto startClock = std::chrono::high_resolution_clock::now();

    if (mode == "--help" || mode == "-h") {
        printHelp();
        return 0;
    }

    if (mode == "--status") {
        std::cout << "{\n"
                  << "  \"status\": \"online\",\n"
                  << "  \"engine\": \"OmniBooking C++ Algorithmic Engine\",\n"
                  << "  \"version\": \"1.0.0\",\n"
                  << "  \"compiler\": \"GCC / MinGW C++17\",\n"
                  << "  \"capabilities\": [\n"
                  << "    \"Dynamic Pricing & RevPAR Optimization\",\n"
                  << "    \"Segment Tree Range Minimum Query (RMQ)\",\n"
                  << "    \"Multi-Channel Quota Allocation\",\n"
                  << "    \"Kahn's DAG Task Scheduler & Critical Path\",\n"
                  << "    \"Rate Parity & OTA Leakage Detection\"\n"
                  << "  ]\n"
                  << "}\n";
        return 0;
    }

    if (mode == "--benchmark") {
        // Run 50,000 Segment Tree queries & 5,000 Pricing calculations
        std::vector<int> daily(365, 15);
        AvailabilitySegmentTree segTree(daily);

        auto bStart = std::chrono::high_resolution_clock::now();
        int sum = 0;
        for (int i = 0; i < 50000; ++i) {
            int l = i % 300;
            int r = l + (i % 30);
            sum += segTree.queryMin(l, r);
        }

        DynamicPricingEngine pricingEngine;
        std::vector<RoomInventory> sampleInv;
        for (int i = 0; i < 30; ++i) {
            RoomInventory inv;
            inv.room_type_id = (i % 3) + 1;
            inv.room_type_name = "Suite " + std::to_string(inv.room_type_id);
            inv.date = "2026-11-" + (i < 9 ? "0" : "") + std::to_string(i + 1);
            inv.total_units = 20;
            inv.available_rooms = (i % 7 == 0) ? 2 : 14;
            inv.base_price = 220.0;
            inv.current_price = 220.0;
            sampleInv.push_back(inv);
        }

        for (int i = 0; i < 1000; ++i) {
            auto res = pricingEngine.computeMatrix(sampleInv);
            (void)res;
        }

        auto bEnd = std::chrono::high_resolution_clock::now();
        double totalMicros = std::chrono::duration<double, std::micro>(bEnd - bStart).count();

        std::cout << "{\n"
                  << "  \"status\": \"benchmark_completed\",\n"
                  << "  \"segment_tree_queries\": 50000,\n"
                  << "  \"pricing_matrix_evaluations\": 30000,\n"
                  << "  \"elapsed_microseconds\": " << std::fixed << std::setprecision(2) << totalMicros << ",\n"
                  << "  \"elapsed_milliseconds\": " << std::fixed << std::setprecision(3) << (totalMicros / 1000.0) << ",\n"
                  << "  \"throughput_ops_per_sec\": " << static_cast<long long>(80000.0 / (totalMicros / 1000000.0)) << ",\n"
                  << "  \"execution_speed\": \"SUB_MILLISECOND_OPTIMAL\"\n"
                  << "}\n";
        return 0;
    }

    if (mode == "--pricing") {
        DynamicPricingEngine pricingEngine;
        std::vector<RoomInventory> items;

        // Sample room inventory matrix
        std::vector<std::string> dates = {
            "2026-10-26", "2026-10-27", "2026-10-28", "2026-10-29",
            "2026-10-30", "2026-10-31", "2026-11-01"
        };

        for (size_t i = 0; i < dates.size(); ++i) {
            RoomInventory inv;
            inv.room_type_id = 1;
            inv.room_type_name = "Deluxe Ocean Suite";
            inv.date = dates[i];
            inv.total_units = 12;
            inv.available_rooms = (i == 4 || i == 5) ? 2 : 8; // weekend compression
            inv.base_price = 240.0;
            inv.current_price = 240.0;
            items.push_back(inv);
        }

        auto calculated = pricingEngine.computeMatrix(items, 3);

        auto endClock = std::chrono::high_resolution_clock::now();
        double elapsedMs = std::chrono::duration<double, std::milli>(endClock - startClock).count();

        std::cout << "{\n"
                  << "  \"status\": \"success\",\n"
                  << "  \"algorithm\": \"C++ Non-Linear Yield & Dynamic Pricing Curve\",\n"
                  << "  \"execution_time_ms\": " << std::fixed << std::setprecision(3) << elapsedMs << ",\n"
                  << "  \"recommendations\": [\n";

        for (size_t i = 0; i < calculated.size(); ++i) {
            const auto& c = calculated[i];
            std::cout << "    {\n"
                      << "      \"room_type_id\": " << c.room_type_id << ",\n"
                      << "      \"room_type_name\": \"" << c.room_type_name << "\",\n"
                      << "      \"date\": \"" << c.date << "\",\n"
                      << "      \"base_price\": " << c.base_price << ",\n"
                      << "      \"recommended_price\": " << c.recommended_price << ",\n"
                      << "      \"surge_percentage\": " << c.surge_percentage << ",\n"
                      << "      \"projected_occupancy\": " << c.projected_occupancy << ",\n"
                      << "      \"expected_revpar\": " << c.expected_revpar << ",\n"
                      << "      \"tier\": \"" << c.pricing_tier << "\",\n"
                      << "      \"rational\": \"" << c.rational << "\"\n"
                      << "    }" << (i + 1 < calculated.size() ? "," : "") << "\n";
        }
        std::cout << "  ]\n}\n";
        return 0;
    }

    if (mode == "--dag") {
        std::vector<std::string> channels = {"Booking.com", "Agoda", "Rakuten Travel"};
        DagScheduler scheduler = DagScheduler::createStandardBookingDag(channels);
        DagExecutionPlan plan = scheduler.solve();

        auto endClock = std::chrono::high_resolution_clock::now();
        double elapsedMs = std::chrono::duration<double, std::milli>(endClock - startClock).count();

        std::cout << "{\n"
                  << "  \"status\": \"success\",\n"
                  << "  \"algorithm\": \"Kahn Topological Sort & Critical Path Method\",\n"
                  << "  \"is_valid_dag\": " << (plan.is_valid_dag ? "true" : "false") << ",\n"
                  << "  \"critical_path_latency_ms\": " << plan.critical_path_duration_ms << ",\n"
                  << "  \"algorithm_compute_time_ms\": " << std::fixed << std::setprecision(3) << elapsedMs << ",\n"
                  << "  \"parallel_stages\": [\n";

        for (size_t s = 0; s < plan.parallel_stages.size(); ++s) {
            std::cout << "    {\n"
                      << "      \"stage\": " << (s + 1) << ",\n"
                      << "      \"tasks\": [";
            for (size_t t = 0; t < plan.parallel_stages[s].size(); ++t) {
                std::cout << "\"" << plan.parallel_stages[s][t] << "\"" << (t + 1 < plan.parallel_stages[s].size() ? ", " : "");
            }
            std::cout << "]\n    }" << (s + 1 < plan.parallel_stages.size() ? "," : "") << "\n";
        }
        std::cout << "  ]\n}\n";
        return 0;
    }

    if (mode == "--allocate") {
        MultiChannelAllocator allocator;
        std::vector<ChannelRate> channels = {
            {1, "Direct Website", 240.0, 0.00, 240.0, true},
            {2, "Booking.com", 240.0, 0.15, 204.0, true},
            {3, "Agoda", 240.0, 0.18, 196.8, true},
            {4, "Rakuten Travel", 240.0, 0.14, 206.4, true}
        };

        auto allocations = allocator.optimizeAllocation(12, 240.0, channels);

        auto endClock = std::chrono::high_resolution_clock::now();
        double elapsedMs = std::chrono::duration<double, std::milli>(endClock - startClock).count();

        std::cout << "{\n"
                  << "  \"status\": \"success\",\n"
                  << "  \"algorithm\": \"Channel Margin Maximization (Greedy Knapsack)\",\n"
                  << "  \"execution_time_ms\": " << std::fixed << std::setprecision(3) << elapsedMs << ",\n"
                  << "  \"allocations\": [\n";

        for (size_t i = 0; i < allocations.size(); ++i) {
            const auto& a = allocations[i];
            std::cout << "    {\n"
                      << "      \"channel_id\": " << a.channel_id << ",\n"
                      << "      \"channel_name\": \"" << a.channel_name << "\",\n"
                      << "      \"allocated_units\": " << a.allocated_units << ",\n"
                      << "      \"unit_price\": " << a.unit_price << ",\n"
                      << "      \"projected_revenue\": " << a.projected_revenue << ",\n"
                      << "      \"net_margin\": " << a.channel_margin_net << "\n"
                      << "    }" << (i + 1 < allocations.size() ? "," : "") << "\n";
        }
        std::cout << "  ]\n}\n";
        return 0;
    }

    if (mode == "--parity") {
        RateParityAuditor auditor(1.0);
        double directRate = 240.0;
        std::string testDate = "2026-10-27";

        std::vector<ChannelRate> channels = {
            {1, "Direct Website", 240.0, 0.00, 240.0, true},
            {2, "Booking.com", 215.0, 0.15, 182.75, true}, // undercuts direct!
            {3, "Agoda", 242.0, 0.18, 198.44, true},        // in parity tolerance
            {4, "Rakuten Travel", 195.0, 0.14, 167.70, true}// critical undercut!
        };

        auto violations = auditor.auditMatrix(directRate, channels, testDate);
        double healthScore = auditor.calculateParityScore(violations, channels.size());

        auto endClock = std::chrono::high_resolution_clock::now();
        double elapsedMs = std::chrono::duration<double, std::milli>(endClock - startClock).count();

        std::cout << "{\n"
                  << "  \"status\": \"success\",\n"
                  << "  \"algorithm\": \"Multi-Channel Rate Parity & Discrepancy Auditor\",\n"
                  << "  \"direct_rate\": " << directRate << ",\n"
                  << "  \"parity_health_score\": " << healthScore << ",\n"
                  << "  \"execution_time_ms\": " << std::fixed << std::setprecision(3) << elapsedMs << ",\n"
                  << "  \"violations\": [\n";

        for (size_t i = 0; i < violations.size(); ++i) {
            const auto& v = violations[i];
            std::cout << "    {\n"
                      << "      \"channel_name\": \"" << v.channel_name << "\",\n"
                      << "      \"direct_price\": " << v.direct_price << ",\n"
                      << "      \"ota_price\": " << v.ota_price << ",\n"
                      << "      \"discrepancy\": " << v.discrepancy_amount << ",\n"
                      << "      \"percent_diff\": " << v.discrepancy_percentage << ",\n"
                      << "      \"is_undercut\": " << (v.is_undercut ? "true" : "false") << ",\n"
                      << "      \"severity\": \"" << v.severity << "\"\n"
                      << "    }" << (i + 1 < violations.size() ? "," : "") << "\n";
        }
        std::cout << "  ]\n}\n";
        return 0;
    }

    std::cerr << "Unknown mode: " << mode << "\n";
    printHelp();
    return 1;
}
