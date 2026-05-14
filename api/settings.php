<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user = auth();

$action = $_GET['action'] ?? '';

switch ($action) {

    // GET ?action=all
    case 'all':
        $rows = db()->query('SELECT skey, value FROM settings ORDER BY skey')->fetchAll();
        $out  = array_column($rows, 'value', 'skey');
        json_out($out);
        break;

    // POST ?action=save
    case 'save':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stmt = db()->prepare('INSERT INTO settings (skey, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?');
        foreach ($data as $k => $v) {
            $stmt->execute([$k, $v, $v]);
        }
        json_out(['ok' => true]);
        break;

    // GET ?action=presets
    case 'presets':
        $rows = db()->query('SELECT * FROM size_presets WHERE active = 1 ORDER BY id')->fetchAll();
        json_out($rows);
        break;

    // GET ?action=suppliers
    case 'suppliers':
        $rows = db()->query('SELECT * FROM suppliers WHERE active = 1 ORDER BY id')->fetchAll();
        json_out($rows);
        break;

    // GET ?action=users — admin only
    case 'users':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $rows = db()->query('SELECT id, name, email, role, active, created_at, last_login FROM users ORDER BY id')->fetchAll();
        json_out($rows);
        break;

    // POST ?action=create_user — admin only
    case 'create_user':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (strlen($data['password'] ?? '') < 8) json_error('Heslo musí mít alespoň 8 znaků.');
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
        $stmt->execute([$data['name'] ?? '', $data['email'] ?? '', $hash, $data['role'] ?? 'editor']);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
