# LongBox — Project Handoff

A personal **comic book collection tracker**. Photograph a cover with your phone, type in the
**character**, the **book name** and the **issue number**, and the app files it away — grouped by
character and by book, with cover thumbnails, search, condition/value tracking, and missing-issue
detection for runs you are collecting.

The app is called **LongBox** (it was "Comic Tracker" until the branding landed). It is a
**separate website** from Car Tracker: its own folder here, its own Hostinger site, its own
login. Nothing in this folder touches the Car Tracker app at the repo root.

---

## 1. Current status

- **The app is LIVE** at **`comictracker.ironmanelabs.com`** (2026-08-22) — files deployed, first book
  saved with its cover, cover reading wired up. Created via the Hostinger API:
  - Website: created as `darkslategray-mosquito-683437.hostingersite.com`, since renamed by the owner to
    **`comictracker.ironmanelabs.com`** (sits alongside `cartracker.ironmanelabs.com`; DNS for that
    domain is managed outside Hostinger, so the owner pointed the record themselves). Note the API's
    `domain` key follows the rename — calls using the old free subdomain return 404.
    Its own site on order `1009816343`,
    root `/home/u526894368/domains/darkslategray-mosquito-683437.hostingersite.com/public_html`, PHP 8.3.30,
    PDO/mysqlnd and GD enabled, `post_max_size` 2048M.
  - Database: **`u526894368_comictracker`**, user `u526894368_comictracker`, assigned to that website,
    host `srv450.hstgr.io` (config uses `localhost`, the standard for same-account PHP→MySQL).
    Password was given to the owner directly — never stored in this repo.
  - Nothing is shared with Car Tracker: separate site, separate database and user, separate login,
    separate session cookie name.
  - **Deploy gotcha, if you ever hand a zip to the owner again:** File Manager extracts into whatever
    folder is currently open. The first attempt landed the whole update inside `public_html/uploads/`,
    which moved `api/` out from under the app and briefly took the site down. Say "make sure you are in
    `public_html`, not inside `uploads`, before extracting", and check the listing afterwards — file
    sizes are enough to tell old from new without reading anything.
  - **How the files got there:** copied by the owner via hPanel File Manager. Claude could not do this step because
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
| `tools/schema-lint.php` | Checks `identify.php`'s schema against the keyword subset structured outputs accepts. Run after any schema change. |
| `tools/test-geometry.js`, `tools/geometry.js`, `tools/sync-geometry.sh` | Unit tests for the straightening maths, run against functions extracted from `index.html`. Dev only — nothing to upload. |
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
  paid, acquired, tags, notes, key_info, cover_file, thumb_file, created_at, updated_at`.
  Columns added after the first release are applied by a small idempotent migration at the top of
  `db()` (`SHOW COLUMNS` then `ALTER TABLE`, wrapped so a failure can never take the app down) —
  that is how `key_info` reached the live database, which already had rows in it.
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
- **Straightening (perspective correction):** the same call asks for `cover_quad` — the book's four
  corners, clockwise from top-left — and the browser maps that quadrilateral onto a rectangle, which
  crops to the edges and takes out tilt and keystone in one step. Pure JS in `index.html`: an 8×8 solve
  for the homography (Gaussian elimination, partial pivoting) then an inverse map of every output pixel
  with bilinear sampling; ~135ms for a 1200px cover. Corners are pushed out 0.6% so a tight quad does
  not shave the border, and a quad measuring outside 0.35–1.4 is refused, falling back to the plain
  `cover_box` crop (also refused server-side if its span is under 15% of the frame).
  - **Output shape — do not derive it from the quad's edge lengths.** Perspective foreshortens them: a
    real 0.66 cover measured 0.88 in testing. Comics are a known shape, so the output uses
    `COMIC_ASPECT` (0.66 — 6.625×10.25in, standard since the 1970s) whenever the measurement is
    consistent with a portrait book (0.45–1.15), falling back to the measurement only for something
    clearly not comic-shaped. Recovering the true aspect from one view is possible (Zhang & He, from
    the homography plus an assumed principal point) but unnecessary here and less robust.
  - **Verified numerically, not by eye.** `tools/test-geometry.js` checks the maths against a known
    homography (recovered to 5e-16) and every corner ordering and winding; a browser test warps a known
    flat cover by a known perspective, straightens it back and compares per pixel — mean error 5.2/255
    per channel, aspect exact. `tools/sync-geometry.sh` re-extracts the pure functions from
    `index.html` so those tests always run the shipping code.
- **Cropping to the book (the fallback):** `cover_box` is an upright rectangle. Claude's coordinates are
  in the space of the image *after* the API's own resize, so `identify.php` reproduces that resize
  (`resized_size()`, the reference implementation from the coordinates doc — verified against both of
  its worked examples, 1075×1520→924×1307 and 1920×1080→1456×819) and converts to fractions of the
  image. **Tier matters:** high-resolution models (Claude 4.7 and later — Opus 5, Sonnet 5, Fable 5)
  allow 2576px/4784 tokens, everything else 1568/1568; `model_image_limits()` keys off the model named
  in the *response*. Get the tier wrong and every crop shifts silently.
- **What is sent vs what is stored:** identification always sends the **uncropped** photo at
  1400px/q0.85 — sharper than what is stored, because issue numbers and date boxes are small, and
  uncropped so a returned box or quad always refers to the same image (re-reading can never crop a
  crop; there is a regression test). What is *stored* is much smaller: a 1000px cover and 360px
  thumbnail at quality 0.72/0.70, **WebP where the browser supports it**, JPEG otherwise. A detailed
  1800×2400 photo that used to store a 417KB cover now stores ~115KB — about 70% less. The
  full-resolution photo is never uploaded. A 1600px `draftMaster` canvas lives in memory only while the
  form is open, so crops and straightening are cut from a sharp source rather than from the stored copy.
- **Key issue info:** `key_info` (TEXT, added by the migration in `db()`) holds why an issue matters — a
  first appearance, a death, a famous arc. Claude fills it during identification; it is an ordinary
  editable field, searchable, in the CSV, and shown in the detail view as a "Why it matters" callout.
  It is the model's recollection, not a citation.
- **Not built yet:** a comics-database lookup (ComicVine or similar) to confirm the year, publisher and
  character list from series + issue rather than from the model's recollection, and barcode scanning for
  anything printed after the mid-80s. Both slot in alongside `identify.php` without touching the form.
- **Schema constraints:** structured outputs accepts only a subset of JSON Schema — notably `minItems`
  is limited to 0 or 1 and `maxItems` is rejected outright, which took down a shipped version of
  `identify.php` (the array length for `cover_box` is stated in its `description` instead, and a reply
  with the wrong count is ignored in PHP). A stand-in API cannot catch this class of bug because it
  never validates the schema, so run `php tools/schema-lint.php api/identify.php` after touching the
  schema — it checks every keyword against the documented subset.
- Editing `config.php` on the server can take a few seconds to take effect — opcache is on.

### Views

- **Library** is where the app always opens, whatever view you were last on — the stored view preference
  is deliberately overridden to `library` in `load()`.
- **Characters** is a collapsed list: one red row per character with its counts, and the covers only
  appear for rows you open. Open state lives in `expandedGroups` for the visit and is not persisted.
  Searching or filtering force-opens every row, or the matches would be hidden inside collapsed rows and
  the search would look broken. The four-card dashboard is replaced here by a single Characters count —
  shelf totals are noise when browsing by character.
- **Books** works the same way: one row per title, collapsed, with its own single count. The run
  analysis (`#300–#301`, `Complete run`, `Missing #302`) stays on the closed row, so a title's state is
  readable without opening it.
