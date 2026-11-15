# RekaCy — Global Logistics Intelligence & Exception Management System

> **GLOBAL VISIBILITY. INTELLIGENT LOGISTICS. COMPLETE CONTROL.**

RekaCy is a dark, map-centred **logistics command center**. Operators manage shipments, tracking history, delays and
exceptions; customers follow **only their own** cargo. Every number on every screen is queried from the database, and
every status change is validated by backend business rules.

Built for *Web Programming 2 — UAS* (Laravel, relational DB, REST API, map + external API, security, tests, docs).

## What you get

| Area | Implemented |
|---|---|
| **Command Center** (`/dashboard`, staff) | 6 KPI cards with 7-day trends, large dark Leaflet world map (cyan/gold/red/green glowing routes, animated remaining legs, ports, exception markers, popups, legend, route + carrier filters), activity / status / regional / carrier / operational panels, critical alerts, high-priority exceptions, latest events, audit activity, and the four bottom cards (Alerts, Route Deviations, Delay Monitoring, Risk Indicators) |
| **Exception center** (`/exceptions`) | KPIs (total, critical, high priority, investigating, resolved), exception map, 30-day severity chart, priority list, alerts, risk indicators, filters (date, type, severity, status, carrier, location, assignee); detail page with assignment, severity change, internal notes, resolve, related event history |
| **Operations** (`/operations`, `/eta`, `/tracking`, `/alerts`) | Operator control center, ETA monitor with the delay rule's explanation, global route monitoring with searchable route list, derived alert feed |
| **Shipments** | 3-step creation wizard, edit, lifecycle `CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED` (+ `CANCELLED`), append-only tracking timeline, priority, items, weather panel, documents + proof of delivery |
| **Smart ETA engine** | `delay = ETA (or actual delivery) − expected arrival`; LOW ≤ 6 h, MEDIUM ≤ 24 h, HIGH above — thresholds in `.env`, reasoning shown in the UI |
| **Carrier analytics / reports** | On-time, delay and exception rate per carrier; reports with filters, **CSV export** (formula-injection safe) and print-friendly page for **PDF via the browser** |
| **Documents & POD** | Private storage (never public), PDF/JPG/PNG ≤ 5 MB, authorised preview/download, POD requires recipient + time |
| **Support tickets** | Customer tickets, staff replies, internal notes, priority, assignment, status workflow |
| **Search & command palette** | Global search (role-scoped) and `Ctrl K` palette with navigation + live results |
| **Auth** | Login, register (customers only), forgot/reset password, profile + password change, Sanctum API tokens |
| **Shared UI** | Collapsible sidebar with tooltips, breadcrumb, clock, system status, notifications, 403/404/419/500/503 pages, empty and loading states |
| **Public** | Landing, About, public tracking (limited data) |

### Honest scope notes
- **Positions are simulated.** The seeded dataset uses invented tracking events; maps show a *SIMULATED TRACKING DATA* label while `REKACY_DEMO_DATA=true`. RekaCy never fabricates live GPS: a shipment with no located event simply has no marker.
- **Roles** are a fixed enum (`admin`, `operator`, `customer`) enforced by policies/gates; there is no editable roles/permissions table, so the "Role Management" page is replaced by role assignment on the Users page.
- **PDF export** is the browser's *Save as PDF* from the print view (no server-side PDF library is bundled).
- **Route deviation** = open `MISROUTED` exceptions (no geometry-based detection). **Risk score** is a documented additive rule (see `RiskService`), not machine learning.
- The map uses OpenStreetMap tiles with a CSS dark filter (no third-party tile provider needed).
- The "Live Tracking" and "Global Map" items of the brief are one screen: **Global Tracking**.
- **This build was written without a PHP runtime and has NOT been executed.** Run `php artisan test` and fix what it reports before submitting.

## Tech stack

PHP **8.4**, Laravel **12**, SQLite (default; MySQL 8 also supported), Eloquent, Blade, Tailwind CSS 4 (Vite), vanilla JS (Fetch), Leaflet, Chart.js, Laravel Sanctum, PHPUnit.

