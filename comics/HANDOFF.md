# Comic Tracker — Project Handoff

A personal **comic book collection tracker**. Photograph a cover with your phone, type in the
**character**, the **book name** and the **issue number**, and the app files it away — grouped by
character and by book, with cover thumbnails, search, condition/value tracking, and missing-issue
detection for runs you are collecting.

It is a **separate website** from Car Tracker: its own folder here, its own Hostinger site, its own
login. Nothing in this folder touches the Car Tracker app at the repo root.

---

## 1. Current status

- **Live infrastructure is created** (2026-08-22, via the Hostinger API):
  - Website: **`darkslategray-mosquito-683437.hostingersite.com`** — its own site on order `1009816343`,
    root `/home/u526894368/domains/darkslategray-mosquito-683437.hostingersite.com/public_html`, PHP 8.3.30,
    PDO/mysqlnd and GD enabled, `post_max_size` 2048M.
  - Database: **`u526894368_comictracker`**, user `u526894368_comictracker`, assigned to that website,
    host `srv450.hstgr.io` (config uses `localhost`, the standard for same-account PHP→MySQL).
    Password was given to the owner directly — never stored in this repo.
  - Nothing is shared with Car Tracker: separate site, separate database and user, separate login,
    separate session cookie name.
  - **Remaining:** copy the files into that site's `public_html`. Claude could not do this step because
    the session's network policy blocked `srv450-files.hstgr.io` (the file-upload host) and every other
    `*.hstgr.io` / site address — the Hostinger API tools reach the account through the MCP proxy, but
    plain HTTPS to the upload endpoint is refused with a gateway 403. A session whose environment allows
    `*.hstgr.io` could finish it with `hosting_generateUploadURLV1` (TUS upload, see that tool's docs);
    otherwise the owner uploads a zip through hPanel → File Manager and extracts it.
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
| `api/identify.php` | Session-guarded; sends a cover photo to the Claude API and returns the fields it can support, for the form to prefill. Writes nothing. |
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

### Reading covers with Claude (optional)

Set `ANTHROPIC_API_KEY` in `config.php` and the add-a-book form starts filling itself in from the
photo. Leave it empty and the feature disappears completely — `list.php` returns `ai: false`, the
button and the settings toggle stay hidden, and typing the fields in by hand works exactly as before.

- **Flow:** photo taken → `identify.php` posts it to the Messages API → the reply prefills the form →
  **you** check it and save. It never saves for you, and it only fills fields you have left empty, so
  anything you typed always wins.
- **Request shape** (verified against the current docs): `claude-opus-5`, image block before the text
  block, `output_config.format` a `json_schema` so the reply is always parseable JSON, and
  `output_config.effort: "low"` — this is a short extraction, not a reasoning task. Headers are
  `x-api-key`, `anthropic-version: 2023-06-01`, `content-type`. Raw cURL rather than the PHP SDK
  deliberately: shared hosting has no composer, and this keeps the deploy to one file with no vendor tree.
- **Fields returned:** character, series, issue, year, `year_source`, publisher, variant, confidence,
  note. `year_source` distinguishes a date **printed on the cover** from one Claude **knows** for a
  recognised issue — the UI says which, because the second is worth verifying. An implausible year is
  dropped server-side (`clean_year`).
- **Cost:** a 1400px cover is ~1,700 visual tokens (`⌈w/28⌉ × ⌈h/28⌉`), so roughly a penny a book on
  Opus 5, a quarter of a cent on Haiku 4.5 via `AI_MODEL`. Per-call cost is capped whatever gets sent,
  because the API downscales to the model's visual-token limit. Set a spend limit in the Console.
- **Failure handling:** the API's own error text is shown verbatim (a bad key or an exhausted credit
  balance explains itself best); 4xx is reported as permanent, 5xx and network trouble as retryable; a
  refusal, an unparseable reply, and a missing key each have their own message. Every failure leaves you
  with a working form and a "type the details in as usual" line.
- **Not built yet:** a comics-database lookup (ComicVine or similar) to confirm the year, publisher and
  character list from series + issue rather than from the model's recollection, and barcode scanning for
  anything printed after the mid-80s. Both slot in alongside `identify.php` without touching the form.
