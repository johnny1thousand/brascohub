<?php
require_once __DIR__ . '/db.php';
$me = require_login();

$in = read_json_body(64 * 1024);
$clientId = clean_text($in['client_id'] ?? '', 48);
if ($clientId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $clientId)) {
    json_response(['error' => 'Missing or invalid book id.'], 400);
}

// Scoped to the signed-in account: another account's book is simply not found.
$row = db()->prepare('SELECT cover_file, thumb_file FROM comics WHERE client_id = :cid AND user_id = :uid');
$row->execute(['cid' => $clientId, 'uid' => $me['id']]);
$row = $row->fetch();

db()->prepare('DELETE FROM comics WHERE client_id = :cid AND user_id = :uid')
    ->execute(['cid' => $clientId, 'uid' => $me['id']]);

if ($row) {
    delete_cover_image($row['cover_file']);
    delete_cover_image($row['thumb_file']);
}

json_response(['ok' => true]);
