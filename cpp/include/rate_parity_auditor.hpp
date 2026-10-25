#pragma once

#include "types.hpp"
#include <vector>
#include <string>

namespace OmniBooking {

class RateParityAuditor {
public:
    RateParityAuditor(double tolerancePercent = 1.0); // 1% tolerance for currency rounding

    /**
     * Audit single channel rate against hotel direct brand rate
     */
    ParityViolation auditChannel(
        double directRate,
        const ChannelRate& channelRate,
        const std::string& date
    ) const;

    /**
     * Audit full channel collection across room types & dates
     */
    std::vector<ParityViolation> auditMatrix(
        double directRate,
        const std::vector<ChannelRate>& channels,
        const std::string& date
    ) const;

    /**
     * Calculate Parity Health Score (0.0% - 100.0%)
     */
    double calculateParityScore(const std::vector<ParityViolation>& violations, size_t totalChecks) const;

private:
    double tolerancePercent_;
};

} // namespace OmniBooking
