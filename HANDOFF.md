# Car Tracker — Project Handoff

A personal **car maintenance tracker**. Log services (oil, tires, brakes, etc.) with date, mileage, time, cost, and notes; the app tracks history and predicts when each service is next due based on how much you drive.

This document is written so a **new Claude Code session (or any developer)** can continue the work with full context. Read it top to bottom.

---

## 1. Current status (as of this handoff)

- The app is **built, working, and tested** as a single self-contained HTML file, now with a **PHP + MySQL server backend on Hostinger**.
- **Server-side sync is implemented:** shared household login, auto-save (debounced, background), auto-load on open, offline fallback via `localStorage`.
- Hosting confirmed: **Hostinger Business plan** (`hostinger_business_v5`), PHP 8.3, MySQL/PDO available. Website: `darkgray-seahorse-474215.hostingersite.com` (no custom domain attached yet — a free domain is pending setup in the account).
- The MySQL database (`u526894368_cartracker`) was created via the Hostinger API. **The PHP files still need to be uploaded to `public_html` by the owner** (no file-manager/FTP API was available to do this remotely) — see §6.
- Data model, service logic, and UI are unchanged from the original offline-only version — see §4/§5.

---

## 2. Files

| File | What it is |
|------|------------|
| `index.html` | **The entire app** — one self-contained file (inline HTML/CSS/JS). Now includes a login screen and server-sync logic (see §6). |
| `api/config.example.php` | Template for server config — copy to `api/config.php` on the server and fill in real values. **`config.php` is gitignored; never commit real credentials.** |
| `api/db.php` | PDO connection + table bootstrap + session helpers. |
| `api/login.php` | Verifies the shared username/password, starts session, has basic per-IP rate limiting. |
| `api/load.php` | Session-guarded; returns the saved JSON blob. |
| `api/save.php` | Session-guarded; upserts the JSON blob. |
| `api/logout.php` | Destroys the session. |
| `api/.htaccess` | Blocks direct web access to `config.php`. |
| `HANDOFF.md` | This document (continuation notes; not part of the app). |

---

## 3. How to run / test

- **Use it live:** once deployed, just visit the site URL — no install/build needed.
- **Local testing without a server:** open `index.html` directly; it will show the login screen, fail to reach `api/`, and fall back to offline/local mode automatically (see §6). To test the full login+sync flow locally, serve the folder over HTTP with PHP's built-in server: `php -S localhost:8777` from the project root, then visit `http://localhost:8777`. (No Node/Python needed — PHP is enough now that the backend is PHP.)

---

## 4. Architecture & data model

- **Vanilla JS**, no frameworks, no build step. Everything (HTML/CSS/JS) is inline in `index.html`.
- Code is one IIFE. Key functions: `load/normalize/seed/persistLocal/save` (persistence), `milesPerDay`, `latestOdometer`, `computeStatus` (per-service due calc), `primaryMetric` (the big "mi left" number), plus render functions, modal handlers, and the sync/auth block (`boot`, `loadFromServer`, `pushToServer`, `doLogin`, `doLogout`).
- **`localStorage` key:** `carTracker.v1` — now used as an **offline cache**, not the source of truth. The server (`api/`) is the source of truth when reachable.
- **Data shape:** unchanged —
  ```json
  {
    "vehicles": [
      {
        "id": "id...",
        "name": "Volvo EX30",
        "year": "2024", "make": "Volvo", "model": "EX30",
        "photo": "",              // data:image/jpeg;base64 string (downscaled) or ""
        "currentMiles": 64000,
        "services": [
          { "id": "id...", "typeId": "oil", "customName": "", "date": "2026-05-01",
            "mileage": 59900, "time": "45 min", "cost": 42.5, "notes": "5W-20 synthetic" }
        ],
        "intervals": [ { "id": "oil", "name": "Oil change", "miles": 5000, "months": 6 }, ... ]
      }
    ],
    "currentVehicleId": "id...",
    "settings": {}
  }
  ```
- **Default service intervals** (editable per vehicle): oil 5,000mi/6mo, tire rotation 6,000/6, brakes 40,000/36, spark plugs 30,000/36, engine air 20,000/24, cabin air 15,000/12, coolant 50,000/60, transmission 60,000/60.
- **Due logic:** unchanged. Status is the worse of mileage-based and time-based. States: `ok` / `warn` (>=85%) / `overdue` / `none`.

---

## 5. Features (all implemented)

Same as before (log/edit/delete services, dashboard, spotlight card, driving-rate estimate, multi-vehicle, editable intervals, photo upload), **plus**:

