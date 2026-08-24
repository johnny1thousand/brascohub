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

$user = ($username !== '' && $password !== '') ? find_user_by_username($username) : null;
$ok = $user && password_verify($password, $user['password_hash']);

if ($ok) {
    unset($attempts[$ip]);
    $_SESSION['logged_in'] = true;
    $_SESSION['uid'] = (int) $user['id'];
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
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

json_response(['ok' => true, 'user' => user_public($user)]);
