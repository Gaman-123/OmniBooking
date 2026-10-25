#include "../include/dynamic_pricing.hpp"
#include <cmath>
#include <algorithm>
#include <sstream>
#include <iomanip>

namespace OmniBooking {

DynamicPricingEngine::DynamicPricingEngine(PricingParameters params)
    : params_(std::move(params)) {}

void DynamicPricingEngine::setParameters(const PricingParameters& params) {
    params_ = params;
}

const PricingParameters& DynamicPricingEngine::getParameters() const {
    return params_;
}

bool DynamicPricingEngine::isWeekend(const std::string& dateStr) const {
    // Parse YYYY-MM-DD
    int y = 2026, m = 10, d = 25;
    char dash1, dash2;
    std::istringstream ss(dateStr);
    if (ss >> y >> dash1 >> m >> dash2 >> d) {
        // Sakamoto's algorithm for day of week (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        static int t[] = {0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4};
        if (m < 3) y -= 1;
        int dow = (y + y/4 - y/100 + y/400 + t[m-1] + d) % 7;
        return (dow == 5 || dow == 6); // Friday or Saturday
    }
    return false;
}

double DynamicPricingEngine::calculateSurgeMultiplier(double occupancy) const {
    if (occupancy < 0.30) {
        // Distress low occupancy -> apply demand stimulation discount
        double discountRatio = (0.30 - occupancy) / 0.30;
        return std::max(params_.min_discount_multiplier, 1.0 - (discountRatio * 0.20));
    }

    if (occupancy <= params_.surge_threshold) {
        // Linear baseline transition between 30% and threshold (e.g. 75%)
        double ratio = (occupancy - 0.30) / (params_.surge_threshold - 0.30);
        return 1.0 + (ratio * 0.10); // up to +10%
    }

    // High occupancy (> 75%): Non-linear exponential surge curve
    double excess = (occupancy - params_.surge_threshold) / (1.0 - params_.surge_threshold);
    // Quadratic acceleration
    double surge = 1.10 + (excess * excess * (params_.max_surge_multiplier - 1.10));
    return std::min(params_.max_surge_multiplier, surge);
}

double DynamicPricingEngine::calculateLeadTimeAdjustment(int daysUntilCheckIn, double occupancy) const {
    if (daysUntilCheckIn <= 2) {
        // Last-minute booking
        if (occupancy > 0.80) {
            // High compression last-minute -> high urgency surge
            return 1.25;
        } else if (occupancy < 0.40) {
            // Unsold distress inventory -> fire sale discount
            return 0.85;
        }
        return 1.0;
    }

    if (daysUntilCheckIn >= 30) {
        // Early bird horizon -> slight advance purchase incentive
        return 0.95;
    }

    return 1.0;
}

CalculatedPrice DynamicPricingEngine::calculatePrice(const RoomInventory& inventory, int daysUntilCheckIn) const {
    CalculatedPrice result;
    result.room_type_id = inventory.room_type_id;
    result.room_type_name = inventory.room_type_name;
    result.date = inventory.date;
    result.base_price = inventory.base_price;
    result.original_price = inventory.current_price;

    double occupancy = 0.0;
    if (inventory.total_units > 0) {
        occupancy = static_cast<double>(inventory.total_units - inventory.available_rooms) / inventory.total_units;
    }
    occupancy = clamp_val(occupancy, 0.0, 1.0);

    // 1. Base occupancy surge
    double surgeMult = calculateSurgeMultiplier(occupancy);

    // 2. Weekend seasonality
    double weekendMult = isWeekend(inventory.date) ? params_.weekend_premium : 1.0;

    // 3. Lead time urgency
    double leadTimeMult = calculateLeadTimeAdjustment(daysUntilCheckIn, occupancy);

    // Combined multiplier
    double finalMultiplier = surgeMult * weekendMult * leadTimeMult;
    // Keep within safe operational bounds
    finalMultiplier = clamp_val(finalMultiplier, params_.min_discount_multiplier, params_.max_surge_multiplier);

    double calculated = inventory.base_price * finalMultiplier;
    // Round to 2 decimal places
    calculated = std::round(calculated * 100.0) / 100.0;

    result.recommended_price = calculated;
    result.surge_percentage = std::round(((calculated - inventory.base_price) / inventory.base_price) * 1000.0) / 10.0;
    
    // RevPAR calculation: Price * Occupancy Probability
    double elasticityFactor = std::max(0.5, 1.0 - ((finalMultiplier - 1.0) * (params_.price_elasticity - 1.0)));
    result.projected_occupancy = clamp_val(occupancy * elasticityFactor, 0.05, 1.0);
    result.expected_revpar = std::round((result.recommended_price * result.projected_occupancy) * 100.0) / 100.0;

    // Tier Classification & Rationale
    if (result.surge_percentage >= 50.0) {
        result.pricing_tier = "EXTREME_YIELD";
        result.rational = "High compression alert. Occupancy >= 80% with tight inventory. Maximizing RevPAR yield.";
    } else if (result.surge_percentage >= 20.0) {
        result.pricing_tier = "SURGE_TIER_2";
        result.rational = "Robust demand pattern detected. Moderate surge pricing applied.";
    } else if (result.surge_percentage > 0.0) {
        result.pricing_tier = "SURGE_TIER_1";
        result.rational = "Standard demand baseline with weekend or occupancy lift.";
    } else if (result.surge_percentage == 0.0) {
        result.pricing_tier = "BAR";
        result.rational = "Best Available Rate (BAR) maintained.";
    } else {
        result.pricing_tier = "DISTRESS_PROMO";
        result.rational = "Low occupancy stimulation discount to drive pace and volume.";
    }

    return result;
}

std::vector<CalculatedPrice> DynamicPricingEngine::computeMatrix(
    const std::vector<RoomInventory>& inventories, 
    int defaultLeadTime
) const {
    std::vector<CalculatedPrice> results;
    results.reserve(inventories.size());

    for (size_t i = 0; i < inventories.size(); ++i) {
        // Approximate lead time based on index/calendar progression
        int leadTime = std::max(1, defaultLeadTime + static_cast<int>(i % 30));
        results.push_back(calculatePrice(inventories[i], leadTime));
    }

    return results;
}

} // namespace OmniBooking