- The page heading is just **My Collection** — no eyebrow label above it, no trailing red full stop.
- Open rows are keyed `view + ":" + name`, so a character and a book title that share a name (a "Batman"
  character and a "Batman" title) do not open each other when you switch views — there is a test for
  exactly that.

### Search and the dashboard blocks

- The search sits **above the heading**, directly under the top bar, as a red-outlined pill with a clear
  button — it is the control used on every visit, so it comes before anything else. The character filter
  and the sort order stay below the blocks: they are refinements, not the main action.
- The four dashboard blocks are **navigation**: Books → Library, Characters → Characters,
  Book titles → Books, Value → Library sorted by value. `stat()` takes a `go` argument
  (`{view, sort}`); omit it for a plain block. On the grouped views the single count block is
  deliberately not a link — it describes the view you are already on. Each clickable block carries a
  faint ↗ because there is no hover state on a phone and they would otherwise look inert.

### Favorites and grails

Two flags per book, `favorite` and `grail`, each a `TINYINT(1)` on the `comics` table (added by the
same idempotent migration loop as `key_info`, so an existing install picks them up on the next
request). In the browser they ride along in `FIELDS` as `"1"` or `""`, which means they save, sync,
merge and survive the offline outbox through exactly the same plumbing as every other field — there is
no separate endpoint. `list.php` deliberately emits `''` rather than `'0'` for off, because the client
stringifies every field and `"0"` would be truthy.

