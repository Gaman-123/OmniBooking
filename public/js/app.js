$(document).ready(function() {
    let isPolling = true;

    function showToast(msg, isSuccess = true) {
        const toast = $('#action-toast');
        toast.text(msg);
        toast.css({
            'display': 'block',
            'background': isSuccess ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)',
            'color': isSuccess ? '#34d399' : '#f87171',
            'border': `1px solid ${isSuccess ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'}`
        });
        setTimeout(() => toast.fadeOut(600), 5000);
    }

    // 1. Check Database & System Health
    function checkHealth() {
        $.getJSON('/api/health', function(res) {
            if (res.database && res.database.status === 'connected') {
                $('#db-driver-label').text(`${res.database.target}`);
            } else {
                $('#db-driver-label').text('Database Fallback Mode');
            }
        }).fail(function() {
            $('#db-driver-label').text('Connecting...');
        });
    }

    // 2. Fetch live inventory matrix
    function loadMatrix() {
        $.getJSON('/api/inventory-matrix?days=7', function(res) {
            if (!res.data || !res.data.rows) return;

            const dates = res.data.dates;
            const rows = res.data.rows;

            // Render Header
            let headHtml = '<tr><th class="room-title-col">Room Type</th>';
            dates.forEach(d => {
                headHtml += `<th>${d.label}<br><span style="font-size:0.72rem; color:var(--text-muted); font-weight:normal;">${d.display}</span></th>`;
            });
            headHtml += '</tr>';
            $('#matrix-head').html(headHtml);

            // Render Rows & Cells
            let bodyHtml = '';
            let totalAvailable = 0;
            let totalPriceSum = 0;
            let totalSlots = 0;

            rows.forEach(r => {
                bodyHtml += `<tr>`;
                bodyHtml += `
                    <td class="room-title-col">
                        <div class="room-name">${r.room_type_name}</div>
                        <div class="room-meta">${r.total_units} total capacity • Base $${r.base_price.toFixed(2)}</div>
                    </td>
                `;

                r.cells.forEach(c => {
                    totalAvailable += c.available;
                    totalPriceSum += c.price;
                    totalSlots++;

                    let badgeClass = 'badge-available';
                    let label = `${c.available} left`;

                    if (c.status === 'sold_out') {
                        badgeClass = 'badge-soldout';
                        label = 'SOLD OUT';
                    } else if (c.status === 'critical') {
                        badgeClass = 'badge-critical';
                        label = `${c.available} left!`;
                    }

                    bodyHtml += `
                        <td>
                            <div class="cell-box">
                                <span class="cell-avail-badge ${badgeClass}">${label}</span>
                                <span class="cell-price">$${c.price.toFixed(2)}</span>
                                <div style="font-size:0.7rem; color:var(--text-muted);">${c.occupancy_pct}% occ</div>
                            </div>
                        </td>
                    `;
                });

                bodyHtml += `</tr>`;
            });

            $('#matrix-body').html(bodyHtml);

            if (totalSlots > 0) {
                $('#stat-adr').text(`$${(totalPriceSum / totalSlots).toFixed(2)}`);
            }
        });
    }

    // 3. Fetch OTA Webhook Sync Logs
    function loadSyncLogs() {
        $.getJSON('/api/sync-logs?limit=15', function(res) {
            if (!res.data || res.data.length === 0) return;

            $('#stat-sync-count').text(`${res.data.length} Logs`);
            let html = '';

            res.data.forEach(log => {
                const isOk = (log.response_status === 200);
                const statusBadge = isOk 
                    ? '<span class="badge-status status-200">200 OK</span>' 
                    : `<span class="badge-status" style="background:rgba(239,68,68,0.2);color:#f87171;">${log.response_status}</span>`;

                html += `
                    <tr>
                        <td><strong>${log.channel_name || 'OTA'}</strong></td>
                        <td><span style="font-family:monospace; color:#93c5fd;">${log.booking_reference || 'N/A'}</span></td>
                        <td>${statusBadge}</td>
                        <td><span class="latency-tag">${log.execution_time_ms}ms</span></td>
                        <td style="color:var(--text-muted); font-size:0.75rem;">${log.created_at || 'Just now'}</td>
                    </tr>
                `;
            });

            $('#sync-logs-body').html(html);
        });
    }

    // 4. Fetch Recent Bookings
    function loadBookings() {
        $.getJSON('/api/bookings', function(res) {
            if (!res.data || res.data.length === 0) return;

            let html = '';
            res.data.slice(0, 10).forEach(b => {
                html += `
                    <tr>
                        <td><span style="font-family:monospace; color:#38bdf8;">${b.booking_reference}</span></td>
                        <td>${b.guest_name}</td>
                        <td>${b.room_type_name || 'Room'}</td>
                        <td><span style="color:#a78bfa;">${b.channel_name || 'Direct'}</span></td>
                        <td><strong>$${parseFloat(b.total_price).toFixed(2)}</strong></td>
                    </tr>
                `;
            });

            $('#bookings-body').html(html);
        });
    }

    // Event Handlers
    $('#simulate-booking').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Processing Reservation...');

        $.post('/api/simulate-booking', {}, function(res) {
            btn.prop('disabled', false).html('<span>🏨 Simulate OTA Booking</span>');

            if (res.status === 'confirmed') {
                const b = res.booking;
                const fanOutCount = (res.dag_dispatcher && res.dag_dispatcher.worker_fan_out_nodes) 
                    ? res.dag_dispatcher.worker_fan_out_nodes.length 
                    : 0;

                showToast(`✓ Booking ${b.booking_reference} confirmed for ${b.guest_name} (${b.room_type_name})! Webhooks broadcasted to ${fanOutCount} external OTAs.`);
                loadMatrix();
                loadSyncLogs();
                loadBookings();
            } else if (res.status === 'conflict') {
                showToast(`⚠️ Conflict: ${res.message}`, false);
            } else {
                showToast(`Error: ${res.message || 'Simulation failed'}`, false);
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<span>🏨 Simulate OTA Booking</span>');
            showToast('Simulation API request failed.', false);
        });
    });

    $('#run-ai-pricing').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Calculating AI Dynamic Pricing...');

        $.post('/api/run-ai-pricing', {}, function(res) {
            btn.prop('disabled', false).html('<span>⚡ Run AI Dynamic Pricing</span>');
            showToast(`✓ Dynamic pricing recalculation complete! Updated ${res.data ? res.data.updated_count : 0} rates based on occupancy curves.`);
            loadMatrix();
        }).fail(function() {
            btn.prop('disabled', false).html('<span>⚡ Run AI Dynamic Pricing</span>');
            showToast('Failed to execute dynamic pricing.', false);
        });
    });

    $('#refresh-matrix').click(function() {
        loadMatrix();
        loadSyncLogs();
        loadBookings();
        showToast('Matrix refreshed.');
    });

    $('#view-db-details').click(function() {
        $.getJSON('/api/health', function(res) {
            const db = res.database;
            alert(
                `Database Diagnostic Telemetry:\n\n` +
                `Target: ${db.target}\n` +
                `Driver: ${db.driver}\n` +
                `Host: ${db.host}\n` +
                `Database: ${db.database}\n` +
                `Status: ${db.status}\n` +
                `Server Version: ${db.server_version || 'N/A'}\n` +
                (db.notice ? `\nNotice: ${db.notice}` : '')
            );
        });
    });

    // Initial Load
    checkHealth();
    loadMatrix();
    loadSyncLogs();
    loadBookings();

    // Auto-poll sync logs and matrix every 10 seconds
    setInterval(() => {
        if (isPolling) {
            loadSyncLogs();
        }
    }, 10000);
});
