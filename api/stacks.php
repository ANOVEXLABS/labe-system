<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user = auth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

switch ($method . ':' . $action) {

    // GET /api/stacks.php — seznam všech stacků
    case 'GET:':
    case 'GET:list':
        $supplier_id = (int)($_GET['supplier_id'] ?? 1);
        $stmt = db()->prepare('SELECT * FROM stacks WHERE supplier_id = ? AND active = 1 ORDER BY sort_order, id');
        $stmt->execute([$supplier_id]);
        json_out($stmt->fetchAll());
        break;

    // GET /api/stacks.php?action=get&id=X
    case 'GET:get':
        $stmt = db()->prepare('SELECT * FROM stacks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Stack nenalezen.', 404);
        json_out($row);
        break;

    // POST /api/stacks.php?action=create
    case 'POST:create':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stmt = db()->prepare('INSERT INTO stacks (supplier_id, code, name, sub, series, bg, accent, logo_color, sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            (int)($data['supplier_id'] ?? 1),
            $data['code']       ?? ('s' . time()),
            $data['name']       ?? 'Nový stack',
            $data['sub']        ?? '',
            $data['series']     ?? 'formula',
            $data['bg']         ?? '#0f0d08',
            $data['accent']     ?? '#c9a84c',
            $data['logo_color'] ?? 'gold',
            (int)($data['sort_order'] ?? 0),
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    // POST /api/stacks.php?action=update&id=X
    case 'POST:update':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $stmt = db()->prepare('UPDATE stacks SET name=?, sub=?, series=?, bg=?, accent=?, logo_color=?, sort_order=? WHERE id=?');
        $stmt->execute([
            $data['name']       ?? '',
            $data['sub']        ?? '',
            $data['series']     ?? 'formula',
            $data['bg']         ?? '#0f0d08',
            $data['accent']     ?? '#c9a84c',
            $data['logo_color'] ?? 'gold',
            (int)($data['sort_order'] ?? 0),
            $id,
        ]);
        json_out(['ok' => true]);
        break;

    // POST /api/stacks.php?action=delete&id=X
    case 'POST:delete':
        if (!isAdmin()) json_error('Pouze admin.', 403);
        db()->prepare('UPDATE stacks SET active = 0 WHERE id = ?')->execute([$id]);
        json_out(['ok' => true]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
