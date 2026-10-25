#include "../include/rate_parity_auditor.hpp"
#include <cmath>
#include <algorithm>

namespace OmniBooking {

RateParityAuditor::RateParityAuditor(double tolerancePercent)
    : tolerancePercent_(tolerancePercent) {}

ParityViolation RateParityAuditor::auditChannel(
    double directRate,
    const ChannelRate& channelRate,
    const std::string& date
) const {
    ParityViolation violation;
    violation.channel_id = channelRate.channel_id;
    violation.channel_name = channelRate.channel_name;
    violation.date = date;
    violation.direct_price = directRate;
    violation.ota_price = channelRate.published_price;

    double diff = channelRate.published_price - directRate;
    violation.discrepancy_amount = std::round(std::abs(diff) * 100.0) / 100.0;

    double percentDiff = 0.0;
    if (directRate > 0.0) {
        percentDiff = (diff / directRate) * 100.0;
    }
    violation.discrepancy_percentage = std::round(percentDiff * 10.0) / 10.0;

    // Check if within tolerance
    if (std::abs(percentDiff) <= tolerancePercent_) {
        violation.severity = "IN_PARITY";
        violation.is_undercut = false;
        return violation;
    }

    if (diff < 0.0) {
        // Channel is UNDER-CUTTING the direct website! (Severe issue: steals direct bookings)
        violation.is_undercut = true;
        double absPct = std::abs(percentDiff);

        if (absPct >= 20.0 || violation.discrepancy_amount >= 40.0) {
            violation.severity = "CRITICAL";
        } else if (absPct >= 10.0 || violation.discrepancy_amount >= 20.0) {
            violation.severity = "HIGH";
        } else if (absPct >= 5.0 || violation.discrepancy_amount >= 10.0) {
            violation.severity = "MEDIUM";
        } else {
            violation.severity = "LOW";
        }
    } else {
        // Channel is over-charging (Markup disparity, lesser risk but affects conversion)
        violation.is_undercut = false;
        violation.severity = (percentDiff >= 15.0) ? "MEDIUM" : "LOW";
    }

    return violation;
}

std::vector<ParityViolation> RateParityAuditor::auditMatrix(
    double directRate,
    const std::vector<ChannelRate>& channels,
    const std::string& date
) const {
    std::vector<ParityViolation> list;
    list.reserve(channels.size());

    for (const auto& ch : channels) {
        if (!ch.is_active) continue;
        ParityViolation v = auditChannel(directRate, ch, date);
        if (v.severity != "IN_PARITY") {
            list.push_back(v);
        }
    }

    return list;
}

double RateParityAuditor::calculateParityScore(
    const std::vector<ParityViolation>& violations, 
    size_t totalChecks
) const {
    if (totalChecks == 0) return 100.0;

    double penalty = 0.0;
    for (const auto& v : violations) {
        if (v.severity == "CRITICAL") penalty += 25.0;
        else if (v.severity == "HIGH") penalty += 15.0;
        else if (v.severity == "MEDIUM") penalty += 8.0;
        else if (v.severity == "LOW") penalty += 3.0;
    }

    double rawScore = 100.0 - (penalty / static_cast<double>(totalChecks));
    return std::clamp(std::round(rawScore * 10.0) / 10.0, 0.0, 100.0);
}

} // namespace OmniBooking
