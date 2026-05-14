<?php
define('APP_NAME',    'ANOVEX Label System');
define('APP_VERSION', '2.0');
define('APP_ROOT',    __DIR__ . '/..');
define('SESSION_NAME','anovex_labels_sess');

session_name(SESSION_NAME);
session_start();

function auth(): array {
    if (empty($_SESSION['user'])) {
        header('Location: /login.php'); exit;
    }
    return $_SESSION['user'];
}

function isAdmin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

function json_out(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function json_error(string $msg, int $code = 400): void {
    json_out(['error' => $msg], $code);
}

function sanitize(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $rows = db()->query('SELECT `skey`, `value` FROM settings')->fetchAll();
        $cache = array_column($rows, 'value', 'skey');
    }
    return $cache[$key] ?? $default;
}
