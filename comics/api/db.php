<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // One row per book. client_id is generated in the browser so a retried
        // save can never create a duplicate (the upsert keys off it).
        $pdo->exec('CREATE TABLE IF NOT EXISTS comics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            client_id VARCHAR(48) NOT NULL,
            character_name VARCHAR(160) NOT NULL DEFAULT "",
            series VARCHAR(200) NOT NULL DEFAULT "",
            issue VARCHAR(40) NOT NULL DEFAULT "",
            issue_sort DECIMAL(12,3) NULL,
            variant VARCHAR(160) NOT NULL DEFAULT "",
            publisher VARCHAR(120) NOT NULL DEFAULT "",
            year SMALLINT NULL,
            grade VARCHAR(40) NOT NULL DEFAULT "",
            value DECIMAL(12,2) NULL,
            paid DECIMAL(12,2) NULL,
            acquired DATE NULL,
            tags VARCHAR(255) NOT NULL DEFAULT "",
            notes TEXT NULL,
            key_info TEXT NULL,
            favorite TINYINT(1) NOT NULL DEFAULT 0,
            grail TINYINT(1) NOT NULL DEFAULT 0,
            value_checked DATETIME NULL,
            cover_file VARCHAR(160) NOT NULL DEFAULT "",
            thumb_file VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_client_id (client_id),
            KEY idx_character (character_name),
            KEY idx_series (series)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // One row per person. The owner row is seeded from config.php the first
        // time this runs, so the existing login keeps working unchanged.
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(64) NOT NULL,
            handle VARCHAR(64) NOT NULL,
            display_name VARCHAR(120) NOT NULL DEFAULT "",
            password_hash VARCHAR(255) NOT NULL,
            is_owner TINYINT(1) NOT NULL DEFAULT 0,
            public_shelf TINYINT(1) NOT NULL DEFAULT 0,
            ai_reads_used INT NOT NULL DEFAULT 0,
            ai_reads_month CHAR(7) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            last_login DATETIME NULL,
            UNIQUE KEY uniq_username (username),
            UNIQUE KEY uniq_handle (handle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // An invite is a one-use code. Kept after use as a record of who joined.
        $pdo->exec('CREATE TABLE IF NOT EXISTS invites (
            code VARCHAR(32) PRIMARY KEY,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            note VARCHAR(120) NOT NULL DEFAULT "",
            used_by INT NULL,
            used_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Columns added after the first release. Safe to run on every request:
        // each one is only added when missing, and a failure here must never
        // take the app down.
        $added = [
            'key_info' => 'ADD COLUMN key_info TEXT NULL',
            'favorite' => 'ADD COLUMN favorite TINYINT(1) NOT NULL DEFAULT 0',
            'grail'    => 'ADD COLUMN grail TINYINT(1) NOT NULL DEFAULT 0',
            'value_checked' => 'ADD COLUMN value_checked DATETIME NULL',
            'user_id' => 'ADD COLUMN user_id INT NOT NULL DEFAULT 0, ADD KEY idx_user (user_id)',
        ];
        foreach ($added as $column => $ddl) {
            try {
                $has = $pdo->prepare('SHOW COLUMNS FROM comics LIKE ?');
                $has->execute([$column]);
                if (!$has->fetch()) {
                    $pdo->exec('ALTER TABLE comics ' . $ddl);
                }
            } catch (PDOException $e) {
                // Leave it alone — the app still works without the new column.
            }
        }

        // Same idempotent trick for the users table.
        $userCols = [
            'disabled' => 'ADD COLUMN disabled TINYINT(1) NOT NULL DEFAULT 0',
            'people_seen_at' => 'ADD COLUMN people_seen_at DATETIME NULL',
        ];
        foreach ($userCols as $column => $ddl) {
            try {
                $has = $pdo->prepare('SHOW COLUMNS FROM users LIKE ?');
                $has->execute([$column]);
                if (!$has->fetch()) {
                    $pdo->exec('ALTER TABLE users ' . $ddl);
                }
            } catch (PDOException $e) {
            }
        }

        bootstrap_owner($pdo);
    }
    return $pdo;
}

/**
 * Turns a single-login install into a multi-user one, once, in place:
 *   - copies config.php's username and password hash into a users row, so the
 *     existing login keeps working with the same password;
 *   - hands every existing book to that owner;
 *   - re-keys the client_id uniqueness per user, since two people can now
 *     generate the same id.
 * Every step is guarded, so running it on an already-migrated database does
 * nothing.
 */
function bootstrap_owner(PDO $pdo) {
    try {
        $ownerId = (int) $pdo->query('SELECT id FROM users WHERE is_owner = 1 ORDER BY id LIMIT 1')->fetchColumn();

        if (!$ownerId && defined('APP_USERNAME') && defined('APP_PASSWORD_HASH') && APP_USERNAME !== '') {
            $ins = $pdo->prepare('INSERT INTO users
                (username, handle, display_name, password_hash, is_owner, public_shelf, created_at)
                VALUES (:u, :h, :d, :p, 1, 1, NOW())');
            $ins->execute([
                'u' => APP_USERNAME,
                'h' => handle_from(APP_USERNAME),
                'd' => defined('OWNER_DISPLAY_NAME') ? OWNER_DISPLAY_NAME : APP_USERNAME,
                'p' => APP_PASSWORD_HASH,
            ]);
            $ownerId = (int) $pdo->lastInsertId();
        }

        // Books that predate accounts belong to the owner.
        if ($ownerId) {
            $pdo->prepare('UPDATE comics SET user_id = :id WHERE user_id = 0')->execute(['id' => $ownerId]);
        }

        // client_id is generated in the browser, so it is only unique per person.
        $keys = $pdo->query("SHOW INDEX FROM comics WHERE Key_name = 'uniq_client_id'")->fetchAll();
        if ($keys) {
            $pdo->exec('ALTER TABLE comics DROP INDEX uniq_client_id, ADD UNIQUE KEY uniq_user_client (user_id, client_id)');
        }
    } catch (PDOException $e) {
        // A half-migrated database still serves the owner; do not take the app down.
    }
}

/** A URL-safe public handle: lowercase, digits and dashes only. */
function handle_from($name) {
    $h = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $name));
    $h = trim($h, '-');
    if (strlen($h) < 3) $h = 'shelf-' . substr(sha1($name . microtime()), 0, 6);
    return substr($h, 0, 60);
}

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function start_session() {
    $lifetime = 60 * 60 * 24 * 30; // 30 days — household app, avoid re-login on every visit
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    // Own cookie name so this app's login never shares a session with Car Tracker,
    // even if the two ever end up on the same domain.
    session_name('comictracker_sid');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/**
 * Every endpoint that touches a collection calls this and uses the row it
 * returns. It is the single choke point: no query anywhere should mention a
 * user id that did not come from here.
 */
function require_login() {
    start_session();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['uid'])) {
        json_response(['error' => 'Not logged in'], 401);
    }
    $user = find_user_by_id((int) $_SESSION['uid']);
    if (!$user) {
        // The account was deleted underneath the session.
        session_destroy();
        json_response(['error' => 'Not logged in'], 401);
    }
    if (!empty($user['disabled'])) {
        // Switched off while signed in: the session stops working immediately.
        session_destroy();
        json_response(['error' => 'This account has been switched off.'], 403);
    }
    return $user;
}

