<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');

// Basic file-based rate limiting per IP.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/comictracker_login_attempts.json';
$fh = fopen($rateFile, 'c+');
flock($fh, LOCK_EX);
$raw = stream_get_contents($fh);
$attempts = $raw ? json_decode($raw, true) : [];
if (!is_array($attempts)) $attempts = [];

$now = time();
$entry = $attempts[$ip] ?? ['count' => 0, 'lockUntil' => 0];
if ($entry['lockUntil'] > $now) {
    flock($fh, LOCK_UN);
    fclose($fh);
    json_response(['error' => 'Too many attempts. Try again in a few minutes.'], 429);
}

start_session();

$ok = $username !== '' && $password !== ''
    && hash_equals(APP_USERNAME, $username)
    && password_verify($password, APP_PASSWORD_HASH);

if ($ok) {
    unset($attempts[$ip]);
    $_SESSION['logged_in'] = true;
} else {
    $entry['count'] += 1;
    if ($entry['count'] >= 8) {
        $entry['lockUntil'] = $now + 15 * 60;
        $entry['count'] = 0;
    }
    $attempts[$ip] = $entry;
    usleep(400000); // slow down brute-force guessing
}

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($attempts));
flock($fh, LOCK_UN);
fclose($fh);

if (!$ok) {
    json_response(['error' => 'Incorrect username or password.'], 401);
}

json_response(['ok' => true]);