Three ways to set them:

* **On the card** — a chalice and a heart at the top-left of the cover (`.bflags`). The card had to stop
  being a `<button>` for this (no nested buttons): it is now a `div.bcard` wrapping `button.bcard-main`
  plus the two toggles. The click handler checks `[data-flag]` **before** `[data-book]`, so tapping a
  heart never opens the book. The "not synced" badge moved to the bottom of the cover to make room.
* **In the detail modal** — the same two buttons, plus a text tag.
* **In the edit form** — two `.mark` buttons under Condition, read back in `readForm()`.

**The shelf** (`renderLibrary`) groups Library into **Grails, Favorites, Everything else**, in that
order. A book that is both is a grail and appears once, at the top. With nothing flagged the plain flat
grid is kept rather than showing a lone "Everything else".

**The dashboard** has two more blocks, Grails and Favorites, which set `settings.flag` and filter the
shelf to just those. Every other block clears the filter, so "Books" always means all of them. While a
filter is on, a dark chip appears in the toolbar (`#flagChip`) — tapping it, or "clear search &
filters", puts everything back. Flag buttons are 31px on desktop and 36px on phones.

`node tools/flags-test.js` covers all of it: both toggles, that tapping one does not open the book, the
section order, the counts, the two shortcuts, the chip, and a flag surviving a round trip to the server
and back.

### Cover read resolution and cost

Each read sends the uncropped photo at `IDENTIFY_MAX` (1400px long edge), which the API bills as
**1,700 image tokens** for a portrait cover, plus ~500 tokens of prompt and ~200 back. On
`claude-opus-5` that is roughly **$0.047 a book** — about $7 to catalogue 150 comics.

Cheaper options, if that ever matters:

* **A smaller model.** One line in `config.php`: `claude-sonnet-5` is ~5x cheaper, `claude-haiku-4-5`
  ~15x. `model_image_limits()` already handles all three — Haiku is standard tier, so a 1400px photo is
  resized to 896x1343 / 1,536 tokens, and the coordinate conversion follows it correctly. The reason to
  stay on Opus is `key_info`: first appearances are recall, not reading, and a wrong one looks
  authoritative.
* **A smaller photo.** 1120px would bill 1,080 image tokens — 36% less. This was built as a Settings
  toggle and then removed: ~$2 across a 150-book collection did not justify a possibly worse read, and
  the accuracy comparison can only be run with a real key on the live site. `IDENTIFY_MAX` is one
  constant if it ever needs revisiting.

The crop and quad conversion is resolution- and model-agnostic: `identify.php` recomputes
`resized_size()` from the dimensions of the image it actually received, so a box at 10-90% of what the
model saw comes back as 0.10/0.80 fractions at every size and on every model. Verified for
1400/1120 x opus-5/sonnet-5/haiku-4.5.

### Branding

The name is **LongBox**; the owner supplied the artwork (a horizontal `LONGBOX / MY COMICS` wordmark
and a matching square app icon, both with real alpha). Both are embedded as base64 WebP in
`index.html` at 700px and 180px wide respectively (700px gives the 52px top bar headroom on a 3x
phone screen), like the fonts — the app still makes no third-party requests.

