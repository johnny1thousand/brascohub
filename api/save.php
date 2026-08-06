<?php
require_once __DIR__ . '/db.php';
require_login();

$raw = file_get_contents('php://input');
if (strlen($raw) > 50 * 1024 * 1024) {
    json_response(['error' => 'Data too large'], 413);
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    json_response(['error' => 'Invalid JSON body'], 400);
}

$stmt = db()->prepare('UPDATE app_data SET data = :data, updated_at = NOW() WHERE id = 1');
$stmt->execute(['data' => $raw]);

json_response(['ok' => true]);
