# Comic Tracker — Project Handoff

A personal **comic book collection tracker**. Photograph a cover with your phone, type in the
**character**, the **book name** and the **issue number**, and the app files it away — grouped by
character and by book, with cover thumbnails, search, condition/value tracking, and missing-issue
detection for runs you are collecting.

It is a **separate website** from Car Tracker: its own folder here, its own Hostinger site, its own
login. Nothing in this folder touches the Car Tracker app at the repo root.

---

## 1. Current status

- App is **built and tested**: single self-contained `comics/index.html` plus a small PHP + MySQL API.
- Tested end-to-end against a real MySQL server: login, add/edit/delete, cover upload, cover replace
  (old files removed), offline queueing, reconnect sync, session expiry, and the live camera capture
  path (photo → downscaled cover + thumbnail → row in MySQL + files on disk).
- **Not deployed yet** — the files still need to be uploaded to a Hostinger website's `public_html`,
  and `api/config.php` created there (see §6). No file-manager/FTP API is available to Claude for this
  hosting account, so the owner does that step.

## 2. Files

| File | What it is |
|------|------------|
| `index.html` | **The entire front end** — one self-contained file (inline HTML/CSS/JS), no build step. |
| `api/config.example.php` | Template for server config — copy to `api/config.php` on the server and fill in. **`config.php` is gitignored; never commit real credentials.** |
| `api/db.php` | PDO connection, table bootstrap, session helpers, image validation/storage helpers. |
| `api/login.php` | Verifies the shared username/password, starts the session, per-IP rate limiting. |
| `api/logout.php` | Destroys the session. |
| `api/list.php` | Session-guarded; returns every book as JSON (no image bytes, just filenames). |
| `api/save.php` | Session-guarded; inserts or updates one book, writes/replaces its cover files. |
| `api/delete.php` | Session-guarded; deletes one book and its cover files. |
| `api/.htaccess` | Blocks direct web access to `config.php`; no directory listing. |
| `uploads/covers/` | Where cover JPEGs are written. `.htaccess` above it blocks script execution and listing. |
| `fonts/OFL-*.txt` | Licence texts for the two embedded webfonts. Reference only — nothing to upload. |
| `HANDOFF.md` | This document. |

## 3. How to run / test

- **Live:** visit the site URL. Log in with the shared household login.
- **Local:** from the `comics/` folder run `php -S localhost:8899`, then open `http://localhost:8899`.
  You need a MySQL database and an `api/config.php` pointing at it. Opening `index.html` as a plain
  file also works, but with no server it stays in offline mode and cannot log in.
- **Camera note:** the live in-page camera needs **HTTPS** (or `localhost`). Hostinger sites have free
  SSL, so this is fine in production. Where the camera API is unavailable the app automatically falls
  back to the phone's own camera/photo picker, so nothing breaks.

## 4. Design system

Styled after a comic-book publisher landing page the owner picked as the reference: cream paper, a
thin red frame around the whole app, heavy black display type, red bands for section headers.

| Token | Value | Role |
|-------|-------|------|
| `--paper` | `#EAE3D9` | page behind the frame |
| `--panel` | `#F4F0E9` | inside the frame, and modal surfaces |
| `--card` | `#FFFFFF` | cards, inputs |
| `--ink` | `#14110F` | all text, the "Books" stat block, modal borders |
| `--muted` | `#7C7368` | secondary text, micro-labels |
| `--line` | `#DCD3C6` | hairlines and card borders |
| `--red` | `#EE2733` | brand: frame, primary buttons, group bands, issue badges, accents |
| `--red-dark` | `#C7141F` | hover on red |

- **Type:** `Archivo Black` for every heading, stat number, card title and the wordmark (uppercase,
  tight tracking); `Archivo` (variable, 100–900) for body text and the letterspaced uppercase
  micro-labels. Both are **embedded in `index.html` as base64 latin subsets**, so the app makes no
  third-party requests and the type is right even offline. They are SIL Open Font License 1.1 fonts
  by Omnibus-Type; the licence texts are in `fonts/`. If you ever swap them out, keep the two-family
  split — display face for headings, text face for everything else.
- **Recurring devices:** the `.eyebrow` label (a short red rule then letterspaced red uppercase) above
  a big display heading; a trailing red full stop on the wordmark and page title; group headers as
  solid red bands with white pill chips inside; the issue number as a red tab notched into the
  top-right of each cover.
- Cards, inputs and modals use small radii (6–10px) with pill-shaped buttons — sharp panels, round
  actions, as in the reference.

## 5. Architecture & data model

- **Vanilla JS**, no frameworks, no build step; one IIFE in `index.html`. Same design system as Car
  Tracker (same palette, cards, pills, modals, Ironmane Labs logo).
- **Row per book in MySQL** (not one JSON blob like Car Tracker) — a collection grows large and each
  book has a photo, so a blob would be re-uploaded in full on every edit.
- **Covers are files, not database rows.** The browser downscales each photo to two JPEGs — a full
  cover (max 1400px, q .84) and a thumbnail (max 440px, q .78) — posts them as data URIs, and the
  server writes them to `uploads/covers/` with random filenames. The DB stores only the filenames.
  Grids load thumbnails, the detail view loads the full cover.