* **Top bar** — the horizontal wordmark (`.brand-mark`), **52px** tall, 42px under 860px, 34px under
  360px (the owner asked for it much bigger than the 30/26/22px it started at; no crop was needed —
  the lockup is 2.9:1, so even at 52px it is only ~151px wide and the controls still fit at 320px).
  The square icon was tried here first and turned to mush at 28px, because its `MY COMICS` band is
  only ~8% of its height; the wordmark stays legible small.
* **Login card** — the same wordmark at up to 250px wide, inside the `h1` (`.login-wordmark`), with
  the `Ironmane Labs` eyebrow kept underneath.
* **Icon** — the square badge, 180px, serves as both `rel="icon"` and `apple-touch-icon`, so a
  home-screen shortcut gets the app icon.
* The old Ironmane Labs griffin PNG is gone from this app, along with the `Comic Tracker.` text
  wordmark.

Two internal names deliberately still say "comictracker": the `localStorage` key
`comicTracker.v1` (renaming it would orphan any queued offline edits) and the session cookie
`comictracker_sid` in `api/db.php` (renaming it would log the owner out). They are invisible to the
user; leave them unless there is a reason to migrate.

### Fitting on a phone

The top bar has to hold the wordmark, the sync status, **+ Add** and the settings gear. It used to lay
those out at natural width, so the wordmark's fixed ~142px pushed the buttons past the edge — 8-11px
over on a 390-393px iPhone, 26px at 375, 81px with horizontal scrolling at 320. The fix is structural
rather than a tuned breakpoint: on mobile the top bar is `minmax(0, 1fr) auto`, so the controls always
take their natural width and the wordmark gets the remainder (truncating as a last resort). Below 400px
the status pill drops to its coloured dot; below 360px the wordmark and gear shrink a step.

`node tools/fit-test.js` measures the overflow at 320/375/390/393/402/412/430 and fails if anything
crosses the edge or the page scrolls sideways. **Caveat:** that is Chromium with a phone viewport, not
Safari on a real handset — fine for catching layout overflow, not a substitute for looking at it on the
actual device. There is no iPhone 17 profile in the tooling; the 393-402 band covers where it lands.

### Features
Photograph a cover in-page (with flip-camera and a file-picker fallback) · character / book name /
issue number / condition / **favorite** / **grail**, plus optional publisher, year, variant, value, paid, date acquired, tags,
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
(`mediumvioletred-alligator-245269.hostingersite.com`). **Give LongBox its own third website**
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
6. ~~**Open the site and log in.**~~ **Done** — first book saved, cover written to `uploads/covers/`.
7. If the Hostinger placeholder page shows instead of the app, delete `default.php` from `public_html` —
   it ships with every new site and `index.html` should take precedence, but deleting it settles the
   question. (The API exposes no file-delete endpoint, so this one is a File Manager click.)

## 7. Notes for the next session

**A caution, from a real bug.** Commit `f8503c0` pasted a block of `renderStats()` into the
`settingsBtn` click handler by mistake. The handler threw `view is not defined` on every click, so the
gear button did nothing at all — no export, no sync-now, no log out, no auto-read toggle — and it
shipped that way through three more commits before a test that actually clicked the gear caught it.
None of the view/nav suites touched it. When editing this file, run the suite that exercises the thing
you did *not* change.

`node tools/settings-test.js` now covers it: the gear opens from all three views, and export, sync-now
and log-out each still do their job.


- The owner is **non-technical** — explain steps plainly, give exact clicks, confirm before anything
  irreversible.
- Keep `config.php` out of git, always. Same for anything under `uploads/covers/` (already ignored).
- The API surface is small and stable: `login`, `logout`, `list`, `save`, `delete`. Adding a field
  means one column in `db.php`'s `CREATE TABLE`, one line in `save.php`, one in `list.php`, and the
  form/detail markup in `index.html`. Existing installs need the column added by hand (the bootstrap
  only creates the table when missing).
- Ideas deliberately left out for now: wishlist / "want" list, per-book multiple photos, price-guide
  integration, per-person logins. (Cover reading now exists — see §5.)
