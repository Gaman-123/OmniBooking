#pragma once

#include "types.hpp"
#include <vector>

namespace OmniBooking {

class DynamicPricingEngine {
public:
    explicit DynamicPricingEngine(PricingParameters params = PricingParameters{});

    /**
     * Compute optimized dynamic price for a single room inventory record
     */
    CalculatedPrice calculatePrice(const RoomInventory& inventory, int daysUntilCheckIn = 14) const;

    /**
     * Compute dynamic pricing across an entire matrix of room inventories
     */
    std::vector<CalculatedPrice> computeMatrix(
        const std::vector<RoomInventory>& inventories, 
        int defaultLeadTime = 14
    ) const;

    /**
     * Update runtime parameters
     */
    void setParameters(const PricingParameters& params);

    const PricingParameters& getParameters() const;

private:
    PricingParameters params_;

    bool isWeekend(const std::string& dateStr) const;
    double calculateSurgeMultiplier(double occupancy) const;
    double calculateLeadTimeAdjustment(int daysUntilCheckIn, double occupancy) const;
};

} // namespace OmniBooking
