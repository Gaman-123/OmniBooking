# 🖥️ Omni Booking — Frontend Integration Guide

> **Stack:** HTML5 · Vanilla CSS (Glassmorphic) · jQuery AJAX · REST API (PHP Backend) · C++ Algorithmic Engine
> **Live Server:** `http://127.0.0.1:8000`

---

## 📐 1. Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│                        Browser (index.html)                          │
│    Glassmorphic SPA  ·  jQuery AJAX  ·  Zero-framework vanilla JS   │
└──────────────────────────────────┬───────────────────────────────────┘
                                   │  REST/JSON over HTTP (AJAX)
                                   ▼
┌──────────────────────────────────────────────────────────────────────┐
│              PHP Backend  (public/index.php → Router)                │
│   /api/health · /api/inventory-matrix · /api/bookings · etc.        │
└──────────────────┬───────────────────────────────┬───────────────────┘
                   │                               │
                   ▼                               ▼
       ┌───────────────────┐         ┌─────────────────────────────┐
       │  MySQL (InnoDB)   │         │  C++ Algorithmic Engine      │
       │  omni_booking db  │         │  bin/channel_engine.exe      │
       │  6 tables · ACID  │         │  DAG · Pricing · Parity      │
       └───────────────────┘         └─────────────────────────────┘
```

---

## 📂 2. File Structure (Frontend Layer)

```
public/
├── index.html        ← Single-Page Application entry (DOM scaffold)
├── index.php         ← PHP front-controller; routes all API calls
├── css/
│   └── style.css     ← Glassmorphic dark-mode design system
└── js/
    └── app.js        ← jQuery AJAX logic, matrix rendering, toasts
```

---

## 🎨 3. Design System (`public/css/style.css`)

### Color Palette

| Token | Value | Usage |
|---|---|---|
| `--bg-dark` | `#0a0e1a` | Primary background |
| `--bg-card` | `#111827` | Card/glass surface |
| `--border` | `rgba(255,255,255,0.08)` | Subtle glass borders |
| `--accent-blue` | `#3b82f6` | Primary action / brand |
| `--accent-green` | `#10b981` | Success / confirmed states |
| `--accent-amber` | `#f59e0b` | Warnings / mid-occupancy |
| `--accent-red` | `#ef4444` | Danger / sold-out alerts |
| `--text-primary` | `#f1f5f9` | Body text |
| `--text-muted` | `#64748b` | Secondary/dim labels |

### Component Classes

```css
/* Glass card panel */
.section-card   { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; }

/* Action buttons */
.btn-primary    { background: var(--accent-blue); }
.btn-success    { background: var(--accent-green); }
.btn-outline    { border: 1px solid var(--border); }
.btn-danger     { background: var(--accent-red); }

/* Matrix status chips */
.chip-available { background: rgba(16,185,129,0.15); color: #10b981; }
.chip-critical  { background: rgba(245,158,11,0.15); color: #f59e0b; }
.chip-sold_out  { background: rgba(239,68,68,0.15);  color: #ef4444; }

/* Inventory cells */
.status-available  { border-left: 3px solid var(--accent-green); }
.status-critical   { border-left: 3px solid var(--accent-amber); }
.status-sold_out   { border-left: 3px solid var(--accent-red); }
```

---

## ⚙️ 4. JavaScript API Integration (`public/js/app.js`)

### Base Configuration

```js
const BASE_URL = '';  // Same-origin API (served by PHP backend)
```

### Available API Endpoints

| Trigger | Method | Endpoint | Description |
|---|---|---|---|
| Page Load | `GET` | `/api/health` | Database status badge |
| Page Load | `GET` | `/api/inventory-matrix?days=7` | Render availability matrix |
| Button | `POST` | `/api/simulate-booking` | OTA reservation + DAG fan-out |
| Button | `POST` | `/api/run-ai-pricing` | PHP yield pricing compute |
| Button | `POST` | `/api/cpp/benchmark` | C++ 1M+ ops/sec benchmark |
| Button | `POST` | `/api/cpp/pricing` | C++ dynamic pricing matrix |
| Button | `POST` | `/api/cpp/dag-solve` | Kahn's DAG topology solver |
| Button | `POST` | `/api/cpp/rate-parity` | OTA rate parity audit |
| Auto-refresh | `GET` | `/api/sync-logs` | Live webhook telemetry |
| Auto-refresh | `GET` | `/api/bookings` | Recent reservations feed |

### AJAX Call Pattern

```js
$.ajax({
    url: '/api/simulate-booking',
    method: 'POST',
    contentType: 'application/json',
    success: function(response) {
        showToast('✅ Booking confirmed: ' + response.booking.booking_reference, 'success');
        renderMatrix(); // Refresh matrix
        loadSyncLogs(); // Refresh audit logs
    },
    error: function() {
        showToast('❌ API error. Is the backend server running?', 'error');
    }
});
```

### Toast Notification System

```js
// Usage anywhere in app.js:
showToast('Message text here', 'success');  // green
showToast('Conflict detected!', 'error');    // red
showToast('Pricing updated', 'info');        // blue
```

---

## 🗃️ 5. UI Components

### Stats Grid (Top Bar)

4 live KPI cards auto-populated on page load:

| ID | Metric | Source |
|---|---|---|
| `#stat-total-rooms` | Total room units | Inventory matrix |
| `#stat-adr` | Average Daily Rate | Matrix price average |
| `#stat-channels-count` | Active OTA channels | Health endpoint |
| `#stat-sync-count` | Total DAG syncs | Sync logs count |

### Availability & Price Matrix

- Renders as a 7-column table (7-day rolling window by default)
- Cells are color-coded by `status`: `available`, `critical`, `sold_out`
- Each cell shows: **Price** + **Available/Total** units + occupancy %
- Auto-refreshes after every booking simulation or pricing update

