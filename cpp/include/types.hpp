#pragma once

#include <string>
#include <vector>
#include <map>
#include <cstdint>

namespace OmniBooking {

template<typename T>
inline constexpr const T& clamp_val(const T& v, const T& lo, const T& hi) {
    return (v < lo) ? lo : (hi < v) ? hi : v;
}

/**
 * Inventory snapshot for a specific room type on a given calendar date
 */
struct RoomInventory {
    int id{0};
    int room_type_id{0};
    std::string room_type_name;
    std::string date;
    int available_rooms{0};
    int total_units{0};
    double base_price{0.0};
    double current_price{0.0};
    double occupancy_rate{0.0}; // 0.0 - 1.0
};

/**
 * Parameters for the Dynamic Pricing and Yield Management Engine
 */
struct PricingParameters {
    double price_elasticity{1.45};       // Elasticity coefficient
    double surge_threshold{0.75};        // Occupancy threshold to trigger surge (75%)
    double max_surge_multiplier{2.20};   // Hard cap on surge (+120%)
    double min_discount_multiplier{0.75};// Hard floor on discount (-25%)
    double weekend_premium{1.15};        // Friday/Saturday multiplier
    double lead_time_decay{0.02};        // Urgency factor per remaining day
};

/**
 * Output of dynamic pricing computation
 */
struct CalculatedPrice {
    int room_type_id{0};
    std::string room_type_name;
    std::string date;
    double base_price{0.0};
    double original_price{0.0};
    double recommended_price{0.0};
    double surge_percentage{0.0};
    double projected_occupancy{0.0};
    double expected_revpar{0.0};
    std::string pricing_tier; // "BAR", "SURGE_HIGH", "SURGE_EXTREME", "PROMOTIONAL", "LAST_MINUTE"
    std::string rational;
};

/**
 * Channel rate details for Rate Parity auditing
 */
struct ChannelRate {
    int channel_id{0};
    std::string channel_name;
    double published_price{0.0};
    double commission_percentage{0.15}; // e.g. 15%
    double net_revenue{0.0};
    bool is_active{true};
};

/**
 * Discrepancy report for rate parity auditing
 */
struct ParityViolation {
    int channel_id{0};
    std::string channel_name;
    std::string date;
    double direct_price{0.0};
    double ota_price{0.0};
    double discrepancy_amount{0.0};
    double discrepancy_percentage{0.0};
    bool is_undercut{false}; // True if OTA is selling cheaper than direct brand site
    std::string severity;   // "LOW", "MEDIUM", "HIGH", "CRITICAL"
};

/**
 * Node in Directed Acyclic Graph (DAG) for asynchronous channel dispatcher
 */
struct DagTaskNode {
    std::string id;
    std::string label;
    std::string type; // "DB_COMMIT", "WEBHOOK_DISPATCH", "TELEMETRY_LOG", "CACHE_INVALIDATE"
    std::vector<std::string> dependencies; // IDs of tasks that must finish before this runs
    int estimated_latency_ms{10};
    int actual_latency_ms{0};
    bool is_completed{false};
};

/**
 * DAG Topological Execution Plan
 */
struct DagExecutionPlan {
    bool is_valid_dag{true};
    std::vector<std::string> topological_order;
    std::vector<std::vector<std::string>> parallel_stages;
    int critical_path_duration_ms{0};
    std::string error_message;
};

/**
 * Request for multi-channel inventory allocation
 */
struct ChannelAllocationRequest {
    int room_type_id{0};
    int total_available{0};
    std::vector<ChannelRate> channels;
};

/**
 * Result of inventory allocation optimizer
 */
struct ChannelAllocationResult {
    int channel_id{0};
    std::string channel_name;
    int allocated_units{0};
    double unit_price{0.0};
    double projected_revenue{0.0};
    double channel_margin_net{0.0};
};

} // namespace OmniBooking