> The brief says "Laravel 8.4". Laravel 8.x does not run on PHP 8.4, so this project targets **PHP 8.4 + Laravel 12**
> (the stack recommended in the brief). Change `composer create-project laravel/laravel:^12.0` if your campus needs another stable version.

## Installation

This repository is a **complete Laravel 12 project** (no skeleton to merge, nothing to overwrite).

**Windows, one click:** extract the zip anywhere and double-click `setup.bat` (first run: Composer + npm + database + demo data),
then `jalankan.bat` next time.

**Manual:**

```bash
composer install
npm install && npm run build
php artisan migrate:fresh --seed     # SQLite file database/database.sqlite is included; .env is pre-configured
php artisan serve                    # http://127.0.0.1:8000
php artisan schedule:work            # optional: 15-minute delay detector
```

Demo accounts (password `password`): `admin@rekacy.test`, `operator@rekacy.test`, `customer@rekacy.test` (also `courier@rekacy.test`, `courier2@…`, `courier3@…`, `operator2@…`, `customer2@…`, `customer3@…`).
MySQL: set `DB_CONNECTION=mysql` and the `DB_*` keys in `.env`, create an empty `rekacy` database first.


## Real-time GPS, WebSocket & courier workflow (v3)

| Piece | How it works |
|---|---|
| **Courier role** | `courier` users + `couriers` profile (vehicle, availability, `is_tracking`, `last_seen_at`). Mobile-first UI at `/courier` (Indonesian). A courier only sees/acts on tasks assigned to them (policy + scoped queries). |
| **Task workflow** | Staff assign a courier to a `BOOKED` shipment → `ASSIGNED → ACCEPTED → PICKED_UP → IN_TRANSIT → ARRIVED → COMPLETED`, or `FAILED` (opens a `DELIVERY_FAILED` exception). Each step moves the shipment through `TrackingService`, so history, notifications and audit stay consistent. Completing needs recipient name + photo (stored as POD). One in-progress delivery per courier. |
| **Live GPS** | Courier taps *Mulai pelacakan* → `navigator.geolocation.watchPosition()` → `POST /courier/location` (or `/api/courier/location` with a token): latitude, longitude, accuracy, speed, heading, device timestamp. Client throttles (≥10 s, or ≥15 m moved), queues points offline (max 200, 9 min) and flushes on reconnect. Server validates ranges, rejects future/stale timestamps, accepts GPS **only** while the task is PICKED_UP/IN_TRANSIT/ARRIVED **and** tracking was started, skips duplicates, and rate-limits (40/min). Every point is stored in `courier_locations`. |
| **WebSocket** | Laravel Reverb + Echo. Private channels: `staff.live`, `shipment.{id}` (staff or the owning customer), `App.Models.User.{id}`. Events (`ShouldBroadcastNow`, no queue worker needed): `courier.location`, `shipment.updated` (includes DB-computed metrics), `user.notified`. Broadcast failures are swallowed so a stopped Reverb never breaks a request. |
| **Live admin dashboard** | KPI cards + *Live operations* strip update from `shipment.updated`; courier markers move on the map from `courier.location`. Without WebSocket the page polls `/live/metrics` and `/live/couriers` every 15 s and the header shows **OFFLINE · POLLING**. |
| **Customer tracking** | Shipment page shows live marker, `LIVE GPS` / `SIGNAL LOST` (no point for 90 s) / `NO GPS`, last-update age and an **ETA estimate** (straight-line × 1.3 ÷ measured speed, clamped 15–60 km/h, default 30). After completion/failure nothing about the courier's position is exposed. |
| **Privacy** | No tracking unless the courier starts it; stops automatically on complete/fail/cancel/re-assign; broadcast payloads carry no personal data; public `/track` never shows coordinates. |