- **Shared login screen** — one household username/password gates the app.
- **Auto-save**: every change saves to the server in the background, debounced ~800ms. A small pill in the top bar shows **Saving… / Saved / Offline — saved locally**.
- **Auto-load**: on open (or after login), the app fetches the latest data from the server.
- **Offline fallback**: `localStorage` still caches the last-known data, so the app keeps working without a connection and re-syncs on reconnect (`online` event triggers a retry push).
- **Backup/Restore UI removed** — replaced with a single **"Download a copy (backup)"** button in Settings (JSON export only; no restore/import UI, no auto-download-on-save).
- **Log out** button added to Settings.
- VIN auto-decode remains removed (do not re-add unless asked).

---

## 6. Server backend — status: code done, **deployment pending**

### What's already done
- Confirmed hosting plan supports PHP/MySQL (Business plan, PHP 8.3, PDO + mysqlnd enabled).
- **MySQL database created on the live account** via the Hostinger API:
  - Database: `u526894368_cartracker`
  - DB user: `u526894368_cartracker`
  - Host: `localhost` (standard for same-account PHP→MySQL on Hostinger)
  - (Password was generated and shared with the owner directly in chat — not stored in this repo or its history.)
- All PHP API files written (`api/*.php`) — single shared login (`APP_USERNAME` / bcrypt `APP_PASSWORD_HASH` in `config.php`), single `app_data` row holding the whole JSON blob (mirrors the existing single-object client data model), session-based auth (HttpOnly cookie, 30-day persistence), basic per-IP rate limiting on login (lockout after 8 failed attempts / 15 min).
- `index.html` updated with login screen, auto-save/auto-load, offline fallback, sync status pill, simplified Settings menu.

### What's NOT done yet — owner action required
There is **no remote file-upload capability** available to Claude for this hosting account (the Hostinger API covers billing/domains/database/PHP-config management, but not a file manager or SSH/SFTP for shared hosting). The owner (or a future session with file-manager access) must:

1. **Upload the files** to `public_html` on `darkgray-seahorse-474215.hostingersite.com` (via hPanel **File Manager**, or an FTP client using credentials from hPanel → **Hosting → Advanced → FTP Accounts**):
   - `index.html` → `public_html/index.html`
   - The whole `api/` folder → `public_html/api/`
2. **Create `api/config.php` on the server** (it is *not* in git). Use `api/config.example.php` as the template. Real values were provided to the owner directly in this conversation (DB credentials + app login + bcrypt hash) — paste them in via File Manager's code editor, then save.
3. Visit the site. The login screen should appear; log in with the shared household username/password.
4. (Optional, recommended) Connect a real custom domain to this website in hPanel once one is ready — currently it's a free `*.hostingersite.com` domain pending setup.

### Design decisions locked in (for future reference)
- **Auth model:** one shared username + password for the whole household (not per-person accounts).
- Data is stored as a single JSON blob per the `app_data` table (`id`, `data` LONGTEXT, `updated_at`) — matches the existing client-side single-object model, avoids a costly normalization effort.
- `config.php` is gitignored; DB/app credentials must never be committed.

---

## 7. Design system — `index.html` (studio) palette

Light theme; white/near-white surfaces kept intentionally. Owner-provided palette, mapped to roles:

| Hex | Role |
|-----|------|
| `#1A2730` | ink / all text / logo (navy) |
| `#45586C` | on-track status (slate) |
| `#B0CEE2` | "all caught up" spotlight (powder blue) |
| `#A63E1B` | due-soon (rust) |
| `#E95D2C` | brand / primary button / overdue alert (orange) |
| `#E9EAEC` surface, `#FBFBFC` cards, `#C9CBCF` outer mat | light neutrals |

CSS variables live in `:root`: `--accent` (#E95D2C), `--sky` (#B0CEE2), `--green` (=slate, on-track), `--amber` (=rust, warn), `--red` (=orange, overdue), `--ink`, `--muted`, `--surface`, `--card`.

---

## 8. Handy notes for the next session

- The owner is **non-technical** — explain steps plainly, give exact clicks, avoid jargon, and confirm before irreversible actions.
- **Immediate open item:** walk the owner through uploading `index.html` + `api/` to `public_html` and creating `api/config.php` (see §6) — that's the only remaining step before the backend is live.
- If a future session has file-manager or SSH/SFTP access to Hostinger, it could complete the upload itself instead of walking the owner through it.
- Keep `config.php` out of git, always.