- Editing `config.php` on the server can take a few seconds to take effect — opcache is on.

### Features
Photograph a cover in-page (with flip-camera and a file-picker fallback) · character / book name /
issue number / condition, plus optional publisher, year, variant, value, paid, date acquired, tags,
notes · **Save & add another** carries the character/book/publisher over and pre-fills the next issue
number, for cataloguing a stack fast · typing `Amazing Spider-Man #300` into the book-name field
splits the issue number out by itself · autocomplete from characters/books/publishers already entered
· three views: Library grid, grouped by Character, grouped by Book (with `#300–#303 · Missing #302`
run analysis) · search across every field · filter by character · sort by added/book/character/issue/
value · totals for books, characters, book titles and collection value · edit and delete · CSV export.

## 6. Deploying to Hostinger — history and remaining step

Hosting is the same account as Car Tracker (Business plan, PHP 8.3, MySQL/PDO, account `u526894368`).
That account already hosts two unrelated sites — **Turnkey General Contractor**
(`darkgray-seahorse-474215.hostingersite.com`) and **Car Tracker**
(`mediumvioletred-alligator-245269.hostingersite.com`). **Give Comic Tracker its own third website**
so nothing overlaps.

1. ~~**Create the website.**~~ **Done** — `darkslategray-mosquito-683437.hostingersite.com`.
   A custom subdomain such as `comictracker.ironmanelabs.com` was not used because `ironmanelabs.com`
   has no DNS zone at Hostinger (`DNS_getDNSRecordsV1` returns empty — its DNS lives at an external
   provider), so the record could not be created here and the name would not have resolved. To move to
   that name later: add the website in hPanel, then point a DNS record at the hosting IP wherever
   `ironmanelabs.com`'s DNS is managed.
2. ~~**Create the database.**~~ **Done** — `u526894368_comictracker` with its own user, assigned to
   the new website. Reusing the Car Tracker database was explicitly ruled out by the owner: nothing is
   shared between the two apps.
3. **Upload the files** (hPanel → **File Manager**, or FTP with credentials from
   **Hosting → Advanced → FTP Accounts**) into the new site's `public_html`:
   - `comics/index.html` → `public_html/index.html`
   - `comics/api/` (the whole folder) → `public_html/api/`
   - `comics/uploads/` (the whole folder, including `covers/` and its `.htaccess`) → `public_html/uploads/`
4. **`api/config.php`** is already filled in inside the deployment zip that was handed to the owner
   (live DB credentials + the app login). It stays out of git. To change the app password later,
   regenerate the hash with
   `php -r 'echo password_hash("new-password", PASSWORD_BCRYPT), PHP_EOL;'`
   and replace the `APP_PASSWORD_HASH` line on the server.
5. **Check the covers folder is writable.** In File Manager, right-click `public_html/uploads/covers`
   → Permissions → `755` (Hostinger's default is usually fine). If it is wrong, the app will tell you
   in plain words the first time you save a book with a photo, and keep the book so nothing is lost.
6. **Open the site over `https://`** and log in. Add a book, take a photo, confirm the cover appears.
7. If the Hostinger placeholder page shows instead of the app, delete `default.php` from `public_html` —
   it ships with every new site and `index.html` should take precedence, but deleting it settles the
   question. (The API exposes no file-delete endpoint, so this one is a File Manager click.)

## 7. Notes for the next session

- The owner is **non-technical** — explain steps plainly, give exact clicks, confirm before anything
  irreversible.
- Keep `config.php` out of git, always. Same for anything under `uploads/covers/` (already ignored).
- The API surface is small and stable: `login`, `logout`, `list`, `save`, `delete`. Adding a field
  means one column in `db.php`'s `CREATE TABLE`, one line in `save.php`, one in `list.php`, and the
  form/detail markup in `index.html`. Existing installs need the column added by hand (the bootstrap
  only creates the table when missing).
- Ideas deliberately left out for now: wishlist / "want" list, per-book multiple photos, price-guide
  integration, per-person logins. (Cover reading now exists — see §5.)