**Status mapping (your 9-step flow → implemented states):** Pesanan dibuat = `CREATED` · Menunggu konfirmasi / Diproses = `CREATED` → `BOOKED` (staff action) · Menunggu kurir = `BOOKED` without active assignment · Kurir ditugaskan = assignment `ASSIGNED/ACCEPTED` · Kurir mengambil barang = `PICKED_UP` · Dalam perjalanan = `IN_TRANSIT` · Sampai di tujuan = assignment `ARRIVED` · Pengiriman selesai = `DELIVERED` · Gagal = assignment `FAILED` + exception; Dibatalkan = `CANCELLED`/assignment `CANCELLED`. The existing lifecycle was kept (no breaking change); courier screens use Indonesian labels.

### Run it with WebSocket

```bash
php artisan serve            # terminal 1
php artisan reverb:start     # terminal 2  (jalankan.bat opens both)
```

`.env` already contains `BROADCAST_CONNECTION=reverb` and local `REVERB_*` keys (change them for deployment). Set `BROADCAST_CONNECTION=null` to turn WebSockets off.

### Testing GPS

- **Same laptop:** open `http://127.0.0.1:8000` (treated as a secure context); log in as `courier@rekacy.test`, open a task that is *PICKED_UP/IN_TRANSIT*, tap **Mulai pelacakan**, allow location.
- **Phone:** browsers require **HTTPS** for geolocation on non-localhost origins. Use a tunnel (e.g. `cloudflared`/`ngrok`) or deploy with TLS; Reverb must then also be reachable over `wss`.
- **No device GPS (lecturer demo):** the *Simulasikan perjalanan (DEMO)* button sends clearly labelled `source=demo` points toward the destination; the UI shows **DEMO** wherever such a point is displayed.

### Honest status of this version
- Written **without a PHP runtime — nothing has been executed**. The new tests (`CourierWorkflowTest`, `CourierGpsTest`, `RealtimeBroadcastTest`) cover access control, task rules, POD, GPS validation/rate limit/privacy/ETA, event dispatch and channel authorisation, but they have **not been run**. Real WebSocket delivery (Reverb ↔ browser) is **not** covered by automated tests; verify it manually with the steps above.
- Seeded data contains couriers and tasks but **no GPS rows** (no fake live positions).
- Not implemented: native mobile app / background GPS (a browser tab must stay open), Redis scaling, push notifications when the tab is closed, server-side route snapping, SMS/WhatsApp.
- Courier accounts are created by an admin (*Couriers* page) — there is no self-registration for couriers.

## Running the tests

```bash
php artisan test
```

Tests use in-memory SQLite (see `phpunit.xml`). They cover: valid/invalid login, access without login,
role isolation (customer vs. customer, customer vs. staff), shipment CRUD + validation, mass-assignment
protection, valid and invalid status transitions, terminal states, chronological tracking rules, current
location, ETA/delay boundaries and configurable thresholds, exception workflow, the delay command,
dashboard scoping, filters/pagination and the public tracking page.

> **Status of this build:** the code was written without access to a PHP runtime, so it has **not been executed yet**.
> Run `php artisan test` first; fix anything it reports, then commit. Do not claim a green suite before you have seen one.

## Group scenario / configuration

Change these in `.env` (see `config/rekacy.php`) — no code change needed:

```
DELAY_LOW_MAX_HOURS=6      # delay ≤ this  → LOW
DELAY_MEDIUM_MAX_HOURS=24  # delay ≤ this  → MEDIUM, above → HIGH
DELAY_THRESHOLD_HOURS=12   # group threshold: delays ≥ this are "escalated" (extra alert wording)
REKACY_GROUP_EXCEPTION=CUSTOMS_HOLD   # default exception type in the form
```

Group A scenario: Southeast Asia → Europe, main exception `CUSTOMS_HOLD`, KPI = on-time delivery rate.

## Architecture

```
Browser ─► Routes + Middleware (auth, role, throttle)
        ─► Controllers (HTTP only) + FormRequests (validation + authorize)
        ─► Services: ShipmentService · TrackingService · EtaService · ExceptionService
                     DashboardService · AlertService · AuditLogger
                     GeocodingService · WeatherService (HTTP Client)
        ─► Eloquent models ─► MySQL
```

Web and API controllers are thin and call the **same** services, so business rules exist once.
Diagrams: [`docs/ERD.md`](docs/ERD.md), [`docs/DATA_DICTIONARY.md`](docs/DATA_DICTIONARY.md).

