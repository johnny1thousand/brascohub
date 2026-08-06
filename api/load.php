<?php
require_once __DIR__ . '/db.php';
require_login();

$row = db()->query('SELECT data, updated_at FROM app_data WHERE id = 1')->fetch();
json_response([
    'data' => $row ? json_decode($row['data'], true) : null,
    'updated_at' => $row ? $row['updated_at'] : null,
]);
