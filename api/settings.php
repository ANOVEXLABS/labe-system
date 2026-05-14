<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user       = auth();
$company_id = (int)($user['company_id'] ?? 1);
$action     = $_GET['action'] ?? '';

switch ($action) {

    // GET ?action=all
    case 'all':
        $stmt = db()->prepare('SELECT skey, value FROM company_settings WHERE company_id = ?');
        $stmt->execute([$company_id]);
        json_out(array_column($stmt->fetchAll(), 'value', 'skey'));
        break;

    // POST ?action=save
    case 'save':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stmt = db()->prepare(
            'INSERT INTO company_settings (company_id, skey, value) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE value=?'
        );
        foreach ($data as $k => $v) {
            $stmt->execute([$company_id, $k, $v, $v]);
        }
        json_out(['ok' => true]);
        break;

    // GET ?action=presets
    case 'presets':
        $stmt = db()->prepare(
            'SELECT * FROM size_presets WHERE active = 1 AND (company_id IS NULL OR company_id = ?) ORDER BY id'
        );
        $stmt->execute([$company_id]);
        json_out($stmt->fetchAll());
        break;

    // GET ?action=suppliers
    case 'suppliers':
        $all  = isset($_GET['all']) && isAdmin();
        $stmt = $all
            ? db()->prepare('SELECT * FROM suppliers WHERE company_id = ? ORDER BY id')
            : db()->prepare('SELECT * FROM suppliers WHERE company_id = ? AND active = 1 ORDER BY id');
        $stmt->execute([$company_id]);
        json_out($stmt->fetchAll());
        break;

    // POST ?action=supplier_create
    case 'supplier_create':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['name'])) json_error('Název je povinný.');
        if (empty($d['code'])) json_error('Kód je povinný.');
        $stmt = db()->prepare(
            'INSERT INTO suppliers (company_id, name, code, address, sku_prefix, active) VALUES (?,?,?,?,?,1)'
        );
        $stmt->execute([
            $company_id,
            trim($d['name']),
            trim($d['code']),
            trim($d['address'] ?? ''),
            trim($d['sku_prefix'] ?? ''),
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    // POST ?action=supplier_update&id=X
    case 'supplier_update':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_error('Chybí id.');
        $d    = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = db()->prepare(
            'UPDATE suppliers SET name=?, code=?, address=?, sku_prefix=? WHERE id=? AND company_id=?'
        );
        $stmt->execute([
            trim($d['name'] ?? ''),
            trim($d['code'] ?? ''),
            trim($d['address'] ?? ''),
            trim($d['sku_prefix'] ?? ''),
            $id,
            $company_id,
        ]);
        json_out(['ok' => true]);
        break;

    // POST ?action=supplier_toggle&id=X
    case 'supplier_toggle':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_error('Chybí id.');
        db()->prepare(
            'UPDATE suppliers SET active = 1 - active WHERE id = ? AND company_id = ?'
        )->execute([$id, $company_id]);
        json_out(['ok' => true]);
        break;

    // GET ?action=users
    case 'users':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $stmt = db()->prepare(
            'SELECT id, name, email, role, active, created_at, last_login FROM users WHERE company_id = ? ORDER BY id'
        );
        $stmt->execute([$company_id]);
        json_out($stmt->fetchAll());
        break;

    // POST ?action=create_user
    case 'create_user':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (strlen($data['password'] ?? '') < 8) json_error('Heslo musí mít alespoň 8 znaků.');
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare(
            'INSERT INTO users (company_id, name, email, password, role) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $company_id,
            $data['name']  ?? '',
            $data['email'] ?? '',
            $hash,
            $data['role']  ?? 'editor',
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
