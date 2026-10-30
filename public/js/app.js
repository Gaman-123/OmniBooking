/* =====================================================================
   Omni Booking — dashboard logic (jQuery + REST)
   All API calls are same-origin (BASE_URL = '').
   ===================================================================== */

(function ($) {
    'use strict';

    const BASE_URL = '';
    const REFRESH_MS = 10000;          // auto-refresh for bookings + sync log
    const SYNC_LOG_LIMIT = 500;        // fetched for the KPI count; table shows the newest 25
    const SYNC_LOG_ROWS = 25;

    const state = {
        days: 7,
        prevPrices: {},                // "roomTypeId|date" -> price, to highlight changes
        knownBookingIds: null,         // Set of booking ids already shown
        knownLogIds: null,
        users: [],
        pollTimer: null,
        currentUser: null
    };

    /* ------------------------------------------------------------------
       Helpers
       ------------------------------------------------------------------ */

    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function money(n, decimals) {
        const num = Number(n);
        if (!isFinite(num)) return '—';
        const d = decimals === undefined ? 2 : decimals;
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    function fmtNum(n) {
        const num = Number(n);
        return isFinite(num) ? num.toLocaleString('en-US') : '—';
    }

    // "2026-10-25 09:46:16" or ISO -> "Oct 25, 09:46" (no timezone conversion)
    function fmtDateTime(value) {
        if (!value) return '—';
        const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (!m) return esc(value);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[parseInt(m[2], 10) - 1] + ' ' + parseInt(m[3], 10) + ', ' + m[4] + ':' + m[5];
    }

    function fmtDate(value) {
        if (!value) return '—';
        const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return esc(value);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[parseInt(m[2], 10) - 1] + ' ' + parseInt(m[3], 10);
    }

    function initials(name) {
        return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (p) { return p[0]; }).join('').toUpperCase();
    }

    function titleCase(s) {
        s = String(s || '');
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    /** JSON request wrapper. Returns a jQuery promise. */
    function api(method, url, data) {
        const opts = {
            url: BASE_URL + url,
            method: method,
            dataType: 'json',
            timeout: 20000
        };
        if (data !== undefined) {
            opts.contentType = 'application/json';
            opts.data = JSON.stringify(data);
        }
        return $.ajax(opts);
    }

    /** Best human-readable message from a failed request. */
    function errorMessage(xhr, fallback) {
        if (!xhr || xhr.status === 0) {
            return 'Can’t reach the server. Check that the backend is running on 127.0.0.1:8000.';
        }
        const body = xhr.responseJSON || {};
        if (Array.isArray(body.errors) && body.errors.length) return body.errors.join(' ');
        if (body.message) return body.message;
        if (xhr.status === 404) return 'That endpoint wasn’t found on the server (404).';
        if (xhr.status >= 500) return 'The server hit an error (' + xhr.status + '). Check the PHP logs.';
        return fallback || 'Request failed (' + xhr.status + ').';
    }

    function setLoading($btn, on) {
        $btn.prop('disabled', on).toggleClass('is-loading', on).attr('aria-busy', on ? 'true' : null);
    }

    function emptyRow(cols, text, cls) {
        return '<tr><td colspan="' + cols + '" class="' + (cls || 'empty-state') + '">' + esc(text) + '</td></tr>';
    }

    function skeletonRows(cols, rows) {
        let html = '';
        for (let r = 0; r < rows; r++) {
            html += '<tr>';
            for (let c = 0; c < cols; c++) html += '<td><span class="skeleton"></span></td>';
            html += '</tr>';
        }
        return html;
    }

    /* ------------------------------------------------------------------
       Toasts
       ------------------------------------------------------------------ */

    function showToast(message, type) {
        type = type || 'info';
        const $toast = $(
            '<div class="toast ' + esc(type) + '" role="' + (type === 'error' ? 'alert' : 'status') + '">' +
                '<span class="msg"></span>' +
                '<button type="button" aria-label="Dismiss">&times;</button>' +
            '</div>'
        );
        $toast.find('.msg').text(message);
        $('#toast-stack').append($toast);

        const $stack = $('#toast-stack .toast');
        if ($stack.length > 4) dismissToast($stack.first());

        const timer = setTimeout(function () { dismissToast($toast); }, type === 'error' ? 7000 : 4500);
        $toast.find('button').on('click', function () {
            clearTimeout(timer);
            dismissToast($toast);
        });
    }

    function dismissToast($toast) {
        $toast.addClass('is-leaving');
        setTimeout(function () { $toast.remove(); }, 220);
    }

    /* ------------------------------------------------------------------
       Modal
       ------------------------------------------------------------------ */

    function openModal(innerHtml, size) {
        closeModal();
        const $backdrop = $(
            '<div class="modal-backdrop">' +
                '<div class="modal section-card ' + (size || '') + '" role="dialog" aria-modal="true">' + innerHtml + '</div>' +
            '</div>'
        );
        $('#modal-root').append($backdrop);
        $backdrop.on('mousedown', function (e) { if (e.target === this) closeModal(); });
        $backdrop.on('click', '[data-close]', closeModal);
        setTimeout(function () {
            const $f = $backdrop.find('input, select, button').filter(':visible').first();
            ($f.length ? $f : $backdrop.find('.modal')).trigger('focus');
        }, 30);
        return $backdrop;
    }

    function closeModal() {
        $('#modal-root').empty();
    }

    function modalHead(title, sub) {
        return '<div class="card-head"><div><h2>' + esc(title) + '</h2>' +
            (sub ? '<p>' + esc(sub) + '</p>' : '') +
            '</div><button type="button" class="btn-ghost btn-sm" data-close aria-label="Close">Close</button></div>';
    }

    /** Promise-like confirm dialog. */
    function confirmDialog(title, body, confirmLabel, danger) {
        const d = $.Deferred();
        const $m = openModal(
            modalHead(title) +
            '<p style="margin:0 0 18px;color:var(--text-secondary)">' + esc(body) + '</p>' +
            '<div class="card-actions" style="justify-content:flex-end">' +
                '<button type="button" class="btn-outline" data-close>Cancel</button>' +
                '<button type="button" class="' + (danger ? 'btn-danger' : 'btn-primary') + '" id="confirm-ok">' + esc(confirmLabel) + '</button>' +
            '</div>', 'sm'
        );
        $m.find('#confirm-ok').on('click', function () { closeModal(); d.resolve(); });
        $m.find('[data-close]').on('click', function () { d.reject(); });
        return d.promise();
    }

    /* ------------------------------------------------------------------
       Health + engine status
       ------------------------------------------------------------------ */

    let lastHealth = null;

    function checkHealth() {
        const $badge = $('#db-health-badge').removeClass('is-ok is-warn is-bad');
        return api('GET', '/api/health').done(function (res) {
            lastHealth = res;
            const db = res.database || {};
            if (db.status !== 'connected') {
                $badge.addClass('is-bad');
                $('#db-driver-label').text('Database disconnected');
            } else if (db.driver === 'mysql') {
                $badge.addClass('is-ok');
                $('#db-driver-label').text('MySQL connected');
            } else if (db.driver === 'pgsql') {
                $badge.addClass('is-ok');
                $('#db-driver-label').text('PostgreSQL connected');
            } else {
                $badge.addClass('is-warn');
                $('#db-driver-label').text('SQLite fallback');
            }
        }).fail(function () {
            lastHealth = null;
            $badge.addClass('is-bad');
            $('#db-driver-label').text('Server unreachable');
        });
    }

    function showDbDetails() {
        if (!lastHealth) {
            showToast('Can’t reach the server. Check that the backend is running.', 'error');
            return;
        }
        const db = lastHealth.database || {};
        const rows = [
            ['Status', db.status],
            ['Engine', db.target],
            ['Driver', db.driver],
            ['Host', db.host],
            ['Database', db.database],
            ['Server version', db.server_version],
            ['Checked at', fmtDateTime(lastHealth.timestamp)]
        ];
        let html = modalHead('Database connection') + '<table class="data-table"><tbody>';
        rows.forEach(function (r) {
            html += '<tr><td class="muted nowrap">' + esc(r[0]) + '</td><td>' + esc(r[1] || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        if (db.notice || db.error) {
            html += '<div class="notice" style="margin-top:14px">' + esc(db.error || db.notice) + '</div>';
        }
        openModal(html, 'sm');
    }

    function checkEngine() {
        const $badge = $('#cpp-status-badge').removeClass('is-ok is-warn is-bad');
        return api('GET', '/api/cpp/status').done(function (res) {
            if (res.status === 'online') {
                $badge.addClass('is-ok');
                $('#cpp-status-label').text('C++ engine online');
                $badge.attr('title', (res.capabilities || []).join('\n'));
            } else {
                $badge.addClass('is-warn');
                $('#cpp-status-label').text('C++ engine offline');
                $badge.attr('title', res.error || 'Run php artisan cpp:build');
            }
        }).fail(function () {
            $badge.addClass('is-bad');
            $('#cpp-status-label').text('Engine unavailable');
        });
    }

    /* ------------------------------------------------------------------
       Availability & price matrix
       ------------------------------------------------------------------ */

    function renderMatrix(options) {
        options = options || {};
        const showDeltas = !!options.showDeltas;

        return api('GET', '/api/inventory-matrix?days=' + state.days).done(function (res) {
            const data = res.data || {};
            const dates = data.dates || [];
            const rows = data.rows || [];

            if (dates.length) {
                $('#matrix-range').text(
                    fmtDate(dates[0].date) + ' to ' + fmtDate(dates[dates.length - 1].date) +
                    ' (' + dates.length + ' nights)'
                );
            }

            // Header
            let head = '<tr><th scope="col">Room type</th>';
            dates.forEach(function (d, i) {
                const weekend = d.day_of_week === 5 || d.day_of_week === 6;
                head += '<th scope="col" class="' + (i === 0 ? 'is-today ' : '') + (weekend ? 'is-weekend' : '') + '"' +
                    (weekend ? ' title="Weekend"' : '') + '>' +
                    '<span class="day">' + esc(d.label) + '</span>' + esc(d.display) + '</th>';
            });
            head += '</tr>';
            $('#matrix-head').html(head);

            if (!rows.length) {
                $('#matrix-body').html(emptyRow(dates.length + 1,
                    'No room types yet. Seed the database (POST /api/seed) to load sample rooms.'));
                updateMatrixStats(rows);
                return;
            }

            const nextPrices = {};
            let body = '';
            rows.forEach(function (r) {
                body += '<tr><th scope="row" class="room-name">' + esc(r.room_type_name) +
                    '<small>' + fmtNum(r.total_units) + ' units, base ' + money(r.base_price, 0) + '</small></th>';

                (r.cells || []).forEach(function (c) {
                    const key = r.room_type_id + '|' + c.date;
                    const price = Number(c.price);
                    const prev = state.prevPrices[key];
                    nextPrices[key] = price;

                    let delta = '';
                    let changed = false;
                    if (showDeltas && prev !== undefined && Math.abs(prev - price) >= 0.01) {
                        changed = true;
                        const diff = price - prev;
                        delta = '<span class="delta ' + (diff > 0 ? 'up' : 'down') + '">' +
                            (diff > 0 ? '+' : '−') + money(Math.abs(diff), 0) + '</span>';
                    }

                    const status = ['available', 'critical', 'sold_out'].indexOf(c.status) >= 0 ? c.status : 'available';
                    const occ = Math.max(0, Math.min(100, Number(c.occupancy_pct) || 0));
                    const statusText = status === 'sold_out' ? 'Sold out' : (status === 'critical' ? 'Almost full' : 'Available');

                    body += '<td class="matrix-cell status-' + status + (changed ? ' is-changed' : '') + '"' +
                        ' title="' + esc(r.room_type_name + ', ' + c.date + ': ' + statusText) + '">' +
                        '<div class="price">' + money(price, 0) + delta + '</div>' +
                        '<div class="units">' + fmtNum(c.available) + ' / ' + fmtNum(c.total_units) + ' left</div>' +
                        '<div class="occ">' + occ + '% occupied</div>' +
                        '<span class="fill" style="width:' + occ + '%"></span>' +
                    '</td>';
                });
                body += '</tr>';
            });

            $('#matrix-body').html(body);
            state.prevPrices = nextPrices;
            updateMatrixStats(rows);
        }).fail(function (xhr) {
            $('#matrix-body').html(emptyRow(state.days + 1, errorMessage(xhr, 'Couldn’t load availability.'), 'error-state'));
        });
    }

    function updateMatrixStats(rows) {
        let units = 0, priceSum = 0, cells = 0, available = 0;
        rows.forEach(function (r) {
            units += Number(r.total_units) || 0;
            (r.cells || []).forEach(function (c) {
                priceSum += Number(c.price) || 0;
                available += Number(c.available) || 0;
                cells++;
            });
        });
        $('#stat-total-rooms').text(fmtNum(units));
        $('#stat-room-types').text(rows.length + ' room type' + (rows.length === 1 ? '' : 's'));
        $('#stat-adr').text(cells ? money(priceSum / cells) : '—');
        $('#stat-adr-sub').text('Mean over the next ' + state.days + ' days');
    }

    /* ------------------------------------------------------------------
       Bookings feed
       ------------------------------------------------------------------ */

    function loadBookings() {
        return api('GET', '/api/bookings').done(function (res) {
            const list = Array.isArray(res.data) ? res.data : [];
            if (!list.length) {
                $('#bookings-body').html(emptyRow(5, 'No bookings yet. Use “Simulate OTA booking” to create one.'));
                state.knownBookingIds = new Set();
                return;
            }

            const firstLoad = state.knownBookingIds === null;
            const seen = new Set();
            let html = '';
            list.forEach(function (b) {
                seen.add(b.id);
                const isNew = !firstLoad && !state.knownBookingIds.has(b.id);
                const guest = b.user_full_name
                    ? esc(b.guest_name || b.user_full_name) + '<span class="cell-sub">Account: ' + esc(b.user_full_name) + '</span>'
                    : esc(b.guest_name || 'Guest');
                html += '<tr' + (isNew ? ' class="is-new"' : '') + '>' +
                    '<td class="ref">' + esc(b.booking_reference) +
                        '<span class="cell-sub">' + fmtDate(b.check_in) + ' to ' + fmtDate(b.check_out) + '</span></td>' +
                    '<td>' + guest + '</td>' +
                    '<td>' + esc(b.room_type_name || '—') + '</td>' +
                    '<td>' + esc(b.channel_name || 'Direct') + '</td>' +
                    '<td class="right num"><strong>' + money(b.total_price) + '</strong></td>' +
                '</tr>';
            });
            $('#bookings-body').html(html);
            state.knownBookingIds = seen;
        }).fail(function (xhr) {
            $('#bookings-body').html(emptyRow(5, errorMessage(xhr, 'Couldn’t load bookings.'), 'error-state'));
        });
    }

    /* ------------------------------------------------------------------
       OTA sync log
       ------------------------------------------------------------------ */

    function loadSyncLogs() {
        return api('GET', '/api/sync-logs?limit=' + SYNC_LOG_LIMIT).done(function (res) {
            const logs = Array.isArray(res.data) ? res.data : [];

            // KPI: total syncs + distinct channels
            const count = logs.length;
            $('#stat-sync-count').text(count >= SYNC_LOG_LIMIT ? fmtNum(SYNC_LOG_LIMIT) + '+' : fmtNum(count));
            const ok = logs.filter(function (l) { return Number(l.response_status) >= 200 && Number(l.response_status) < 300; }).length;
            $('#stat-sync-sub').text(count ? Math.round((ok / count) * 100) + '% delivered successfully' : 'Webhooks sent');

            const channels = new Set();
            logs.forEach(function (l) { if (l.channel_name) channels.add(l.channel_name); });
            $('#stat-channels-count').text(channels.size ? fmtNum(channels.size) : '—');
            $('#stat-channels-sub').text(channels.size ? Array.from(channels).join(', ') : 'Shown after the first booking');

            if (!logs.length) {
                $('#sync-logs-body').html(emptyRow(5, 'No channel updates yet. They appear after a booking is made.'));
                state.knownLogIds = new Set();
                return;
            }

            const firstLoad = state.knownLogIds === null;
            const seen = new Set();
            let html = '';
            logs.slice(0, SYNC_LOG_ROWS).forEach(function (l) {
                seen.add(l.id);
                const isNew = !firstLoad && !state.knownLogIds.has(l.id);
                const code = Number(l.response_status);
                const okCode = code >= 200 && code < 300;
                html += '<tr' + (isNew ? ' class="is-new"' : '') + '>' +
                    '<td>' + esc(l.channel_name || 'OTA') + '</td>' +
                    '<td class="ref">' + esc(l.booking_reference || '—') + '</td>' +
                    '<td><span class="chip ' + (okCode ? 'chip-success' : 'chip-error') + '">' +
                        (code ? esc(code) + (okCode ? ' OK' : ' Failed') : 'No response') + '</span></td>' +
                    '<td class="right num">' + (l.execution_time_ms !== null && l.execution_time_ms !== undefined ? esc(l.execution_time_ms) + ' ms' : '—') + '</td>' +
                    '<td class="nowrap muted">' + fmtDateTime(l.created_at) + '</td>' +
                '</tr>';
            });
            $('#sync-logs-body').html(html);
            state.knownLogIds = seen;
        }).fail(function (xhr) {
            $('#sync-logs-body').html(emptyRow(5, errorMessage(xhr, 'Couldn’t load the sync log.'), 'error-state'));
        });
    }

    /* ------------------------------------------------------------------
       Actions: simulate booking + AI pricing
       ------------------------------------------------------------------ */

    function simulateBooking() {
        const $btn = $('#simulate-booking');
        setLoading($btn, true);
        api('POST', '/api/simulate-booking').done(function (res) {
            if (res.status === 'confirmed' && res.booking) {
                const b = res.booking;
                const fanOut = (res.dag_dispatcher && res.dag_dispatcher.worker_fan_out_nodes) || [];
                showToast('Booking ' + b.booking_reference + ' confirmed for ' + b.guest_name +
                    ' (' + (b.room_type_name || 'room') + ' via ' + (b.channel_name || 'direct') + '). ' +
                    fanOut.length + ' channel' + (fanOut.length === 1 ? '' : 's') + ' updated.', 'success');
                refreshAfterChange();
            } else if (res.status === 'conflict') {
                showToast('Booking rejected: ' + (res.message || 'no rooms left for those dates.'), 'error');
                renderMatrix();
            } else {
                showToast(res.message || 'The booking couldn’t be created.', 'error');
            }
        }).fail(function (xhr) {
            showToast(errorMessage(xhr, 'The booking couldn’t be created.'), 'error');
        }).always(function () {
            setLoading($btn, false);
        });
    }

    function runAiPricing() {
        const $btn = $('#run-ai-pricing');
        setLoading($btn, true);
        api('POST', '/api/run-ai-pricing?days=' + state.days).done(function (res) {
            const d = res.data || {};
            showToast('Prices updated for ' + fmtNum(d.updated_count || 0) + ' room-nights' +
                (d.engine ? ' using ' + d.engine : '') + '. Changed prices are highlighted.', 'success');
            renderMatrix({ showDeltas: true });
        }).fail(function (xhr) {
            showToast(errorMessage(xhr, 'Pricing couldn’t be updated.'), 'error');
        }).always(function () {
            setLoading($btn, false);
        });
    }

    function refreshAfterChange() {
        renderMatrix();
        loadBookings();
        loadSyncLogs();
    }

    /* ------------------------------------------------------------------
       Users
       ------------------------------------------------------------------ */

    function loadUsers() {
        if (!state.users.length) $('#users-body').html(skeletonRows(7, 3));
        return api('GET', '/api/users').done(function (res) {
            const users = Array.isArray(res.users) ? res.users : [];
            state.users = users;

            const active = users.filter(function (u) { return Number(u.is_active) === 1; }).length;
            $('#stat-users-count').text(fmtNum(users.length));
            $('#stat-users-sub').text(users.length ? active + ' active' : 'Guest and staff accounts');

            if (!users.length) {
                $('#users-body').html(emptyRow(7, 'No users yet. Create the first account with the form.'));
                return;
            }

            let html = '';
            users.forEach(function (u) {
                const isActive = Number(u.is_active) === 1;
                const role = ['guest', 'staff', 'admin'].indexOf(u.role) >= 0 ? u.role : 'guest';
                const roleChip = role === 'admin' ? 'chip-info' : (role === 'staff' ? 'chip-warning' : '');
                html += '<tr class="' + (isActive ? '' : 'is-inactive') + '" data-user-id="' + esc(u.id) + '">' +
                    '<td class="num muted">' + esc(u.id) + '</td>' +
                    '<td><strong>' + esc(u.name) + '</strong><span class="cell-sub">' + esc(u.email) + '</span></td>' +
                    '<td class="nowrap">' + (u.phone ? esc(u.phone) : '<span class="muted">—</span>') + '</td>' +
                    '<td><span class="chip ' + roleChip + '">' + esc(titleCase(role)) + '</span></td>' +
                    '<td><span class="chip ' + (isActive ? 'chip-success' : 'chip-error') + '">' + (isActive ? 'Active' : 'Deactivated') + '</span></td>' +
                    '<td class="nowrap muted">' + fmtDate(u.created_at) + '</td>' +
                    '<td class="actions">' +
                        '<button type="button" class="btn-ghost btn-sm" data-action="bookings">Bookings</button>' +
                        '<button type="button" class="btn-ghost btn-sm" data-action="edit">Edit</button>' +
                        (isActive
                            ? '<button type="button" class="btn-ghost btn-sm danger" data-action="deactivate">Deactivate</button>'
                            : '<button type="button" class="btn-ghost btn-sm" data-action="reactivate">Reactivate</button>') +
                    '</td>' +
                '</tr>';
            });
            $('#users-body').html(html);
        }).fail(function (xhr) {
            $('#users-body').html(emptyRow(7, errorMessage(xhr, 'Couldn’t load users.'), 'error-state'));
            $('#stat-users-count').text('—');
        });
    }

    function findUser(id) {
        return state.users.find(function (u) { return String(u.id) === String(id); });
    }

    function showFormError($el, messages) {
        if (!messages || !messages.length) {
            $el.addClass('hidden').empty();
            return;
        }
        if (messages.length === 1) {
            $el.text(messages[0]);
        } else {
            $el.html('<ul>' + messages.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>');
        }
        $el.removeClass('hidden');
    }

    function createUser(e) {
        e.preventDefault();
        const $form = $('#create-user-form');
        const $btn = $form.find('button[type="submit"]');
        const $err = $('#create-user-error');

        const payload = {
            name: $.trim($('#user-name').val()),
            email: $.trim($('#user-email').val()).toLowerCase(),
            password: $('#user-password').val(),
            phone: $.trim($('#user-phone').val()) || null,
            role: $('#user-role').val() || 'guest'
        };

        // Client-side checks (the server validates too)
        const problems = [];
        if (!payload.name) problems.push('Enter a full name.');
        if (!payload.email) problems.push('Enter an email address.');
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) problems.push('Enter a valid email address.');
        if (!payload.password) problems.push('Enter a password.');
        if (problems.length) {
            showFormError($err, problems);
            return;
        }
        showFormError($err, null);
        setLoading($btn, true);

        api('POST', '/api/users', payload).done(function (res) {
            showToast('User created: ' + ((res.user && res.user.name) || payload.name), 'success');
            $form[0].reset();
            loadUsers();
        }).fail(function (xhr) {
            const body = xhr.responseJSON || {};
            showFormError($err, Array.isArray(body.errors) && body.errors.length
                ? body.errors
                : [errorMessage(xhr, 'The user couldn’t be created.')]);
        }).always(function () {
            setLoading($btn, false);
        });
    }

    function login(e) {
        e.preventDefault();
        const $btn = $('#login-form button[type="submit"]');
        const $err = $('#login-error');
        const email = $.trim($('#login-email').val());
        const password = $('#login-password').val();

        if (!email || !password) {
            showFormError($err, ['Enter your email and password.']);
            return;
        }
        showFormError($err, null);
        setLoading($btn, true);

        api('POST', '/api/users/login', { email: email, password: password }).done(function (res) {
            try { sessionStorage.setItem('current_user', JSON.stringify(res.user)); } catch (ex) { /* storage blocked */ }
            renderUserPanel(res.user);
            showToast(res.message || 'Signed in.', 'success');
            $('#login-form')[0].reset();
        }).fail(function (xhr) {
            showFormError($err, [xhr.status === 401
                ? 'That email and password don’t match an account.'
                : errorMessage(xhr, 'Sign-in failed.')]);
        }).always(function () {
            setLoading($btn, false);
        });
    }

    function renderUserPanel(user) {
        state.currentUser = user || null;
        if (!user) {
            $('#user-panel, #header-user-badge').addClass('hidden');
            return;
        }
        const init = initials(user.name);
        $('#user-panel-initial, #header-user-initial').text(init);
        $('#user-panel-name').text(user.name);
        $('#user-panel-meta').text(titleCase(user.role) + ' \u2022 ' + user.email).attr('title', user.email);
        $('#user-panel-name').attr('title', user.email);
        $('#header-user-name').text(user.name);
        $('#user-panel, #header-user-badge').removeClass('hidden');
    }

    function logout() {
        try { sessionStorage.removeItem('current_user'); } catch (ex) { /* ignore */ }
        renderUserPanel(null);
        showToast('Signed out.', 'info');
    }

    function restoreSession() {
        try {
            const raw = sessionStorage.getItem('current_user');
            if (raw) renderUserPanel(JSON.parse(raw));
        } catch (ex) { /* ignore */ }
    }

    function showUserBookings(user) {
        const $m = openModal(
            modalHead('Bookings for ' + user.name, user.email) +
            '<div class="table-scroll"><table class="data-table"><thead><tr>' +
                '<th>Reference</th><th>Stay</th><th>Room type</th><th class="right">Total</th><th>Status</th>' +
            '</tr></thead><tbody id="user-bookings-body">' + skeletonRows(5, 3) + '</tbody></table></div>'
        );

        api('GET', '/api/users/' + encodeURIComponent(user.id) + '/bookings').done(function (res) {
            const list = Array.isArray(res.bookings) ? res.bookings : [];
            if (!list.length) {
                $m.find('#user-bookings-body').html(emptyRow(5, user.name + ' has no bookings yet.'));
                return;
            }
            let html = '';
            list.forEach(function (b) {
                const st = String(b.status || '').toLowerCase();
                const chip = st === 'confirmed' ? 'chip-success' : (st === 'cancelled' ? 'chip-error' : 'chip-info');
                html += '<tr>' +
                    '<td class="ref">' + esc(b.booking_reference) + '</td>' +
                    '<td class="nowrap">' + fmtDate(b.check_in) + ' to ' + fmtDate(b.check_out) + '</td>' +
                    '<td>' + esc(b.room_type_name || '—') + '</td>' +
                    '<td class="right num">' + money(b.total_price) + '</td>' +
                    '<td><span class="chip ' + chip + '">' + esc(titleCase(st || 'unknown')) + '</span></td>' +
                '</tr>';
            });
            $m.find('#user-bookings-body').html(html);
        }).fail(function (xhr) {
            $m.find('#user-bookings-body').html(emptyRow(5, errorMessage(xhr, 'Couldn’t load this user’s bookings.'), 'error-state'));
        });
    }

    function editUser(user) {
        const roles = ['guest', 'staff', 'admin'];
        const $m = openModal(
            modalHead('Edit user', user.email) +
            '<form id="edit-user-form" class="form-stack" novalidate>' +
                '<div class="field"><label for="edit-name">Full name</label>' +
                    '<input id="edit-name" type="text" value="' + esc(user.name) + '" required></div>' +
                '<div class="field-row">' +
                    '<div class="field"><label for="edit-phone">Phone</label>' +
                        '<input id="edit-phone" type="tel" value="' + esc(user.phone || '') + '"></div>' +
                    '<div class="field"><label for="edit-role">Role</label><select id="edit-role">' +
                        roles.map(function (r) {
                            return '<option value="' + r + '"' + (user.role === r ? ' selected' : '') + '>' + titleCase(r) + '</option>';
                        }).join('') +
                    '</select></div>' +
                '</div>' +
                '<div class="form-error hidden" id="edit-user-error" role="alert"></div>' +
                '<div class="card-actions" style="justify-content:flex-end">' +
                    '<button type="button" class="btn-outline" data-close>Cancel</button>' +
                    '<button type="submit" class="btn-primary">Save changes</button>' +
                '</div>' +
            '</form>', 'sm'
        );

        $m.find('#edit-user-form').on('submit', function (e) {
            e.preventDefault();
            const name = $.trim($m.find('#edit-name').val());
            const $err = $m.find('#edit-user-error');
            if (!name) { showFormError($err, ['Enter a full name.']); return; }

            const $btn = $(this).find('button[type="submit"]');
            setLoading($btn, true);
            api('PUT', '/api/users/' + encodeURIComponent(user.id), {
                name: name,
                phone: $.trim($m.find('#edit-phone').val()) || null,
                role: $m.find('#edit-role').val()
            }).done(function () {
                closeModal();
                showToast('Changes saved for ' + name + '.', 'success');
                loadUsers();
            }).fail(function (xhr) {
                showFormError($err, [errorMessage(xhr, 'Changes couldn’t be saved.')]);
                setLoading($btn, false);
            });
        });
    }

    function deactivateUser(user, $btn) {
        confirmDialog(
            'Deactivate ' + user.name + '?',
            'They won’t be able to sign in. Their booking history is kept, and you can reactivate them later.',
            'Deactivate', true
        ).done(function () {
            setLoading($btn, true);
            api('DELETE', '/api/users/' + encodeURIComponent(user.id)).done(function (res) {
                showToast(res.message || (user.name + ' deactivated.'), 'success');
                loadUsers();
            }).fail(function (xhr) {
                showToast(errorMessage(xhr, 'The user couldn’t be deactivated.'), 'error');
                setLoading($btn, false);
            });
        });
    }

    function reactivateUser(user, $btn) {
        setLoading($btn, true);
        api('PUT', '/api/users/' + encodeURIComponent(user.id), { is_active: 1 }).done(function () {
            showToast(user.name + ' reactivated.', 'success');
            loadUsers();
        }).fail(function (xhr) {
            showToast(errorMessage(xhr, 'The user couldn’t be reactivated.'), 'error');
            setLoading($btn, false);
        });
    }

    /* ------------------------------------------------------------------
       C++ engine panel
       ------------------------------------------------------------------ */

    const ENGINE_TITLES = {
        'benchmark': 'Benchmark',
        'pricing': 'Dynamic pricing',
        'dag-solve': 'DAG solver',
        'rate-parity': 'Rate parity audit',
        'allocate': 'Channel allocation'
    };

    function runEngine(action, $btn) {
        $('.engine-btn').attr('aria-pressed', 'false');
        $btn.attr('aria-pressed', 'true');
        setLoading($btn, true);

        api('POST', '/api/cpp/' + action).done(function (res) {
            renderEngineOutput(action, res);
            const out = document.getElementById('engine-output');
            if (out && out.getBoundingClientRect().top > window.innerHeight) {
                out.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }).fail(function (xhr) {
            $('#engine-output').html('<div class="notice">' + esc(errorMessage(xhr, 'The engine request failed.')) + '</div>');
            showToast(errorMessage(xhr, 'The engine request failed.'), 'error');
        }).always(function () {
            setLoading($btn, false);
        });
    }

    function metric(label, value) {
        return '<div class="metric"><div class="stat-label">' + esc(label) + '</div><div class="v">' + value + '</div></div>';
    }

    function renderEngineOutput(action, res) {
        const $out = $('#engine-output');
        let html = '<h3>' + esc(ENGINE_TITLES[action] || action) + '</h3>';

        // Offline / fallback / error states
        if (res.status === 'fallback' || res.status === 'offline') {
            html += '<div class="notice">The C++ engine isn’t compiled on this server, so nothing ran. ' +
                'Build it with <code>php artisan cpp:build</code>, then try again.</div>';
            $out.html(html + rawJson(res));
            showToast('C++ engine is offline. Run php artisan cpp:build.', 'error');
            return;
        }
        if (res.status === 'error' || res.status === 'raw_output') {
            html += '<div class="notice">The engine returned an unexpected result' +
                (res.exit_code !== undefined ? ' (exit code ' + esc(res.exit_code) + ')' : '') + '. See the raw output below.</div>';
            $out.html(html + rawJson(res));
            showToast('The engine returned an unexpected result.', 'error');
            return;
        }

        html += '<div class="engine-meta">';
        if (res.engine_mode) html += '<span class="chip chip-info">' + esc(res.engine_mode) + '</span>';
        if (res.bridge_latency_ms !== undefined) html += '<span class="chip">Bridge ' + esc(res.bridge_latency_ms) + ' ms</span>';
        if (res.execution_time_ms !== undefined) html += '<span class="chip">Compute ' + esc(res.execution_time_ms) + ' ms</span>';
        if (res.algorithm) html += '<span class="chip">' + esc(res.algorithm) + '</span>';
        html += '</div>';

        switch (action) {
            case 'benchmark':
                html += '<div class="engine-summary">' +
                    metric('Throughput', fmtNum(res.throughput_ops_per_sec) + ' <small class="muted">ops/s</small>') +
                    metric('Elapsed', esc(res.elapsed_milliseconds) + ' ms') +
                    metric('Range queries', fmtNum(res.segment_tree_queries)) +
                    metric('Price evaluations', fmtNum(res.pricing_matrix_evaluations)) +
                '</div>';
                break;

            case 'pricing': {
                const recs = Array.isArray(res.recommendations) ? res.recommendations : [];
                html += '<div class="table-scroll scroll-y"><table class="data-table"><thead><tr>' +
                    '<th>Room type</th><th>Date</th><th class="right">Base</th><th class="right">Recommended</th>' +
                    '<th class="right">Surge</th><th class="right">Occupancy</th><th>Tier</th></tr></thead><tbody>';
                if (!recs.length) html += emptyRow(7, 'No recommendations returned.');
                recs.forEach(function (r) {
                    html += '<tr><td>' + esc(r.room_type_name) + '</td>' +
                        '<td class="nowrap">' + fmtDate(r.date) + '</td>' +
                        '<td class="right num">' + money(r.base_price) + '</td>' +
                        '<td class="right num"><strong>' + money(r.recommended_price) + '</strong></td>' +
                        '<td class="right num">' + esc(r.surge_percentage) + '%</td>' +
                        '<td class="right num">' + Math.round((Number(r.projected_occupancy) || 0) * 100) + '%</td>' +
                        '<td><span class="chip">' + esc(r.tier) + '</span></td></tr>';
                });
                html += '</tbody></table></div>';
                break;
            }

            case 'dag-solve': {
                const stages = Array.isArray(res.parallel_stages) ? res.parallel_stages : [];
                html += '<div class="engine-summary">' +
                    metric('Valid DAG', res.is_valid_dag ? 'Yes' : 'No') +
                    metric('Critical path', esc(res.critical_path_latency_ms) + ' ms') +
                    metric('Parallel stages', fmtNum(stages.length)) +
                '</div><div class="stages">';
                stages.forEach(function (s) {
                    html += '<div class="stage"><h3>Stage ' + esc(s.stage) + '</h3>' +
                        (s.tasks || []).map(function (t) { return '<code>' + esc(t) + '</code>'; }).join('') + '</div>';
                });
                html += '</div>';
                break;
            }

            case 'rate-parity': {
                const v = Array.isArray(res.violations) ? res.violations : [];
                html += '<div class="engine-summary">' +
                    metric('Parity score', esc(res.parity_health_score) + '<small class="muted"> / 100</small>') +
                    metric('Direct rate', money(res.direct_rate)) +
                    metric('Violations', fmtNum(v.length)) +
                '</div>';
                html += '<div class="table-scroll"><table class="data-table"><thead><tr>' +
                    '<th>Channel</th><th class="right">Direct</th><th class="right">OTA price</th>' +
                    '<th class="right">Difference</th><th>Severity</th></tr></thead><tbody>';
                if (!v.length) html += emptyRow(5, 'No channel is undercutting your direct rate.');
                v.forEach(function (r) {
                    const sev = String(r.severity || '').toUpperCase();
                    const chip = sev === 'CRITICAL' ? 'chip-error' : (sev === 'HIGH' ? 'chip-warning' : 'chip-info');
                    html += '<tr><td>' + esc(r.channel_name) + '</td>' +
                        '<td class="right num">' + money(r.direct_price) + '</td>' +
                        '<td class="right num">' + money(r.ota_price) + '</td>' +
                        '<td class="right num">' + esc(r.percent_diff) + '%</td>' +
                        '<td><span class="chip ' + chip + '">' + esc(titleCase(sev.toLowerCase())) + '</span></td></tr>';
                });
                html += '</tbody></table></div>';
                break;
            }

            case 'allocate': {
                const a = Array.isArray(res.allocations) ? res.allocations : [];
                let units = 0, revenue = 0, margin = 0;
                a.forEach(function (r) {
                    units += Number(r.allocated_units) || 0;
                    revenue += Number(r.projected_revenue) || 0;
                    margin += Number(r.net_margin) || 0;
                });
                html += '<div class="engine-summary">' +
                    metric('Units allocated', fmtNum(units)) +
                    metric('Projected revenue', money(revenue, 0)) +
                    metric('Net margin', money(margin, 0)) +
                '</div>';
                html += '<div class="table-scroll"><table class="data-table"><thead><tr>' +
                    '<th>Channel</th><th class="right">Units</th><th class="right">Unit price</th>' +
                    '<th class="right">Revenue</th><th class="right">Net margin</th></tr></thead><tbody>';
                if (!a.length) html += emptyRow(5, 'No allocations returned.');
                a.forEach(function (r) {
                    html += '<tr><td>' + esc(r.channel_name) + '</td>' +
                        '<td class="right num">' + fmtNum(r.allocated_units) + '</td>' +
                        '<td class="right num">' + money(r.unit_price) + '</td>' +
                        '<td class="right num">' + money(r.projected_revenue) + '</td>' +
                        '<td class="right num"><strong>' + money(r.net_margin) + '</strong></td></tr>';
                });
                html += '</tbody></table></div>';
                break;
            }
        }

        $out.html(html + rawJson(res));
    }

    function rawJson(obj) {
        return '<details class="raw-json"><summary>Show raw JSON</summary><pre>' +
            esc(JSON.stringify(obj, null, 2)) + '</pre></details>';
    }

    /* ------------------------------------------------------------------
       Auto-refresh (paused while the tab is hidden)
       ------------------------------------------------------------------ */

    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(function () {
            loadBookings();
            loadSyncLogs();
        }, REFRESH_MS);
        $('[data-live]').removeClass('is-paused').find('.live-text').text('Live');
    }

    function stopPolling() {
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.pollTimer = null;
        $('[data-live]').addClass('is-paused').find('.live-text').text('Paused');
    }

    /* ------------------------------------------------------------------
       Events + init
       ------------------------------------------------------------------ */

    function bindEvents() {
        $('#simulate-booking').on('click', simulateBooking);
        $('#run-ai-pricing').on('click', runAiPricing);
        $('#db-health-badge').on('click', showDbDetails);

        $('#days-selector').on('click', 'button', function () {
            const days = parseInt($(this).data('days'), 10);
            if (days === state.days) return;
            state.days = days;
            $('#days-selector button').attr('aria-pressed', 'false');
            $(this).attr('aria-pressed', 'true');
            renderMatrix();
        });

        // Tabs: create user / sign in
        $('.tabs').on('click', '[role="tab"]', function () {
            const target = $(this).attr('aria-controls');
            $('.tabs [role="tab"]').attr('aria-selected', 'false');
            $(this).attr('aria-selected', 'true');
            $('#create-user-form, #login-form').addClass('hidden');
            $('#' + target).removeClass('hidden');
        });

        $('#create-user-form').on('submit', createUser);
        $('#login-form').on('submit', login);
        $('#logout-btn').on('click', logout);
        $('#refresh-users').on('click', function () { loadUsers(); });

        $('#users-body').on('click', 'button[data-action]', function () {
            const $btn = $(this);
            const user = findUser($btn.closest('tr').data('user-id'));
            if (!user) return;
            switch ($btn.data('action')) {
                case 'bookings': showUserBookings(user); break;
                case 'edit': editUser(user); break;
                case 'deactivate': deactivateUser(user, $btn); break;
                case 'reactivate': reactivateUser(user, $btn); break;
            }
        });

        $('.engine-buttons').on('click', '.engine-btn', function () {
            runEngine($(this).data('engine'), $(this));
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#modal-root').children().length) closeModal();
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                loadBookings();
                loadSyncLogs();
                startPolling();
            }
        });
    }

    function init() {
        bindEvents();
        restoreSession();
        $('#bookings-body').html(skeletonRows(5, 4));
        $('#sync-logs-body').html(skeletonRows(5, 4));

        checkHealth();
        checkEngine();
        renderMatrix();
        loadBookings();
        loadSyncLogs();
        loadUsers();
        startPolling();
    }

    // Expose helpers for console debugging / other scripts
    window.OmniBooking = {
        showToast: showToast,
        renderMatrix: renderMatrix,
        loadUsers: loadUsers,
        loadBookings: loadBookings,
        loadSyncLogs: loadSyncLogs
    };
    window.showToast = showToast;

    $(init);
})(jQuery);
