<?php
/**
 * Joining with an invite code.
 *
 * An invite is one-use and is claimed inside a transaction, so two people
 * racing on the same code cannot both get in. Nothing here trusts the browser:
 * the username, handle and password are all re-validated server-side, and the
 * new account starts with a private shelf and no books.
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$in = read_json_body(64 * 1024);
$code = strtoupper(trim((string) ($in['invite'] ?? '')));
$username = trim((string) ($in['username'] ?? ''));
$password = (string) ($in['password'] ?? '');
$display = clean_text($in['display_name'] ?? '', 120);

// Rate limit by IP: guessing invite codes should be slow and boring.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/comictracker_signup_attempts.json';
$fh = fopen($rateFile, 'c+');
flock($fh, LOCK_EX);
$raw = stream_get_contents($fh);
$attempts = is_array($j = json_decode($raw ?: '[]', true)) ? $j : [];
$now = time();
$entry = $attempts[$ip] ?? ['count' => 0, 'lockUntil' => 0];
$locked = $entry['lockUntil'] > $now;
if (!$locked) {
    $entry['count'] += 1;
    if ($entry['count'] >= 10) { $entry['lockUntil'] = $now + 30 * 60; $entry['count'] = 0; }
    $attempts[$ip] = $entry;
}
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($attempts));
flock($fh, LOCK_UN);
fclose($fh);
if ($locked) {
    json_response(['error' => 'Too many attempts. Try again later.'], 429);
}

if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{2,31}$/', $username)) {
    json_response(['error' => 'Pick a username of 3–32 letters, numbers, dots, dashes or underscores.'], 400);
}
if (strlen($password) < 10) {
    json_response(['error' => 'Use a password of at least 10 characters.'], 400);
}
if (strlen($password) > 200) {
    json_response(['error' => 'That password is too long.'], 400);
}
if (!preg_match('/^[A-Z0-9-]{6,32}$/', $code)) {
    json_response(['error' => 'That invite code does not look right.'], 400);
}

$pdo = db();

if (find_user_by_username($username)) {
    json_response(['error' => 'That username is taken.'], 409);
}

// A handle is derived from the username, with a suffix if it collides.
$handle = handle_from($username);
$base = $handle;
for ($i = 2; $i < 40 && find_user_by_handle($handle); $i++) {
    $handle = substr($base, 0, 52) . '-' . $i;
}

try {
    $pdo->beginTransaction();

    // Claim the invite first: this UPDATE is the lock. If it changes no rows,
    // the code was already used or never existed.
    $claim = $pdo->prepare('UPDATE invites SET used_at = NOW() WHERE code = :c AND used_by IS NULL AND used_at IS NULL');
    $claim->execute(['c' => $code]);
    if ($claim->rowCount() !== 1) {
        $pdo->rollBack();
        json_response(['error' => 'That invite code has already been used, or does not exist.'], 403);
    }

    $ins = $pdo->prepare('INSERT INTO users
        (username, handle, display_name, password_hash, is_owner, public_shelf, created_at)
        VALUES (:u, :h, :d, :p, 0, 0, NOW())');
    $ins->execute([
        'u' => $username,
        'h' => $handle,
        'd' => $display !== '' ? $display : $username,
        'p' => password_hash($password, PASSWORD_BCRYPT),
    ]);
    $id = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE invites SET used_by = :id WHERE code = :c')->execute(['id' => $id, 'c' => $code]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['error' => 'Could not create the account. Try again.'], 500);
}

$user = find_user_by_id($id);
start_session();
$_SESSION['logged_in'] = true;
$_SESSION['uid'] = $id;

json_response(['ok' => true, 'user' => user_public($user)]);