- **`client_id`** — every book gets an id generated in the browser. `save.php` upserts on it, so a
  retried save after a dropped connection can never create a duplicate.
- **Table `comics`** (created automatically on first API call):
  `id, client_id, character_name, series, issue, issue_sort, variant, publisher, year, grade, value,
  paid, acquired, tags, notes, cover_file, thumb_file, created_at, updated_at`.
  `issue_sort` is the numeric part of `issue`, so `#12A` and `Annual 4` still sort sensibly.
- **Offline behaviour:** `localStorage` key `comicTracker.v1` caches the library, the pending-change
  queue (outbox), and view preferences. Add or edit a book with no signal and it is saved on the
  device, marked **Not synced**, and pushed automatically on reconnect (or 20s retry). If storage
  fills up, the offline copy of the *full-size* pending photo is dropped and the thumbnail is kept.
- **Rejections are visible, never silent.** If the server refuses a book (e.g. `uploads/covers` not
  writable), the book stays in the app marked **Not saved** with the server's reason shown in its
  detail view, and it survives a reload — re-saving it after the problem is fixed pushes it up.
- **Auth:** one shared household username + password (same model as Car Tracker), session cookie
  HttpOnly, 30-day life, lockout after 8 failed logins for 15 minutes. The cookie is named
  `comictracker_sid` (not the default `PHPSESSID`) and the login rate-limit file is its own, so this
  app's login is independent of Car Tracker's even if the two ever share a domain.

### Features
Photograph a cover in-page (with flip-camera and a file-picker fallback) · character / book name /
issue number / condition, plus optional publisher, year, variant, value, paid, date acquired, tags,
notes · **Save & add another** carries the character/book/publisher over and pre-fills the next issue
number, for cataloguing a stack fast · typing `Amazing Spider-Man #300` into the book-name field
splits the issue number out by itself · autocomplete from characters/books/publishers already entered
· three views: Library grid, grouped by Character, grouped by Book (with `#300–#303 · Missing #302`
run analysis) · search across every field · filter by character · sort by added/book/character/issue/
value · totals for books, characters, book titles and collection value · edit and delete · CSV export.

## 6. Deploying to Hostinger — owner action required

Hosting is the same account as Car Tracker (Business plan, PHP 8.3, MySQL/PDO, account `u526894368`).
That account already hosts two unrelated sites — **Turnkey General Contractor**
(`darkgray-seahorse-474215.hostingersite.com`) and **Car Tracker**
(`mediumvioletred-alligator-245269.hostingersite.com`). **Give Comic Tracker its own third website**
so nothing overlaps.

1. **Create the website.** hPanel → **Websites → Create or migrate a website** → use a free
   `*.hostingersite.com` domain (or a real domain if you have one). Note the new site's name; every
   step below happens on **that** site, not the two above.
2. **Create the database.** hPanel → **Databases → MySQL Databases** → create a database and user
   (e.g. `u526894368_comics`). Copy the database name, username and password.
   *Alternative:* you can reuse the Car Tracker database credentials — this app only creates a table
   called `comics`, which does not collide with Car Tracker's `app_data` table. A separate database is
   cleaner, but reusing is safe if you would rather not manage another one.
3. **Upload the files** (hPanel → **File Manager**, or FTP with credentials from
   **Hosting → Advanced → FTP Accounts**) into the new site's `public_html`:
   - `comics/index.html` → `public_html/index.html`
   - `comics/api/` (the whole folder) → `public_html/api/`
   - `comics/uploads/` (the whole folder, including `covers/` and its `.htaccess`) → `public_html/uploads/`
4. **Create `api/config.php` on the server.** It is deliberately not in git. In File Manager, copy
   `api/config.example.php` to `api/config.php`, open it in the editor, and fill in:
   - the three `DB_*` values from step 2 (`DB_HOST` stays `localhost`),
   - `APP_USERNAME` — the household login name you want,
   - `APP_PASSWORD_HASH` — a bcrypt hash of your password. Generate it in hPanel → **Advanced →
     SSH/Terminal** (or ask in chat) with:
     `php -r 'echo password_hash("your-password-here", PASSWORD_BCRYPT), PHP_EOL;'`
5. **Check the covers folder is writable.** In File Manager, right-click `public_html/uploads/covers`
   → Permissions → `755` (Hostinger's default is usually fine). If it is wrong, the app will tell you
   in plain words the first time you save a book with a photo, and keep the book so nothing is lost.
6. **Open the site over `https://`** and log in. Add a book, take a photo, confirm the cover appears.

## 7. Notes for the next session

- The owner is **non-technical** — explain steps plainly, give exact clicks, confirm before anything
  irreversible.
- Keep `config.php` out of git, always. Same for anything under `uploads/covers/` (already ignored).
- The API surface is small and stable: `login`, `logout`, `list`, `save`, `delete`. Adding a field
  means one column in `db.php`'s `CREATE TABLE`, one line in `save.php`, one in `list.php`, and the
  form/detail markup in `index.html`. Existing installs need the column added by hand (the bootstrap
  only creates the table when missing).
- Ideas deliberately left out for now: wishlist / "want" list, per-book multiple photos, barcode or
  cover-image lookup against an external comics database, price-guide integration, per-person logins.
