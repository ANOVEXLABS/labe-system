<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user = auth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Pole která se ukládají
$FIELDS = ['ean','sku','refid','orig_name','preset_code','name','sub','count','num',
           'name_full','net','doplnek_stravy','davkovani','upozorneni','skladovani',
           'obsah_baleni','sarze','serv','slozeni','storage','ing_mode'];
$JSON_FIELDS = ['ings','feats','fs'];

function buildProduct(array $data, array $fields, array $jsonFields): array {
    $out = [];
    foreach ($fields as $f) {
        $out[$f] = $data[$f] ?? null;
    }
    foreach ($jsonFields as $f) {
        $val = $data[$f] ?? null;
        $out[$f] = is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
    }
    return $out;
}

switch ($method . ':' . $action) {

    // GET ?action=list&stack_id=X
    case 'GET:list':
        $stack_id = (int)($_GET['stack_id'] ?? 0);
        if (!$stack_id) json_error('Chybí stack_id.');
        $stmt = db()->prepare('SELECT * FROM products WHERE stack_id = ? AND active = 1 ORDER BY sort_order, id');
        $stmt->execute([$stack_id]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            foreach (['ings','feats','fs'] as $f) {
                $r[$f] = $r[$f] ? json_decode($r[$f], true) : null;
            }
        }
        json_out($rows);
        break;

    // GET ?action=get&id=X
    case 'GET:get':
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_error('Produkt nenalezen.', 404);
        foreach (['ings','feats','fs'] as $f) {
            $row[$f] = $row[$f] ? json_decode($row[$f], true) : null;
        }
        json_out($row);
        break;

    // GET ?action=all&supplier_id=X — pro katalog
    case 'GET:all':
        $supplier_id = (int)($_GET['supplier_id'] ?? 1);
        $stmt = db()->prepare('
            SELECT p.*, s.name AS stack_name, s.accent
            FROM products p
            JOIN stacks s ON p.stack_id = s.id
            WHERE p.supplier_id = ? AND p.active = 1
            ORDER BY s.sort_order, p.sort_order, p.id
        ');
        $stmt->execute([$supplier_id]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            foreach (['ings','feats','fs'] as $f) {
                $r[$f] = $r[$f] ? json_decode($r[$f], true) : null;
            }
        }
        json_out($rows);
        break;

    // POST ?action=create
    case 'POST:create':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $p = buildProduct($data, $FIELDS, $JSON_FIELDS);
        $cols = implode(',', array_keys($p));
        $vals = implode(',', array_fill(0, count($p), '?'));
        $stmt = db()->prepare("INSERT INTO products (stack_id, supplier_id, sort_order, $cols) VALUES (?,?,?,$vals)");
        $stmt->execute(array_merge([
            (int)($data['stack_id']    ?? 0),
            (int)($data['supplier_id'] ?? 1),
            (int)($data['sort_order']  ?? 0),
        ], array_values($p)));
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    // POST ?action=update&id=X
    case 'POST:update':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $p = buildProduct($data, $FIELDS, $JSON_FIELDS);
        // Přidej sort_order a stack_id
        $p['sort_order'] = (int)($data['sort_order'] ?? 0);
        $p['stack_id']   = (int)($data['stack_id']   ?? 0);
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($p)));
        $stmt = db()->prepare("UPDATE products SET $set WHERE id=?");
        $stmt->execute([...array_values($p), $id]);
        json_out(['ok' => true]);
        break;

    // POST ?action=delete&id=X
    case 'POST:delete':
        db()->prepare('UPDATE products SET active = 0 WHERE id = ?')->execute([$id]);
        json_out(['ok' => true]);
        break;

    // POST ?action=copy&id=X — kopie produktu do jiného stacku
    case 'POST:copy':
        $data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $target   = (int)($data['target_stack_id'] ?? 0);
        if (!$target) json_error('Chybí target_stack_id.');
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $orig = $stmt->fetch();
        if (!$orig) json_error('Produkt nenalezen.', 404);
        unset($orig['id'], $orig['created_at'], $orig['updated_at']);
        $orig['stack_id'] = $target;
        $orig['fs']       = null; // reset font sizes
        $cols = implode(',', array_keys($orig));
        $vals = implode(',', array_fill(0, count($orig), '?'));
        $ins  = db()->prepare("INSERT INTO products ($cols) VALUES ($vals)");
        $ins->execute(array_values($orig));
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    // POST ?action=move&id=X — přesun produktu do jiného stacku
    case 'POST:move':
        $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $target = (int)($data['target_stack_id'] ?? 0);
        if (!$target) json_error('Chybí target_stack_id.');
        $stmt = db()->prepare('UPDATE products SET stack_id = ?, fs = NULL WHERE id = ?');
        $stmt->execute([$target, $id]);
        json_out(['ok' => true]);
        break;

    // POST ?action=import_sku — import SKU z Excelu (EAN → SKU mapa)
    case 'POST:import_sku':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $map  = $data['map'] ?? []; // [{ean, sku, title}, ...]
        if (!$map) json_error('Prázdná mapa.');
        $updated = 0;
        $stmt = db()->prepare('SELECT id, ean FROM products WHERE active = 1');
        $stmt->execute();
        $products = $stmt->fetchAll();
        foreach ($products as $prod) {
            $ean = preg_replace('/\D/', '', $prod['ean'] ?? '');
            if (!$ean) continue;
            foreach ($map as $entry) {
                $excelEan = preg_replace('/\D/', '', $entry['ean'] ?? '');
                $match = false;
                // 1. Přesný match
                if ($excelEan === $ean) $match = true;
                // 2. Jeden obsahuje druhý (posun z parseru)
                if (!$match && strlen($ean) >= 10 && strlen($excelEan) >= 10) {
                    if (strpos($excelEan, substr($ean, 0, 10)) !== false ||
                        strpos($ean, substr($excelEan, 0, 10)) !== false) {
                        $match = true;
                    }
                }
                // 3. Posledních 10 číslic
                if (!$match && strlen($ean) >= 10 && strlen($excelEan) >= 10) {
                    if (substr($ean, -10) === substr($excelEan, -10)) $match = true;
                }
                if ($match) {
                    // Aktualizuj SKU + opraven EAN z excelu (důvěryhodnější zdroj)
                    $upd = db()->prepare('UPDATE products SET sku=?, ean=?, orig_name=COALESCE(NULLIF(orig_name,""),?) WHERE id=?');
                    $upd->execute([$entry['sku'], $excelEan, $entry['title'] ?? '', $prod['id']]);
                    $updated++;
                    break;
                }
            }
        }
        json_out(['ok' => true, 'updated' => $updated]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
