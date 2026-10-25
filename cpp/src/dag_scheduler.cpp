#include "../include/dag_scheduler.hpp"
#include <queue>
#include <set>
#include <algorithm>

namespace OmniBooking {

void DagScheduler::addTask(const DagTaskNode& task) {
    tasks_.push_back(task);
}

void DagScheduler::clear() {
    tasks_.clear();
}

DagExecutionPlan DagScheduler::solve() const {
    DagExecutionPlan plan;

    if (tasks_.empty()) {
        plan.is_valid_dag = true;
        plan.critical_path_duration_ms = 0;
        return plan;
    }

    // Index mappings
    std::map<std::string, const DagTaskNode*> nodeMap;
    std::map<std::string, int> inDegree;
    std::map<std::string, std::vector<std::string>> adjList; // parent -> children

    for (const auto& task : tasks_) {
        nodeMap[task.id] = &task;
        inDegree[task.id] = 0;
    }

    for (const auto& task : tasks_) {
        for (const auto& dep : task.dependencies) {
            if (nodeMap.find(dep) != nodeMap.end()) {
                adjList[dep].push_back(task.id);
                inDegree[task.id]++;
            } else {
                plan.is_valid_dag = false;
                plan.error_message = "Missing dependency ID: " + dep + " required by " + task.id;
                return plan;
            }
        }
    }

    // Kahn's Algorithm for Topological Sort & Stage Detection
    std::queue<std::string> q;
    std::map<std::string, int> earliestFinish;

    for (const auto& pair : inDegree) {
        const std::string& id = pair.first;
        int deg = pair.second;
        if (deg == 0) {
            q.push(id);
            earliestFinish[id] = nodeMap[id]->estimated_latency_ms;
        }
    }

    std::vector<std::string> topoOrder;
    std::vector<std::vector<std::string>> stages;

    while (!q.empty()) {
        size_t stageSize = q.size();
        std::vector<std::string> currentStage;

        for (size_t i = 0; i < stageSize; ++i) {
            std::string curr = q.front();
            q.pop();
            topoOrder.push_back(curr);
            currentStage.push_back(curr);

            for (const auto& neighbor : adjList[curr]) {
                int candidateFinish = earliestFinish[curr] + nodeMap[neighbor]->estimated_latency_ms;
                earliestFinish[neighbor] = std::max(earliestFinish[neighbor], candidateFinish);

                inDegree[neighbor]--;
                if (inDegree[neighbor] == 0) {
                    q.push(neighbor);
                }
            }
        }

        if (!currentStage.empty()) {
            stages.push_back(currentStage);
        }
    }

    // Cycle check
    if (topoOrder.size() != tasks_.size()) {
        plan.is_valid_dag = false;
        plan.error_message = "Cycle detected in task dependency graph! Acyclic constraint violated.";
        return plan;
    }

    // Critical Path Duration: Max earliestFinish across all terminal nodes
    int maxDuration = 0;
    for (const auto& pair : earliestFinish) {
        int finishTime = pair.second;
        maxDuration = std::max(maxDuration, finishTime);
    }

    plan.is_valid_dag = true;
    plan.topological_order = std::move(topoOrder);
    plan.parallel_stages = std::move(stages);
    plan.critical_path_duration_ms = maxDuration;
    return plan;
}

DagScheduler DagScheduler::createStandardBookingDag(const std::vector<std::string>& channelNames) {
    DagScheduler scheduler;

    // Node A: Primary ACID Database Commitment & Inventory Decrement
    DagTaskNode nodeA;
    nodeA.id = "node_a_db_commit";
    nodeA.label = "Node A: Database Transaction & Inventory Lock";
    nodeA.type = "DB_COMMIT";
    nodeA.estimated_latency_ms = 8;
    scheduler.addTask(nodeA);

    // Fan-Out Nodes (Node B, C, D...): External OTA Webhooks
    std::vector<std::string> fanOutIds;
    int idx = 1;
    for (const auto& name : channelNames) {
        DagTaskNode webhookNode;
        webhookNode.id = "node_fanout_" + std::to_string(idx++);
        webhookNode.label = "Webhook: Fan-Out to " + name;
        webhookNode.type = "WEBHOOK_DISPATCH";
        webhookNode.dependencies = {"node_a_db_commit"};
        // Simulated realistic network latencies
        webhookNode.estimated_latency_ms = 25 + (idx * 5);
        scheduler.addTask(webhookNode);
        fanOutIds.push_back(webhookNode.id);
    }

    // Node End: Telemetry & Audit Trail Sync Log
    DagTaskNode nodeAudit;
    nodeAudit.id = "node_audit_telemetry";
    nodeAudit.label = "Node Audit: Record JSON Telemetry & Sync Logs";
    nodeAudit.type = "TELEMETRY_LOG";
    nodeAudit.dependencies = fanOutIds; // Runs after all webhooks finish
    nodeAudit.estimated_latency_ms = 4;
    scheduler.addTask(nodeAudit);

    return scheduler;
}

} // namespace OmniBooking
