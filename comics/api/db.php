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
            cover_file VARCHAR(160) NOT NULL DEFAULT "",
            thumb_file VARCHAR(160) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_client_id (client_id),
            KEY idx_character (character_name),
            KEY idx_series (series)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    return $pdo;
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
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function require_login() {
    start_session();
    if (empty($_SESSION['logged_in'])) {
        json_response(['error' => 'Not logged in'], 401);
    }
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
function store_cover_image($dataUri, $prefix) {
    if (!is_string($dataUri) || $dataUri === '') {
        return '';
    }
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUri, $m)) {
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
    $info = @getimagesizefromstring($bytes);
    if ($info === false) {
        throw new RuntimeException('That file is not an image.');
    }
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

// ---------- field helpers ----------

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
