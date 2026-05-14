<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user       = auth();
$company_id = (int)($user['company_id'] ?? 1);
$method     = $_SERVER['REQUEST_METHOD'];
$action     = $_GET['action'] ?? '';
$id         = (int)($_GET['id'] ?? 0);

switch ($method . ':' . $action) {

    case 'GET:':
    case 'GET:list':
        $supplier_id = (int)($_GET['supplier_id'] ?? 0);
        $stmt = db()->prepare(
            'SELECT * FROM stacks WHERE company_id = ? AND supplier_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$company_id, $supplier_id]);
        json_out($stmt->fetchAll());
        break;

    case 'GET:get':
        $stmt = db()->prepare('SELECT * FROM stacks WHERE id = ? AND company_id = ? LIMIT 1');
        $stmt->execute([$id, $company_id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Stack nenalezen.', 404);
        json_out($row);
        break;

    case 'POST:create':
        $data        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $supplier_id = (int)($data['supplier_id'] ?? 0);
        if (!$supplier_id) json_error('Chybí supplier_id.');
        $stmt = db()->prepare(
            'INSERT INTO stacks (company_id, supplier_id, name, sub, series, bg, accent, logo_color, safe_margin, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $company_id,
            $supplier_id,
            $data['name']        ?? 'Nový stack',
            $data['sub']         ?? '',
            $data['series']      ?? 'select',
            $data['bg']          ?? '#0b1220',
            $data['accent']      ?? '#3d6af2',
            $data['logo_color']  ?? 'white',
            (float)($data['safe_margin'] ?? 2.0),
            (int)($data['sort_order']    ?? 0),
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'POST:update':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stmt = db()->prepare(
            'UPDATE stacks SET name=?, sub=?, series=?, bg=?, accent=?, logo_color=?, safe_margin=?, sort_order=?
             WHERE id=? AND company_id=?'
        );
        $stmt->execute([
            $data['name']        ?? '',
            $data['sub']         ?? '',
            $data['series']      ?? 'select',
            $data['bg']          ?? '#0b1220',
            $data['accent']      ?? '#3d6af2',
            $data['logo_color']  ?? 'white',
            (float)($data['safe_margin'] ?? 2.0),
            (int)($data['sort_order']    ?? 0),
            $id,
            $company_id,
        ]);
        json_out(['ok' => true]);
        break;

    case 'POST:delete':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        db()->prepare('UPDATE products SET stack_id = NULL WHERE stack_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM stacks WHERE id = ? AND company_id = ?')->execute([$id, $company_id]);
        json_out(['ok' => true]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
