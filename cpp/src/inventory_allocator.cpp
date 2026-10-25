#include "../include/inventory_allocator.hpp"
#include <algorithm>
#include <climits>
#include <cmath>

namespace OmniBooking {

AvailabilitySegmentTree::AvailabilitySegmentTree(const std::vector<int>& dailyAvailability)
    : n_(static_cast<int>(dailyAvailability.size())) {
    if (n_ > 0) {
        tree_.assign(4 * n_, 0);
        lazy_.assign(4 * n_, 0);
        build(dailyAvailability, 1, 0, n_ - 1);
    }
}

void AvailabilitySegmentTree::build(const std::vector<int>& arr, int node, int start, int end) {
    if (start == end) {
        tree_[node] = arr[start];
        return;
    }
    int mid = start + (end - start) / 2;
    build(arr, 2 * node, start, mid);
    build(arr, 2 * node + 1, mid + 1, end);
    tree_[node] = std::min(tree_[2 * node], tree_[2 * node + 1]);
}

void AvailabilitySegmentTree::pushDown(int node, int start, int end) {
    if (lazy_[node] != 0) {
        tree_[node] += lazy_[node];
        if (start != end) {
            lazy_[2 * node] += lazy_[node];
            lazy_[2 * node + 1] += lazy_[node];
        }
        lazy_[node] = 0;
    }
}

int AvailabilitySegmentTree::query(int node, int start, int end, int l, int r) const {
    if (r < start || end < l || start > end) {
        return INT_MAX;
    }
    // const_cast or non-const approach; since lazy is mutable logically, handle lazy push if needed:
    // Here we can evaluate tree_[node] + lazy_[node]
    if (l <= start && end <= r) {
        return tree_[node] + lazy_[node];
    }
    int mid = start + (end - start) / 2;
    int leftVal = query(2 * node, start, mid, l, r);
    int rightVal = query(2 * node + 1, mid + 1, end, l, r);
    return std::min(leftVal, rightVal) + lazy_[node];
}

void AvailabilitySegmentTree::updateRange(int node, int start, int end, int l, int r, int val) {
    pushDown(node, start, end);
    if (r < start || end < l) {
        return;
    }
    if (l <= start && end <= r) {
        lazy_[node] += val;
        pushDown(node, start, end);
        return;
    }
    int mid = start + (end - start) / 2;
    updateRange(2 * node, start, mid, l, r, val);
    updateRange(2 * node + 1, mid + 1, end, l, r, val);
    pushDown(2 * node, start, mid);
    pushDown(2 * node + 1, mid + 1, end);
    tree_[node] = std::min(tree_[2 * node], tree_[2 * node + 1]);
}

int AvailabilitySegmentTree::queryMin(int leftIndex, int rightIndex) const {
    if (n_ == 0 || leftIndex > rightIndex || leftIndex < 0 || rightIndex >= n_) {
        return 0;
    }
    return query(1, 0, n_ - 1, leftIndex, rightIndex);
}

bool AvailabilitySegmentTree::allocateRange(int leftIndex, int rightIndex, int units) {
    if (n_ == 0 || leftIndex > rightIndex || leftIndex < 0 || rightIndex >= n_) {
        return false;
    }
    int minAvailable = queryMin(leftIndex, rightIndex);
    if (minAvailable < units) {
        // Overbooking risk detected! Prevent allocation
        return false;
    }
    // Decrement availability
    updateRange(1, 0, n_ - 1, leftIndex, rightIndex, -units);
    return true;
}

std::vector<int> AvailabilitySegmentTree::getValues() const {
    std::vector<int> res(n_);
    for (int i = 0; i < n_; ++i) {
        res[i] = queryMin(i, i);
    }
    return res;
}

// -----------------------------------------------------------------------------
// Multi-Channel Allocator Implementation
// -----------------------------------------------------------------------------
std::vector<ChannelAllocationResult> MultiChannelAllocator::optimizeAllocation(
    int totalAvailableUnits,
    double basePrice,
    const std::vector<ChannelRate>& channels
) const {
    std::vector<ChannelAllocationResult> results;
    if (channels.empty() || totalAvailableUnits <= 0) {
        return results;
    }

    // Sort channels by net yield descending: PublishedPrice * (1 - Commission)
    struct RankedChannel {
        ChannelRate rate;
        double netYield;
    };

    std::vector<RankedChannel> ranked;
    ranked.reserve(channels.size());
    for (const auto& ch : channels) {
        double pubPrice = (ch.published_price > 0.0) ? ch.published_price : basePrice;
        double net = pubPrice * (1.0 - ch.commission_percentage);
        ranked.push_back({ch, net});
    }

    std::sort(ranked.begin(), ranked.end(), [](const RankedChannel& a, const RankedChannel& b) {
        return a.netYield > b.netYield;
    });

    int remaining = totalAvailableUnits;
    results.reserve(ranked.size());

    // Allocate with minimum baseline quota for active channels, priority to high-margin channels
    for (size_t i = 0; i < ranked.size(); ++i) {
        const auto& item = ranked[i];
        ChannelAllocationResult r;
        r.channel_id = item.rate.channel_id;
        r.channel_name = item.rate.channel_name;
        r.unit_price = (item.rate.published_price > 0.0) ? item.rate.published_price : basePrice;

        if (remaining <= 0) {
            r.allocated_units = 0;
            r.projected_revenue = 0.0;
            r.channel_margin_net = 0.0;
            results.push_back(r);
            continue;
        }

        // Top ranked channel gets larger share of remaining pool
        int share;
        if (i == ranked.size() - 1) {
            share = remaining; // Last channel takes remainder
        } else {
            // Allocate 40-50% of remaining to top channel
            share = std::max(1, static_cast<int>(std::round(remaining * 0.45)));
            share = std::min(share, remaining);
        }

        r.allocated_units = share;
        remaining -= share;
        r.projected_revenue = std::round(r.allocated_units * r.unit_price * 100.0) / 100.0;
        r.channel_margin_net = std::round(r.projected_revenue * (1.0 - item.rate.commission_percentage) * 100.0) / 100.0;
        results.push_back(r);
    }

    return results;
}

} // namespace OmniBooking
