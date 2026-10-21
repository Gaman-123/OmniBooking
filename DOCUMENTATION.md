# 📘 Omni Booking - Technical Documentation & System Guide

Welcome to **Omni Booking**, a high-performance Channel Manager backend engineered with **PHP 8.3 & Laravel architecture**, integrated with **Neon SQL (Serverless PostgreSQL)**, asynchronous **DAG task dispatching**, and real-time inventory synchronization.

---

## 🏛️ 1. System Architecture Overview

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Frontend Layer (Browser)                        │
│             HTML5, CSS3, JavaScript, jQuery Availability Matrix        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ AJAX / REST (JSON)
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                     Application Layer (PHP / Laravel)                  │
│  - App\Http\Router                 - App\Http\Controllers              │
│  - App\Database\Connection         - App\Services\DagDispatcherService │
│  - App\Services\InventoryService   - App\Models (PDO/Eloquent pattern) │
└───────────────────┬────────────────────────────────────────┬───────────┘
                    │                                        │
                    │ Database Transactions (ACID)           │ Async Webhook Fan-Out (HTTP/cURL)
                    ▼                                        ▼
┌───────────────────────────────────────┐   ┌────────────────────────────┐
│      Database Layer (Neon SQL)        │   │  External Mock OTA Layer   │
│  Serverless PostgreSQL                │   │  - [Mock Booking.com]      │
│  (Compound Index Range Lookups)       │   │  - [Mock Agoda]            │
│  + SQLite Fallback for Local Sandbox  │   │  - [Mock Rakuten Travel]   │
└───────────────────────────────────────┘   └────────────────────────────┘
```

---

## 📂 2. File & Directory Structure

```
Omni booking/
├── app/
│   ├── Database/
│   │   ├── Connection.php         # Neon PostgreSQL PDO connection manager with SSL & fallback
│   │   └── Migrator.php           # Database schema migration runner for Neon SQL
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── BookingController.php   # Atomic reservation commit & DAG trigger
│   │   │   ├── InventoryController.php # Matrix generation & interval availability queries
│   │   │   ├── MockOtaController.php   # Mock webhook receivers for Booking.com, Agoda, Rakuten
│   │   │   ├── PricingController.php   # Dynamic pricing surge calculation
│   │   │   └── SyncLogController.php   # Audit trail of OTA webhook sync logs
│   │   └── Router.php             # Fast REST Router with CORS & JSON responses
│   ├── Models/
│   │   ├── Booking.php            # Bookings table model
│   │   ├── Channel.php            # Channels table model (OTAs)
│   │   ├── Hotel.php              # Hotels table model
│   │   ├── Inventory.php          # Inventories table model with compound index lookups
│   │   ├── RoomType.php           # Room types table model
│   │   └── SyncLog.php            # Sync logs table model (JSONB telemetry)
│   └── Services/
│       ├── DagDispatcherService.php # Directed Acyclic Graph (DAG) webhook fan-out
│       └── InventoryService.php     # Availability matrix & dynamic pricing logic
├── config/
│   └── database.php               # Neon PostgreSQL & SQLite connection profiles
├── database/
│   ├── migrations/                # Individual SQL migrations
│   ├── seeders/
│   │   └── DatabaseSeeder.php     # Seeds Hotel, 3 Room Types, 30-Day Matrix & 3 Channels
│   └── schema.sql                 # Complete Neon PostgreSQL raw SQL schema
├── public/
│   ├── index.php                  # Application front controller entrypoint
│   ├── index.html                 # Interactive dashboard UI
│   ├── css/style.css              # Glassmorphic CSS styling
│   └── js/app.js                  # jQuery AJAX application logic
├── routes/
│   └── api.php                    # REST API route declarations
├── artisan                        # CLI command-line runner (migrate, seed, simulate, serve)
├── .env                           # Local environment configuration
├── .env.example                   # Template environment configuration
├── composer.json                  # PHP dependency manifest
└── DOCUMENTATION.md               # Complete architectural blueprint and guide
```

---

## 🗄️ 3. Database Schema (Neon SQL Serverless PostgreSQL)

The schema uses compound indexing and foreign key constraints for ACID-compliant consistency:

### 1. `hotels`
Stores property metadata.
```sql
CREATE TABLE hotels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. `room_types`
Stores room category details and baseline pricing.
```sql
CREATE TABLE room_types (
    id SERIAL PRIMARY KEY,
    hotel_id INT REFERENCES hotels(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    total_units INT NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. `inventories`
Stores daily availability and dynamic pricing per room type.
- **Compound Unique Index:** `UNIQUE(room_type_id, date)` guarantees $O(1)$ indexed range lookups.
```sql
CREATE TABLE inventories (
    id SERIAL PRIMARY KEY,
    room_type_id INT REFERENCES room_types(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    available_rooms INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(room_type_id, date)
);
CREATE INDEX idx_inventories_date_lookup ON inventories(room_type_id, date);
```

### 4. `channels`
Stores connected Online Travel Agencies (OTAs).
```sql
CREATE TABLE channels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    webhook_url TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 5. `bookings`
Stores reservation records.
```sql
CREATE TABLE bookings (
    id SERIAL PRIMARY KEY,
    booking_reference VARCHAR(50) UNIQUE NOT NULL,
    guest_name VARCHAR(150),
    room_type_id INT REFERENCES room_types(id),
    channel_id INT REFERENCES channels(id),
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 6. `sync_logs`
Stores webhook audit telemetry with JSONB payloads and execution millisecond benchmarks.
```sql
CREATE TABLE sync_logs (
    id SERIAL PRIMARY KEY,
    booking_id INT REFERENCES bookings(id) ON DELETE CASCADE,
    channel_id INT REFERENCES channels(id) ON DELETE CASCADE,
    payload JSONB,
    response_status INT,
    execution_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔌 4. How to Connect Neon SQL (Serverless PostgreSQL)

Neon SQL provides a cloud PostgreSQL database. To connect your live Neon instance:

1. Open your Neon Console at [console.neon.tech](https://console.neon.tech).
2. Copy your connection details from the **Connection Details** dashboard.
3. Update your `.env` file with your credentials:

```dotenv
# Option A: Full Connection String URL
DATABASE_URL=postgresql://neondb_owner:YOUR_PASSWORD@ep-nameless-resonance-123456.us-east-2.aws.neon.tech/neondb?sslmode=require

# Option B: Individual Connection Parameters
DB_CONNECTION=pgsql
DB_HOST=ep-nameless-resonance-123456.us-east-2.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=YOUR_PASSWORD
DB_SSLMODE=require
```

> **Smart Fallback:** If live Neon credentials are not yet configured or you are developing offline, the application automatically uses a local SQLite database (`database/database.sqlite`), allowing instant local testing with zero downtime!

---

## ⚡ 5. Directed Acyclic Graph (DAG) Task Dispatcher

To prevent HTTP request blocking when synchronizing with multiple OTAs, the system implements a **DAG Task Dispatcher**:

```
                 [Node A: Database Commitment]
           (Atomic reservation transaction & validation)
                                |
                 +--------------+--------------+
                 |              |              |
                 v              v              v
            [Node B]       [Node C]       [Node D]
         Mock Booking.com  Mock Agoda  Mock Rakuten Travel
```

1. **Node A (Commitment Phase):**
   - Starts an ACID database transaction.
   - Evaluates date interval availability: `MIN(available_rooms) >= requested_rooms`.
   - Decrements inventory across dates atomically.
   - Inserts booking into `bookings` and commits.
2. **Nodes B, C, D (Fan-Out Phase):**
   - Asynchronously dispatches webhooks to external OTAs.
   - Measures exact execution time in milliseconds (`execution_time_ms`).
   - Writes structured JSON audit payloads into `sync_logs`.

---

## 📡 6. REST API Endpoints Specification

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/health` | Database connection status & health telemetry |
| `GET` | `/api/inventory-matrix?days=7` | Real-time availability & pricing matrix |
| `POST` | `/api/check-availability` | Range availability verification |
| `GET` | `/api/bookings` | List recent bookings |
| `POST` | `/api/bookings` | Create direct booking |
| `POST` | `/api/simulate-booking` | Simulate incoming OTA booking + DAG fan-out |
| `POST` | `/api/run-ai-pricing` | Calculate & update dynamic pricing matrix |
| `GET` | `/api/sync-logs` | Live OTA webhook sync logs audit trail |
| `POST` | `/api/mock-ota/booking-com` | Mock Booking.com webhook receiver |
| `POST` | `/api/mock-ota/agoda` | Mock Agoda webhook receiver |
| `POST` | `/api/mock-ota/rakuten` | Mock Rakuten Travel webhook receiver |
| `POST` | `/api/migrate` | Run database migrations via API |
| `POST` | `/api/seed` | Seed initial database records via API |

---

## 🛠️ 7. Artisan CLI Tool Usage

The project includes an `artisan` CLI tool to manage backend operations directly from your terminal:

```bash
# Check database connection and active driver
php artisan db:status

# Run migrations (Neon SQL PostgreSQL or SQLite)
php artisan migrate

# Seed sample hotel, 3 room types, 30-day matrix, and 3 channels
php artisan db:seed

# Simulate an incoming OTA reservation with DAG fan-out
php artisan booking:simulate

# Reset / drop all tables
php artisan migrate:reset

# Start local development server
php artisan serve
```

---

## 🚀 8. Running the Application Locally

1. **Install Dependencies:**
   ```bash
   composer install
   ```

2. **Run Migrations & Seeders:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

3. **Start the Web Server:**
   ```bash
   php artisan serve
   ```
   Open your browser to: **`http://127.0.0.1:8000`**