function find_user_by_id($id) {
    $q = db()->prepare('SELECT * FROM users WHERE id = :id');
    $q->execute(['id' => (int) $id]);
    return $q->fetch() ?: null;
}

function find_user_by_username($username) {
    $q = db()->prepare('SELECT * FROM users WHERE username = :u');
    $q->execute(['u' => (string) $username]);
    return $q->fetch() ?: null;
}

function find_user_by_handle($handle) {
    $q = db()->prepare('SELECT * FROM users WHERE handle = :h');
    $q->execute(['h' => (string) $handle]);
    return $q->fetch() ?: null;
}

/** What the browser is allowed to know about the signed-in account. */
function user_public(array $u) {
    return [
        'username' => $u['username'],
        'handle' => $u['handle'],
        'display_name' => $u['display_name'] !== '' ? $u['display_name'] : $u['username'],
        'is_owner' => (int) $u['is_owner'] === 1,
        'public_shelf' => (int) $u['public_shelf'] === 1,
        'reads_used' => reads_used_this_month($u),
        'reads_limit' => (int) $u['is_owner'] === 1 ? 0 : ai_monthly_reads(),
        'new_people' => unseen_people($u),
    ];
}

/** Refuses anyone but the owner. Every admin action starts with this. */
function require_owner(array $me) {
    if ((int) $me['is_owner'] !== 1) {
        json_response(['error' => 'Only the owner can do that.'], 403);
    }
}

/** Accounts created since the owner last looked at the people list. */
function unseen_people(array $me) {
    if ((int) $me['is_owner'] !== 1) return 0;
    $since = $me['people_seen_at'];
    $sql = 'SELECT COUNT(*) FROM users WHERE is_owner = 0'
        . ($since ? ' AND created_at > :since' : '');
    $q = db()->prepare($sql);
    $q->execute($since ? ['since' => $since] : []);
    return (int) $q->fetchColumn();
}

// ---------- the shared cover-read allowance ----------

/** Reads a non-owner account may spend per calendar month. 0 means unlimited. */
function ai_monthly_reads() {
    $n = defined('AI_MONTHLY_READS') ? (int) AI_MONTHLY_READS : 50;
    return $n > 0 ? $n : 0;
}

function reads_used_this_month(array $u) {
    return $u['ai_reads_month'] === gmdate('Y-m') ? (int) $u['ai_reads_used'] : 0;
}

