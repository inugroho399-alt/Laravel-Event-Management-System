# EventPulse — Enterprise Event Management System

<p align="center">
  <strong>Production-Grade Event Discovery, Ticketing, QR Check-In, and Analytics Platform</strong>
  <br>
  Built with Laravel 13, PHP 8.2+, Tailwind CSS v3, Alpine.js, and Vite.
</p>

---

## 🌟 Overview

**EventPulse** is a full-featured, scalable, multi-tenant Event Management Platform engineered for event organizers, attendees, and system administrators. It combines responsive modern user experiences with rigorous backend authorization, database-level transactional concurrency controls, pure vector SVG QR passes, and real-time gate check-in workstations.

---

## 🚀 Key Features

### 1. 🛡️ Authentication & Role-Based Authorization
- Three discrete authorization domains: **System Administrator**, **Event Organizer**, and **Attendee / Participant**.
- Server-side role middleware (`role:admin`, `role:organizer`) and granular Eloquent Policies (`EventPolicy`, `TicketTypePolicy`, `RegistrationPolicy`, `CheckInPolicy`).
- Prevention of privilege escalation: Public registration and profile modification strictly reject role tampering.
- Administrator account safeguards: Self-demotion and self-deletion are prohibited.

### 2. 🎪 Public Event Discovery & Catalog
- High-performance event search by title, description, and location.
- Lifecycle filtering (`published`, `ongoing`, `completed`, `draft`, `cancelled`).
- 16:9 banner visuals with graceful gradient fallbacks for custom graphics.
- Responsive mobile/tablet/desktop grid layouts.

### 3. 🎟️ Multi-Tier Ticketing & Concurrency Guarantees
- Flexible ticket tier creation: Free admissions and paid tiers with custom pricing and quotas.
- **Race Condition Immunity**: High-concurrency ticket reservations utilize pessimistic row locking (`lockForUpdate()`), ensuring quotas are never oversold under high traffic.
- Active registration quotas automatically restore if an attendee cancels their ticket.
- Quota down-scaling protection: Organizers cannot reduce ticket tier quotas below the current registered attendee count.

### 4. 🎫 Boarding-Pass Digital Tickets & Vector QR Code
- Luxury airline boarding-pass design (`/my-registrations/{id}`) with scalloped tear-off stub and scan-target reticle.
- Pure vector SVG QR code generation via `BaconQrCode` stored securely on the public disk.
- Zero credential leakage: QR codes encode strictly the unique alphanumeric registration token (`EVENT-REG-XXXXXXXX`).
- High-contrast print stylesheet (`@media print`) for printable physical badge passes.
- Instant 1-click clipboard copy with tactile tooltip feedback.

### 5. ⚡ Live Gate Check-In Workstation
- Real-time scanner interface for gate staff (`/organizer/events/{id}/check-in`).
- Single-transaction validation: Verifies event match, prevents duplicate entry, records checking staff ID (`checked_in_by`), and updates attendee status to `attended`.
- Rejects check-ins for `draft`, `cancelled`, and `completed` events.
- JSON API endpoint for barcode/QR hardware scanners and mobile camera integrations.

### 6. 📊 Real-Time Organizer & Admin Dashboards
- **Organizer Dashboard**: Live counts of owned events, attendee turnout rates, total ticket quotas, recent registrations, and check-in activity feeds.
- **Admin Oversight**: System-wide platform metrics, complete event catalogs, attendee audit logs, and user management with search and role filters.
- Responsive sidebar navigation: Persistent on desktop (`lg:w-64`), smooth collapsible drawer on mobile and tablet.

### 7. 📈 Reports & Formula-Sanitized CSV Exports
- Aggregate analytics: Turnout rates, capacity utilization percentages, and total event revenue.
- Filter by date boundaries (`date_from`, `date_to`), event selection, and attendee status.
- **CSV Formula Injection Mitigation**: All exported spreadsheet cells starting with dangerous calculation operators (`=`, `+`, `-`, `@`, `\t`, `\r`) are sanitized with single-quote escaping.

