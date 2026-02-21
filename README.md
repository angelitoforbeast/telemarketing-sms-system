# TeleSMS — Telemarketing & SMS Campaign Management System

A Laravel-based multi-tenant platform for managing courier shipment imports (JNT & Flash Express), telemarketing call campaigns, and automated SMS blasting.

---

## Features

- **Multi-Courier Import Engine** — Upload JNT (`.xlsx`) and Flash Express (`.csv`) files with auto-detection and validation
- **Unified Shipment Management** — Single normalized table for all couriers with advanced filtering, search, and bulk operations
- **Telemarketing Module** — Assign shipments to agents, log call attempts with dispositions, and track callback schedules
- **SMS Campaign Engine** — Create rule-based SMS campaigns triggered by shipment status, with deduplication and daily limits
- **Role-Based Access Control (RBAC)** — Powered by Spatie Permission with 6 pre-configured roles and 20+ granular permissions
- **Multi-Tenant Architecture** — Company-scoped data isolation with a Platform Admin super-panel
- **Background Processing** — Queued import processing and scheduled SMS evaluation/sending via Laravel Jobs

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 10 |
| Frontend | Blade + Tailwind CSS (via Breeze) |
| Auth | Laravel Breeze |
| RBAC | Spatie Laravel Permission |
| File Parsing | PhpSpreadsheet (XLSX) + native CSV |
| Queue | Laravel Queue (database driver) |
| Database | MySQL (recommended) / SQLite (dev) |

---

## Requirements

- PHP 8.1+
- Composer
- Node.js 18+ & npm
- MySQL 8.0+ (production) or SQLite (development)
- PHP Extensions: `gd`, `zip`, `sqlite3` (for dev), `pdo_mysql` (for production)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/YOUR_USERNAME/telemarketing-sms-system.git
cd telemarketing-sms-system
```

### 2. Install dependencies

```bash
composer install
npm install && npm run build
```

### 3. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure database

**For SQLite (quick development):**
```bash
touch database/database.sqlite
```
Set in `.env`:
```
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

**For MySQL (production):**
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=telesms
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 5. Run migrations and seed

```bash
php artisan migrate:fresh --seed
```

### 6. Start the server

```bash
php artisan serve
```

### 7. (Optional) Start the queue worker

```bash
php artisan queue:work
```

### 8. (Optional) Start the scheduler

```bash
# Add to crontab:
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Default Accounts

| Role | Email | Password |
|------|-------|----------|
| **Platform Admin** | `admin@platform.com` | `password` |
| **Company Owner** | `owner@demo.com` | `password` |
| **Telemarketer** | `agent@demo.com` | `password` |

> **Important:** Change these passwords immediately in production!

---

## Roles & Permissions

| Role | Scope | Description |
|------|-------|-------------|
| **Platform Admin** | Global | Manages all companies, views platform-wide stats |
| **Company Owner** | Company | Full access to all company features |
| **Company Manager** | Company | Manages imports, shipments, users, SMS campaigns |
| **Telemarketer** | Company | Views assigned queue, logs calls |
| **SMS Operator** | Company | Manages SMS campaigns and views send logs |
| **Viewer** | Company | Read-only access to shipments and dashboard |

---

## Project Structure

```
app/
├── Console/Kernel.php              # Scheduler (SMS evaluation & sending)
├── Http/
│   ├── Controllers/
│   │   ├── Company/                # Dashboard, User Management
│   │   ├── Import/                 # File upload & import processing
│   │   ├── Platform/               # Platform admin panel
│   │   ├── Shipment/               # Shipment listing, assignment
│   │   ├── Sms/                    # SMS campaign CRUD & logs
│   │   └── Telemarketing/          # Call queue & logging
│   └── Middleware/
│       ├── EnsureCompanyScope.php   # Company-scoped route protection
│       └── EnsurePlatformAdmin.php  # Platform admin route protection
├── Jobs/
│   ├── ProcessImportJob.php         # Background import processing
│   ├── EvaluateSmsCampaignsJob.php  # Scheduled: queue SMS messages
│   └── SendQueuedSmsJob.php         # Scheduled: send queued SMS
├── Models/                          # 12 Eloquent models
└── Services/
    ├── Auth/RegistrationService.php
    ├── Import/                      # FileParser, ImportService, Normalization
    ├── Shipment/ShipmentService.php
    ├── Sms/SmsCampaignService.php
    └── Telemarketing/TelemarketingService.php

database/
├── migrations/                      # 17 migration files
└── seeders/                         # Roles, Statuses, Dispositions

resources/views/
├── components/                      # Reusable Blade components
├── company/users/                   # User management views
├── import/                          # Import upload & history views
├── layouts/                         # App layout & navigation
├── platform/                        # Platform admin views
├── shipments/                       # Shipment list & detail views
├── sms/campaigns/                   # SMS campaign views
└── telemarketing/                   # Call queue & form views
```

---

## SMS Template Placeholders

When creating SMS campaigns, use these placeholders in the message template:

| Placeholder | Description |
|-------------|-------------|
| `{consignee_name}` | Recipient's name |
| `{waybill_no}` | Tracking/waybill number |
| `{courier}` | Courier name (JNT/FLASH) |
| `{status}` | Current shipment status |
| `{cod_amount}` | COD amount (formatted) |
| `{item_description}` | Item description |

**Example:**
```
Hi {consignee_name}, your {courier} package {waybill_no} is ready for pickup. COD: PHP {cod_amount}. Please prepare exact amount.
```

---

## Import File Formats

### JNT Express (`.xlsx`)
Expected columns: `Waybill Number`, `Sender Name`, `Sender Phone`, `Receiver Name`, `Receiver Phone`, `Receiver Address`, `Province`, `City`, `Barangay`, `COD Amount`, `Settlement Weight`, etc.

### Flash Express (`.csv`)
Expected columns: `Tracking No.`, `Consignee`, `Phone`, `Address`, `Province`, `City`, `COD Amt`, `Status`, `PU time`, etc.

The system auto-detects the courier format and validates it against the user's selection.

---

## License

This project is proprietary software. All rights reserved.