/** True when this account still has reads left this month. */
function reads_available(array $u) {
    if ((int) $u['is_owner'] === 1) return true;
    $limit = ai_monthly_reads();
    return $limit === 0 || reads_used_this_month($u) < $limit;
}

/** Counts one read against the month, rolling the counter over on the 1st. */
function count_read(array $u) {
    $month = gmdate('Y-m');
    $sql = $u['ai_reads_month'] === $month
        ? 'UPDATE users SET ai_reads_used = ai_reads_used + 1 WHERE id = :id'
        : 'UPDATE users SET ai_reads_used = 1, ai_reads_month = :m WHERE id = :id';
    $q = db()->prepare($sql);
    $q->execute($u['ai_reads_month'] === $month ? ['id' => $u['id']] : ['id' => $u['id'], 'm' => $month]);
}

function read_json_body($maxBytes = 24 * 1024 * 1024) {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > $maxBytes) {
        json_response(['error' => 'Upload too large — try a smaller photo.'], 413);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(['error' => 'Invalid JSON body'], 400);
    }
    return $decoded;
}

// ---------- cover images ----------

function covers_dir() {
    $dir = dirname(__DIR__) . '/uploads/covers';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Writes a browser-produced data URI to uploads/covers and returns the filename.
 * Returns '' if there is nothing to write; throws RuntimeException on a bad image
 * or an unwritable folder.
 */
/**
 * Validates a browser-produced data URI and returns [$bytes, $mimeType].
 * Throws RuntimeException with a plain-language message on anything unusable.
 */
function decode_image_data_uri($dataUri) {
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', (string) $dataUri, $m)) {
        throw new RuntimeException('Unsupported image format.');
    }
    $ext = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
    $bytes = base64_decode(substr($dataUri, strlen($m[0])), true);
    if ($bytes === false || strlen($bytes) < 64) {
        throw new RuntimeException('Could not read that image.');
    }
    if (strlen($bytes) > 12 * 1024 * 1024) {
        throw new RuntimeException('That image is too large.');
    }
    if (@getimagesizefromstring($bytes) === false) {
        throw new RuntimeException('That file is not an image.');
    }
    return [$bytes, 'image/' . $ext];
}

function store_cover_image($dataUri, $prefix) {
    if (!is_string($dataUri) || $dataUri === '') {
        return '';
    }
    list($bytes, $mime) = decode_image_data_uri($dataUri);
    $ext = substr($mime, strlen('image/'));
    $dir = covers_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('The uploads/covers folder is missing or not writable on the server.');
    }
    $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (file_put_contents($dir . '/' . $name, $bytes) === false) {
        throw new RuntimeException('Could not save the image on the server.');
    }
    return $name;
}

function delete_cover_image($name) {
    $name = (string) $name;
    // Only ever touch plain filenames inside uploads/covers.
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name) || strpos($name, '..') !== false) {
        return;
    }
    $path = covers_dir() . '/' . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}

// ---------- cover reading (Claude API) ----------

/** The API key, or '' when the owner has not added one to config.php yet. */
function ai_key() {
    return defined('ANTHROPIC_API_KEY') ? trim((string) ANTHROPIC_API_KEY) : '';
}

function ai_model() {
    $model = defined('AI_MODEL') ? trim((string) AI_MODEL) : '';
    return $model !== '' ? $model : 'claude-opus-5';
}

function ai_endpoint() {
    $url = defined('AI_API_URL') ? trim((string) AI_API_URL) : '';
    return $url !== '' ? $url : 'https://api.anthropic.com/v1/messages';
}

function ai_enabled() {
    return ai_key() !== '' && function_exists('curl_init');
}

// ---------- field helpers ----------

/** A checkbox from the browser: "1" on, "" or absent off. Stored as 0/1. */
function clean_flag($value) {
    if (is_bool($value)) return $value ? 1 : 0;
    $value = trim((string) (is_scalar($value) ? $value : ''));
    return ($value === '' || $value === '0' || strtolower($value) === 'false') ? 0 : 1;
}

function clean_text($value, $max) {
    $value = is_scalar($value) ? (string) $value : '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    $value = trim($value);
    return mb_substr($value, 0, $max);
}

function clean_money($value) {
    if ($value === null || $value === '' || !is_scalar($value)) return null;
    $value = str_replace([',', '$'], '', (string) $value);
    if (!is_numeric($value)) return null;
    return round((float) $value, 2);
}

function clean_year($value) {
    if ($value === null || $value === '' || !is_scalar($value)) return null;
    $n = (int) $value;
    return ($n >= 1800 && $n <= 2200) ? $n : null;
}

function clean_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    return $value;
}

/** "300", "#300", "Annual 4", "1.5", "12A" -> a sortable number (or null). */
function issue_sort_value($issue) {
    if (preg_match('/(\d+(?:\.\d+)?)/', (string) $issue, $m)) {
        return (float) $m[1];
    }
    return null;
}