### OTA Sync Log Table

Real-time webhook audit trail from `sync_logs` table:

| Column | Description |
|---|---|
| Channel | OTA name (Booking.com, Agoda, Rakuten) |
| Booking Ref | `OB-XXXXXX` reference |
| HTTP Status | Webhook response code (200 = SUCCESS) |
| Latency | Millisecond dispatch time |
| Time | ISO timestamp |

### Recent Bookings Feed

| Column | Description |
|---|---|
| Reference | Unique booking code |
| Guest | Guest name |
| Room Type | Room category |
| Channel | Source OTA |
| Total | Total reservation price (USD) |

---

## 🚀 6. Running the Frontend

### Step 1 — Start the PHP Backend

```bash
php artisan serve
# → http://127.0.0.1:8000
```

### Step 2 — Initialize the Database (first run)

Open browser or use curl:

```bash
# Run migrations
curl -X POST http://127.0.0.1:8000/api/migrate

# Seed sample data
curl -X POST http://127.0.0.1:8000/api/seed
```

Or via artisan CLI:

```bash
php artisan migrate
php artisan db:seed
```

### Step 3 — Access the Dashboard

Open: **`http://127.0.0.1:8000`**

The UI auto-initializes:
1. Checks database connection (health badge turns green/orange)
2. Renders the 7-day inventory/pricing matrix
3. Loads recent bookings and OTA sync logs
4. Displays C++ engine status

---

## 🔌 7. C++ Engine Buttons (Advanced Panel)

> **Prerequisite:** Binary must be compiled. Run: `php artisan cpp:build`

| Button | API Call | Output |
|---|---|---|
| `⚡ C++ Benchmark` | `POST /api/cpp/benchmark` | 1M+ ops/sec throughput JSON |
| `💹 C++ Dynamic Pricing` | `POST /api/cpp/pricing` | 7-day surge pricing recommendations |
| `🔗 Solve DAG` | `POST /api/cpp/dag-solve` | Parallel stages + critical path (57ms) |
| `📊 Rate Parity Audit` | `POST /api/cpp/rate-parity` | OTA undercutting violations |
| `🚀 Allocate Channels` | `POST /api/cpp/allocate` | Optimal quota per channel |

All C++ responses include:
```json
{
  "engine_mode": "NATIVE_CPP17",
  "bridge_latency_ms": 48.3,
  ...
}
```

---

## 🎯 8. Customization Guide

### Change Date Range (default 7 days)

In `app.js`, find:
```js
url: '/api/inventory-matrix?days=7'
```
Replace `7` with any value up to `30`.

### Add a New KPI Card

In `index.html`, clone a `.stat-card` div:
```html
<div class="stat-card">
    <div class="stat-label">Your Metric</div>
    <div class="stat-val" id="stat-custom">—</div>
    <div class="stat-sub">Your description</div>
</div>
```
Then populate it in `app.js` after your API response:
```js
$('#stat-custom').text(response.your_value);
```

### Styling Dark Theme Override

```css
/* Add to style.css */
:root {
    --bg-dark: #050a14;        /* darker background */
    --accent-blue: #6366f1;    /* indigo brand color */
}
```

---

## 📡 9. REST API Quick Reference

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/health` | None | DB status + driver info |
| `GET` | `/api/inventory-matrix?days=N` | None | Full NxN matrix JSON |
| `POST` | `/api/check-availability` | None | Date-range room check |
| `GET` | `/api/bookings` | None | Last 50 bookings |
| `POST` | `/api/bookings` | None | Create direct booking |
| `POST` | `/api/simulate-booking` | None | OTA simulation + DAG |
| `POST` | `/api/run-ai-pricing` | None | PHP yield pricing |
| `GET` | `/api/sync-logs` | None | Webhook audit trail |
| `GET` | `/api/cpp/status` | None | C++ engine capabilities |
| `POST` | `/api/cpp/benchmark` | None | 1M+ ops/sec test |
| `POST` | `/api/cpp/pricing` | None | C++17 surge pricing |
| `POST` | `/api/cpp/dag-solve` | None | Kahn's topo scheduler |
| `POST` | `/api/cpp/allocate` | None | Multi-channel optimizer |
| `POST` | `/api/cpp/rate-parity` | None | OTA parity auditor |
| `POST` | `/api/migrate` | None | Run schema migrations |
| `POST` | `/api/seed` | None | Seed initial data |

---

## 🐞 10. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Matrix shows "Loading..." forever | Backend not running | `php artisan serve` |
| DB badge shows orange/SQLite | MySQL not running or wrong creds | Start MySQL or check `.env` |
| Matrix shows 0 rooms | Not seeded | `POST /api/seed` or `php artisan db:seed` |
| Booking simulation fails | No room inventory for today | Re-run seeder |
| C++ buttons return `offline` | Binary not compiled | `php artisan cpp:build` |
| CORS errors in console | Request from different port | Use `127.0.0.1:8000` same-origin |

---

## 🏗️ 11. Extending the Frontend (Next Steps)

- **React/Vue SPA:** Replace `index.html` + `app.js` with Vite-based SPA; backend API unchanged
- **WebSockets:** Add real-time push for sync logs using Ratchet PHP or a Node.js socket gateway
- **Authentication:** Wrap API routes with JWT bearer token middleware
- **Charts:** Integrate Chart.js for RevPAR trend lines and occupancy heat maps
- **Multi-property:** Extend matrix table with hotel-level grouping headers

---

> Built with ❤️ | Omni Booking Channel Manager | PHP 8.3 · MySQL · C++17 · GCC/MinGW
