#pragma once

#include "types.hpp"
#include <vector>
#include <memory>

namespace OmniBooking {

/**
 * Segment Tree for fast O(log N) Range Minimum Query (RMQ) & Range Availability Updates
 */
class AvailabilitySegmentTree {
public:
    explicit AvailabilitySegmentTree(const std::vector<int>& dailyAvailability);

    /**
     * Query minimum available rooms in range [leftIndex, rightIndex]
     */
    int queryMin(int leftIndex, int rightIndex) const;

    /**
     * Atomically decrement availability in range [leftIndex, rightIndex]
     * Returns true if all days had at least 'units' available, false otherwise (transaction aborted)
     */
    bool allocateRange(int leftIndex, int rightIndex, int units);

    /**
     * Get current array of daily availabilities
     */
    std::vector<int> getValues() const;

private:
    int n_;
    std::vector<int> tree_;
    std::vector<int> lazy_;

    void build(const std::vector<int>& arr, int node, int start, int end);
    void updateRange(int node, int start, int end, int l, int r, int val);
    int query(int node, int start, int end, int l, int r) const;
    void pushDown(int node, int start, int end);
};

/**
 * Multi-Channel Inventory Allocation Optimizer
 */
class MultiChannelAllocator {
public:
    MultiChannelAllocator() = default;

    /**
     * Allocate inventory across multiple OTA channels and direct booking to maximize net yield
     */
    std::vector<ChannelAllocationResult> optimizeAllocation(
        int totalAvailableUnits,
        double basePrice,
        const std::vector<ChannelRate>& channels
    ) const;
};

} // namespace OmniBooking