## REST API

Base URL `/api`, JSON, Sanctum bearer tokens. Postman collection: [`postman/RekaCy.postman_collection.json`](postman/RekaCy.postman_collection.json).

| Method | Endpoint | Who |
|---|---|---|
| POST | `/register`, `/login` | public (throttled) |
| POST | `/logout` · GET `/user` · GET `/dashboard` | authenticated |
| GET | `/shipments` (filters: `q,status,carrier_id,origin_id,destination_id,from,to,delay,sort,direction,per_page`) | all (customers see own) |
| POST | `/shipments` | admin, operator |
| GET | `/shipments/{id}` | owner or staff |
| PUT/PATCH | `/shipments/{id}` | admin, operator |
| DELETE | `/shipments/{id}` | admin (CREATED/CANCELLED only) |
| GET/POST | `/shipments/{id}/tracking` | read: owner/staff · write: staff |
| GET/POST | `/shipments/{id}/exceptions` | read: owner/staff · write: staff |
| PATCH | `/exceptions/{id}` | staff |
| GET/POST | `/carriers`, `/locations` | read: staff · write: admin |
| GET | `/shipments/{id}/weather`, `/geocode?q=` | owner/staff · staff |

Status codes: `200/201/204` success, `401` unauthenticated, `403` forbidden, `404` missing, `422` validation or business-rule violation.
Error body: `{"message": "...", "errors": {...}}` — never a stack trace.

Example — advance a shipment:

```http
POST /api/shipments/12/tracking
Authorization: Bearer <token>

{"status":"PICKED_UP","location_id":3,"description":"Cargo picked up","occurred_at":"2026-11-03 09:30:00"}
```
```http
POST /api/shipments/12/tracking     (current status CREATED)
{"status":"IN_TRANSIT"}
→ 422 {"message":"Invalid status transition CREATED → IN_TRANSIT. Allowed from CREATED: BOOKED, CANCELLED.", ...}
```

## Security

Password hashing (bcrypt via `hashed` cast), CSRF on all web forms, Blade escaping (+ escaped JS popups), Eloquent bindings (no raw user SQL),
`$fillable` whitelists (role, status, delay fields and owner are never mass-assignable), server-side policies on every route/endpoint,
login/API throttling, token revocation for disabled users, `.env` excluded from Git.

## Deployment

Any PHP 8.4 host with MySQL or SQLite (VPS, Laravel Forge, Railway, shared hosting with SSH):

1. `git clone`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`
2. Set `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`, DB credentials and a contact e-mail in `GEOCODING_USER_AGENT`
3. `php artisan migrate --force` (+ `--seed` once for demo data), `php artisan config:cache route:cache view:cache`
4. Cron: `* * * * * php /path/artisan schedule:run` ; queue worker optional (notifications are synchronous)
5. Point the web root to `public/`

A GitHub Actions workflow (`.github/workflows/ci.yml`) runs the tests on every push.

## Known limitations & future work

- Demo data is **synthetic** (carriers, vessels, routes, coordinates are approximate and invented).
- Positions come from operator-entered tracking events; there is no live GPS feed from physical devices.
- No payments, microservices, ML or real carrier/customs integrations (out of scope in the brief).
- With SQLite, row locking (`lockForUpdate`) has no effect, so concurrent status changes are less protected than on MySQL; fine for demo use.
- Shipment numbers use `max(id)+1`; the unique index protects against a race, a retry would be the next step.
- Email notifications are implemented but off by default (`REKACY_MAIL_NOTIFICATIONS`).
- CDN-hosted Leaflet/Chart.js should be self-hosted or version-locked with SRI hashes in production.

## Attribution

Laravel, Sanctum, Tailwind CSS, Vite, Leaflet, Chart.js (MIT); map tiles © OpenStreetMap contributors (ODbL);
geocoding by Nominatim; weather by Open-Meteo (CC BY 4.0). Inter typeface (OFL) via Bunny Fonts.
All application code in this repository was written for this project; AI assistance was used and the code must be
reviewed and understood by the student before submission.
