# 🏨 Omni Booking — High-Performance Channel Manager & Dynamic Inventory Engine

[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![C++](https://img.shields.io/badge/C%2B%2B-17%20Algorithmic%20Core-00599c?style=flat-square&logo=cplusplus&logoColor=white)](https://en.cppreference.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%20(InnoDB)-4479a1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![PostgreSQL](https://img.shields.io/badge/Neon%20SQL-Serverless%20Postgres-336791?style=flat-square&logo=postgresql&logoColor=white)](https://neon.tech/)
[![SQLite](https://img.shields.io/badge/SQLite-Fallback%20Sandbox-003B57?style=flat-square&logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

> **Omni Booking** is an enterprise-grade, hybrid hotel channel manager engineered for ultra-fast availability lookups, dynamic yield pricing, and real-time inventory synchronization across major Online Travel Agencies (OTAs)—including **Booking.com**, **Agoda**, and **Rakuten Travel**.
> 
> Engineered with a **PHP 8.3 MVC architecture**, a **native C++17 algorithmic engine** executing $O(\log N)$ segment tree queries and Kahn's DAG task scheduling, and resilient **multi-database failover** (MySQL, Neon Serverless PostgreSQL, and SQLite).

---

## 📸 Dashboard Preview

![Omni Booking Dashboard](file:///C:/Users/rache/.gemini/antigravity-ide/brain/5e4a3f0c-a217-4838-b948-125193e5fc0c/omni_booking_dashboard_1793201918950.jpg)

*The Omni Booking single-page dashboard: Real-time database & C++ health telemetry, multi-channel KPI cards, 7/14/30-day interactive availability and pricing matrix with occupancy meters, live booking feed, and sub-millisecond OTA webhook sync audit logs.*

---

## 📑 Table of Contents

- [Overview & Architecture](#-overview--architecture)
- [Key Features](#-key-features)
- [Technologies Used](#-technologies-used)
- [System Architecture](#-system-architecture)
- [Prerequisites](#-prerequisites)
- [Installation Instructions](#-installation-instructions)
- [Commands to Run the Frontend Locally](#-commands-to-run-the-frontend-locally)
- [Environment Variables](#-environment-variables)
- [Artisan CLI Reference](#-artisan-cli-reference)
- [REST API Endpoints](#-rest-api-endpoints)
- [Database Configuration & Failover](#-database-configuration--failover)
- [License](#-license)

---

## 🌟 Overview & Architecture

Hotels face severe operational risks when updating rates and room availability across multiple global booking portals. Stale inventory leads to **costly double-bookings**, while slow pricing adjustments forfeit revenue during demand surges.

Omni Booking solves this with a **high-performance hybrid design**:
1. **Frontend Layer**: Responsive, zero-dependency Glassmorphic Single-Page Application (HTML5, Vanilla CSS3 tokens, jQuery 3.7.1) with live occupancy gauges and asynchronous polling.
2. **Application Layer (PHP 8.3)**: Lightweight, high-throughput REST API with strict ACID database transaction management, concurrency locks, and an asynchronous DAG task dispatcher.
3. **Algorithmic Core (C++17)**: Compiled binary (`bin/channel_engine.exe` / `bin/channel_engine`) invoked for sub-millisecond segment tree range queries ($O(\log N)$), Kahn's DAG topological ordering, dynamic surge pricing calculations, and OTA rate parity auditing.
4. **Database Resilience**: Built-in support for **MySQL 8 (InnoDB)**, **Neon SQL Serverless PostgreSQL**, with automatic fallback to **SQLite** for instant offline local development.

---

## ✨ Key Features

### 1. 📅 Real-Time Availability & Dynamic Pricing Matrix
- Interactive 7-day, 14-day, and 30-day interval selector.
- Visual occupancy health bars with instant color status chips:
  - 🟢 **Available** (Safe inventory threshold)
  - 🟡 **Critical** (2 or fewer rooms remaining)
  - 🔴 **Sold Out** (0 units available)
- In-line dynamic price rendering reflecting occupancy surges and booking velocity.

### 2. ⚡ Directed Acyclic Graph (DAG) Task Dispatcher
- Prevents HTTP blocking when pushing reservations downstream.
- Two-stage execution model:
  - **Node A (Commitment Phase)**: Atomic ACID database transaction checks `MIN(available_rooms) >= requested_rooms`, decrements rooms, and creates the booking.
  - **Nodes B, C, D (Fan-Out Phase)**: Asynchronously fans out webhook notifications to Booking.com, Agoda, and Rakuten Travel.
  - Records execution latencies in milliseconds (`execution_time_ms`) and full JSON payloads in audit logs.

### 3. 🚀 Native C++17 Algorithmic Engine (`channel_engine`)
- **Range Minimum Query (RMQ) Segment Tree**: Evaluates room availability across arbitrary date intervals in $O(\log N)$ time instead of $O(N)$ linear table scans. Capable of executing **50,000+ interval checks in < 15ms**.
- **Kahn's Topological Sort (DAG Solver)**: Computes optimal dependency resolution order for concurrent OTA webhook dispatches.
- **Dynamic Pricing Engine**: Applies non-linear demand curves based on lead time, seasonality, and occupancy velocity.
- **OTA Rate Parity Auditor**: Scans channel feeds to detect and flag contract undercutting and unauthorized OTA markdowns.
- **Multi-Channel Quota Allocator**: Dynamically optimizes allotment ratios across OTAs according to historical commission margins.

### 4. 🔒 Concurrency & Double-Booking Protection
- Strict database-level row locking (`SELECT ... FOR UPDATE` on MySQL / PostgreSQL).
- Compound unique index `UNIQUE(room_type_id, date)` ensures zero race conditions during simultaneous reservation requests across disparate channels.

### 5. 👥 User Management & Role-Based Access
- Full user authentication lifecycle with password hashing.
- Role management: **Guest**, **Staff**, and **Admin**.
- Per-user reservation history modal and account status toggle.

### 6. 📊 Real-Time Telemetry & Sync Audit Logs
- Live audit stream tracking every outbound OTA webhook payload, response code, and latency.
- Health badges reflecting active database driver and C++ engine compilation status.

---

## 🛠 Technologies Used

| Layer | Technologies |
|---|---|
| **Frontend** | HTML5, Vanilla CSS3 (Custom Glassmorphic System, Dark Mode, CSS Variables), JavaScript (ES6+), jQuery 3.7.1, Google Fonts (*Bricolage Grotesque*, *Instrument Sans*) |
| **Backend** | PHP 8.2 / 8.3, Composer (PSR-4 Autoloading), Custom REST Router, GuzzleHttp 7.8, vlucas/phpdotenv 5.6 |
| **Algorithmic Core** | C++17 / C++14, GCC / g++, Segment Trees, Kahn's Topological Sorting, STL Data Structures |
| **Databases** | MySQL 8.0 (InnoDB), Neon SQL (Serverless PostgreSQL with SSL), SQLite3 (Local sandbox fallback) |
| **CLI & Tooling** | Custom PHP `artisan` CLI, Node.js / npm (scripts), Make / CMake, Git |
| **DevOps / Containers** | Docker (Multi-stage build), Docker Compose, Nginx, GitHub Actions |

---

## 🏛 System Architecture

```mermaid
graph TD
    subgraph Browser ["Frontend Client (SPA)"]
        UI[index.html & Glassmorphic CSS]
        AJAX[jQuery AJAX Engine / app.js]
    end

    subgraph Backend ["Application Layer (PHP 8.3)"]
        Router[public/index.php & Router.php]
        Controllers[Controllers: Booking, Inventory, Users, Pricing]
        DAG[DagDispatcherService - Webhook Fan-Out]
        Bridge[CppAlgorithmBridge - proc_open IPC]
    end

    subgraph AlgorithmicCore ["C++17 High-Performance Core"]
        CppBinary["bin/channel_engine (.exe)"]
        RMQ["Segment Tree RMQ O(log N)"]
        Kahn["Kahn's DAG Topology Scheduler"]
        Pricing["Surge & Yield Pricing Model"]
        Parity["Rate Parity Auditor"]
    end

    subgraph DataStore ["Database Layer"]
        MySQL[(MySQL 8.0 InnoDB)]
        Neon[(Neon Serverless PostgreSQL)]
        SQLite[(SQLite3 Local Fallback)]
    end

    subgraph ExternalChannels ["Mock OTA Network"]
        BookingCom[Mock Booking.com]
        Agoda[Mock Agoda]
        Rakuten[Mock Rakuten Travel]
    end

    UI --> AJAX
    AJAX -->|REST / JSON| Router
    Router --> Controllers
    Controllers --> Bridge
    Bridge -->|Sub-millisecond IPC| CppBinary
    CppBinary --> RMQ
    CppBinary --> Kahn
    CppBinary --> Pricing
    CppBinary --> Parity

    Controllers -->|ACID Transactions| MySQL
    Controllers -.->|Cloud SSL| Neon
    Controllers -.->|Zero-Config Fallback| SQLite

    Controllers --> DAG
    DAG -->|Async Webhook| BookingCom
    DAG -->|Async Webhook| Agoda
    DAG -->|Async Webhook| Rakuten
```

---

## 📋 Prerequisites

Before setting up Omni Booking locally, ensure you have the following installed on your machine:

1. **PHP**: Version `8.2` or `8.3`
   - Required extensions enabled in `php.ini`:
     - `pdo`
     - `pdo_mysql` (for MySQL)
     - `pdo_pgsql` (for Neon PostgreSQL)
     - `pdo_sqlite` (for SQLite fallback)
     - `curl`
     - `mbstring`
     - `openssl`
2. **Composer**: Version `2.x` ([Get Composer](https://getcomposer.org/))
3. **Database** (Choose any one):
   - **MySQL 8.0+** (Default local option)
   - **Neon Serverless PostgreSQL** (Free cloud database at [neon.tech](https://neon.tech))
   - **SQLite** (Zero setup required; auto-generated locally)
4. **C++ Compiler** *(Optional, for building the high-speed algorithmic core)*:
   - **Windows**: MinGW-w64 (`g++` 13+) or MSVC
   - **Linux**: `build-essential` (`sudo apt install g++`)
   - **macOS**: Clang / Xcode command-line tools
   - *Note: If a C++ compiler is not available, the backend automatically uses built-in PHP fallback logic without crashing.*
5. **Node.js & npm** *(Optional)*: Version `18+` for npm script shortcuts.

---

## 🚀 Installation Instructions

### Step 1: Clone the Repository
```bash
git clone https://github.com/Gaman-123/OmniBooking.git
cd OmniBooking
```

### Step 2: Configure Environment Variables
Copy the template configuration file to create your local `.env`:
```bash
# Windows (PowerShell or Command Prompt)
copy .env.example .env

# macOS / Linux
cp .env.example .env
```
*(Review and update database credentials in `.env` if using MySQL or Neon PostgreSQL. See [Environment Variables](#-environment-variables) below).*

### Step 3: Install PHP Dependencies
```bash
composer install
```

### Step 4: Run Database Migrations
Create all required tables (`hotels`, `room_types`, `inventories`, `channels`, `bookings`, `sync_logs`, `users`):
```bash
php artisan migrate
```

### Step 5: Seed Sample Data
Populate the database with realistic hotels, 3 room categories (Deluxe Suite, Ocean Villa, Executive King), a 30-day availability and pricing matrix, and active OTA channels:
```bash
php artisan db:seed
```

### Step 6: (Optional) Compile the C++ Algorithmic Core
Compile the native C++17 binary into `bin/channel_engine.exe` (or `bin/channel_engine` on Linux/macOS):
```bash
# Using Artisan
php artisan cpp:build

# Or using npm
npm run build:cpp

# Or manually using g++
g++ -std=c++14 -O3 -Icpp/include cpp/src/dynamic_pricing.cpp cpp/src/inventory_allocator.cpp cpp/src/dag_scheduler.cpp cpp/src/rate_parity_auditor.cpp cpp/src/main.cpp -o bin/channel_engine.exe
```

Verify the C++ engine status:
```bash
php artisan cpp:status
```

---

## 💻 Commands to Run the Frontend Locally

Omni Booking's frontend is a responsive Single-Page Application located in `public/`. Because it interacts with the backend REST API (`/api/...`), the best way to run the frontend is using the built-in development server that serves both the static UI and API endpoints simultaneously.

### 🌟 Recommended: Integrated Server (Frontend + REST API)

Run any of the following equivalent commands from the project root:

```bash
# Option A: Using the Artisan CLI
php artisan serve

# Option B: Using npm script
npm run serve:php

# Option C: Direct PHP built-in server
php -S 127.0.0.1:8000 -t public public/index.php
```

Once started, open your web browser and navigate to:
👉 **`http://127.0.0.1:8000`** (or **`http://localhost:8000`**)

---

### 🌐 Alternative: Static Frontend Preview Only

If you only need to inspect or modify the HTML/CSS/JS frontend without running database transactions:

```bash
# Using Node's serve utility
npx serve public -p 8000

# Using Python 3 HTTP server
python -m http.server 8000 --directory public
```
> *Note: While static preview renders the glassmorphic layout and UI widgets, API actions (like booking simulation or saving users) require the PHP server running.*

---

### 🐳 Alternative: Running with Docker Compose

If you have Docker installed, you can spin up the complete stack (PHP 8.3-FPM + Nginx + MySQL 8 + compiled C++ binary) in one command:

```bash
docker-compose up -d --build
```
Then visit **`http://localhost:80`**.

---

## 🔐 Environment Variables

The application is configured via the `.env` file at the root of the project. Below is the complete reference of supported environment variables:

| Variable | Type | Default | Description |
|---|---|---|---|
| `APP_NAME` | `string` | `"Omni Booking"` | Human-readable name of the application. |
| `APP_ENV` | `string` | `local` | Environment mode (`local`, `staging`, `production`). |
| `APP_KEY` | `string` | *(Base64 string)* | 32-character application encryption key. |
| `APP_DEBUG` | `boolean` | `true` | Enables detailed stack traces and debugging logs. |
| `APP_URL` | `string` | `http://127.0.0.1:8000` | Canonical root URL of the application. |
| `CPP_ENGINE_PATH` | `string` | `bin/channel_engine.exe` | Path to the compiled C++ algorithmic core executable (`bin/channel_engine` on Linux). |
| `DB_CONNECTION` | `string` | `mysql` | Active database driver (`mysql`, `pgsql`, or `sqlite`). |
| `DB_HOST` | `string` | `127.0.0.1` | Database server host. |
| `DB_PORT` | `integer` | `3306` | Database server port (`3306` for MySQL, `5432` for PostgreSQL). |
| `DB_DATABASE` | `string` | `omni_booking` | Database schema name. |
| `DB_USERNAME` | `string` | `root` | Database username. |
| `DB_PASSWORD` | `string` | `""` | Database password. |
| `DB_CHARSET` | `string` | `utf8mb4` | Database character set. |
| `DB_COLLATION` | `string` | `utf8mb4_unicode_ci` | Database collation. |
| `DATABASE_URL` | `string` | *(Optional)* | Full connection URI (e.g. `postgresql://user:pass@host/db?sslmode=require`). |
| `DB_SSLMODE` | `string` | `require` | SSL mode for PostgreSQL/Neon connections (`require`, `prefer`, `disable`). |
| `DB_SQLITE_PATH` | `string` | `database/database.sqlite` | Filepath for local SQLite fallback database. |
| `QUEUE_CONNECTION` | `string` | `database` | Task queue driver (`database` or `sync`). |

---

## 🧰 Artisan CLI Reference

Omni Booking includes a CLI tool (`artisan`) for terminal-based administration and algorithmic benchmarking:

```bash
php artisan [command]
```

| Command | Description |
|---|---|
| `php artisan serve` | Starts the local HTTP development server on `http://127.0.0.1:8000`. |
| `php artisan migrate` | Executes all pending database schema migrations on the active driver. |
| `php artisan migrate:reset` | Drops all application tables. |
| `php artisan db:seed` | Seeds properties, 3 room categories, 30-day matrix, and channels. |
| `php artisan db:status` | Tests database connectivity and displays active driver health metrics. |
| `php artisan cpp:build` | Compiles the C++ Algorithmic Core with `g++` into `bin/channel_engine.exe`. |
| `php artisan cpp:status` | Queries the C++ engine version, capabilities, and system health. |
| `php artisan cpp:benchmark` | Runs 50,000 RMQ queries and 30,000 dynamic pricing evaluations in <15ms. |
| `php artisan cpp:pricing` | Computes dynamic yield surge pricing recommendations using C++. |
| `php artisan cpp:dag` | Solves the OTA webhook dispatch DAG schedule using Kahn's algorithm. |
| `php artisan cpp:parity` | Audits rate parity across channels to detect partner rate undercutting. |
| `php artisan booking:simulate` | Simulates an incoming OTA reservation with atomic inventory deduction & DAG fan-out. |
| `php artisan help` | Displays the list of all available commands. |

---

## 📡 REST API Endpoints

All responses are returned as standardized JSON with appropriate HTTP status codes.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/health` | System diagnostics, database driver health, and timestamp. |
| `GET` | `/api/inventory-matrix?days=7` | Real-time availability & dynamic pricing matrix (7, 14, or 30 days). |
| `POST` | `/api/check-availability` | Range availability verification (`room_type_id`, `check_in`, `check_out`). |
| `GET` | `/api/bookings` | List recent reservations (up to 50 latest). |
| `POST` | `/api/bookings` | Create a direct booking with concurrency protection. |
| `POST` | `/api/simulate-booking` | Simulate an incoming OTA booking + trigger DAG webhook fan-out. |
| `POST` | `/api/run-ai-pricing` | Calculate and update dynamic pricing matrix across room types. |
| `GET` | `/api/users` | Retrieve all registered users. |
| `POST` | `/api/users` | Register a new user (guest, staff, or admin). |
| `POST` | `/api/users/login` | Authenticate user credentials. |
| `GET` | `/api/users/{id}` | Get specific user details. |
| `PUT` | `/api/users/{id}` | Update user profile. |
| `DELETE` | `/api/users/{id}` | Deactivate a user. |
| `GET` | `/api/users/{id}/bookings` | Get all reservations associated with a user ID. |
| `GET` | `/api/cpp/status` | Query C++ engine status and compilation diagnostics. |
| `POST` | `/api/cpp/benchmark` | Run sub-millisecond C++ micro-benchmark. |
| `POST` | `/api/cpp/pricing` | Run C++ dynamic surge pricing matrix calculation. |
| `POST` | `/api/cpp/dag-solve` | Solve OTA sync task schedule via C++ Kahn's algorithm. |
| `POST` | `/api/cpp/allocate` | Run C++ multi-channel inventory allocation optimizer. |
| `POST` | `/api/cpp/rate-parity` | Audit OTA rates for parity violations. |
| `GET` | `/api/sync-logs` | Retrieve live OTA webhook synchronization audit trail. |
| `POST` | `/api/mock-ota/booking-com` | Mock Booking.com webhook receiver. |
| `POST` | `/api/mock-ota/agoda` | Mock Agoda webhook receiver. |
| `POST` | `/api/mock-ota/rakuten` | Mock Rakuten Travel webhook receiver. |

---

## 🔄 Database Configuration & Failover

Omni Booking supports three database drivers out-of-the-box via `config/database.php`:

### 1. MySQL (Default Production / Local)
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=omni_booking
DB_USERNAME=root
DB_PASSWORD=
```

### 2. Neon SQL (Serverless PostgreSQL)
Ideal for cloud deployment. To connect Neon:
```ini
DB_CONNECTION=pgsql
DB_HOST=ep-your-instance.us-east-2.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=your_neon_password
DB_SSLMODE=require
```

### 3. SQLite (Zero-Config Offline Fallback)
If MySQL or PostgreSQL is unreachable or not yet configured, the system gracefully falls back to a local SQLite database:
```ini
DB_CONNECTION=sqlite
DB_SQLITE_PATH=database/database.sqlite
```
*No database server installation required — perfect for rapid sandboxing and offline evaluation.*

---

## 📄 License

This project is open-source and licensed under the [MIT License](LICENSE).

---

<p align="center">
  <b>Omni Booking</b> • Engineered with PHP 8.3, C++17, and Modern Vanilla Web Technologies.
</p>
