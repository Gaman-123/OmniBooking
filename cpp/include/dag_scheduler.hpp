#pragma once

#include "types.hpp"
#include <vector>
#include <string>
#include <map>

namespace OmniBooking {

class DagScheduler {
public:
    DagScheduler() = default;

    /**
     * Add a task node to the DAG
     */
    void addTask(const DagTaskNode& task);

    /**
     * Clear all tasks
     */
    void clear();

    /**
     * Compile and solve the DAG execution plan:
     * - Validates acyclicity
     * - Kahn's algorithm topological sorting
     * - Groups into parallel execution stages
     * - Computes Critical Path Duration (in milliseconds)
     */
    DagExecutionPlan solve() const;

    /**
     * Helper to build the standard OmniBooking Fan-Out DAG for a reservation event:
     * Stage 1: Node A (DB Commit & Inventory Decrement)
     * Stage 2: Node B (Booking.com Webhook), Node C (Agoda Webhook), Node D (Rakuten Webhook)
     * Stage 3: Node E (Sync Logs Audit Trail Persistence)
     */
    static DagScheduler createStandardBookingDag(const std::vector<std::string>& channelNames);

private:
    std::vector<DagTaskNode> tasks_;
};

} // namespace OmniBooking
