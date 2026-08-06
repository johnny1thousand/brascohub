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
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_data (
            id INT PRIMARY KEY AUTO_INCREMENT,
            data LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $row = $pdo->query('SELECT id FROM app_data LIMIT 1')->fetch();
        if (!$row) {
            $pdo->prepare('INSERT INTO app_data (id, data, updated_at) VALUES (1, :data, NOW())')
                ->execute(['data' => '{}']);
        }
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