### 8. 🔒 Enterprise Security Hardening
- Global **SecurityHeaders** middleware:
  - `X-Frame-Options: SAMEORIGIN` (Clickjacking defense)
  - `X-Content-Type-Options: nosniff` (MIME sniffing defense)
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: camera=(self), microphone=(), geolocation=()`
- Rate limiting protection:
  - Scanner API: `throttle:120,1`
  - Ticket Registration: `throttle:60,1`
  - Account Registration: `throttle:30,1`
- Upload protection: Event banner images are restricted to raster formats (`jpeg,png,jpg,webp`, max 2MB) to prevent SVG Stored XSS.
- 100% Parameter-bound queries (zero raw SQL).
- Branded production error views (`403`, `404`, `419`, `500`).

---

## 💻 Tech Stack & Requirements

- **PHP**: 8.2 or 8.3+
- **Framework**: Laravel 13
- **Frontend**: Blade, Tailwind CSS v3, Alpine.js, Vite 7
- **Database**: SQLite (default / testing), MySQL 8.0+, or PostgreSQL 15+
- **Package Manager**: Composer 2+, Node.js 18+ (npm)

---

## 🛠️ Local Installation & Quickstart

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/inugroho399-alt/EventManagementSystem.git
   cd EventManagementSystem
   ```

2. **Install PHP & Node Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Create Database & Run Migrations with Demo Data**:
   ```bash
   touch database/database.sqlite
   php artisan migrate:fresh --seed
   ```

5. **Symlink Public Storage Directory**:
   ```bash
   php artisan storage:link
   ```

6. **Build Frontend Assets**:
   ```bash
   npm run build
   ```

7. **Start Development Server**:
   ```bash
   php artisan serve
   ```
   Access the application at `http://localhost:8000`.

---

## 👥 Default Demo Accounts

The database seeder provisions three pre-configured accounts with realistic test data:

| Role | Email | Password | Access Scope |
|---|---|---|---|
| **Administrator** | `admin@example.com` | `password` | Complete platform oversight, user administration, global event audit |
| **Event Organizer** | `organizer@example.com` | `password` | Manage events, tickets, live check-in station, reporting & CSV export |
| **Participant** | `participant@example.com` | `password` | Discover events, reserve tickets, view boarding passes with QR codes |

---

## 🧪 Running the Automated Test Suite

The codebase features an exhaustive, automated regression and security test suite covering Unit, Feature, Stress, and E2E scenarios:

```bash
# Run all 320+ automated tests
php artisan test
```

### Key Test Suites:
- [`EndToEndSystemTest.php`](tests/Feature/EndToEndSystemTest.php): End-to-end user lifecycle from registration to check-in and analytics.
- [`ConcurrencyAndEdgeCaseStressTest.php`](tests/Feature/ConcurrencyAndEdgeCaseStressTest.php): Race condition quota stress testing, boundary states, multi-tenant isolation.
- [`DatabaseTransactionIntegrityTest.php`](tests/Unit/DatabaseTransactionIntegrityTest.php): Rollback integrity on storage exceptions and foreign key cascades.
- [`SecurityReviewTest.php`](tests/Feature/SecurityReviewTest.php): IDOR penetration attempts, CSRF, security headers, role manipulation, and upload sanitization.

---

## 🌐 Production Deployment Guide

### 1. Production Environment Setup
In your production `.env`:
```dotenv
APP_NAME="EventPulse - Event Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://events.yourdomain.com

# Database (e.g., PostgreSQL or MySQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=eventpulse_prod
DB_USERNAME=eventpulse_user
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD

# Secure Session Cookies
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Cache & Storage
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

### 2. Run Database Migrations
```bash
php artisan migrate --force
```

### 3. Production Asset Build
```bash
npm ci
npm run build
```

### 4. Cache Configurations & Optimize
```bash
php artisan optimize
```
*(Caches routes, configuration, blade views, and events for maximum throughput).*

### 5. Configure Scheduled Tasks (Cron)
Add the Laravel schedule runner to your server's crontab:
```bash
* * * * * cd /var/www/eventpulse && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Queue Workers (Supervisor Configuration)
If using background queue processing:
```ini
[program:eventpulse-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/eventpulse/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/eventpulse/storage/logs/worker.log
stopwaitsecs=3600
```

### 7. Nginx Web Server Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name events.yourdomain.com;
    root /var/www/eventpulse/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 📄 License

The EventPulse Event Management System is open-sourced software licensed under the [MIT License](LICENSE).
