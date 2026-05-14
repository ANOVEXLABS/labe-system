<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user       = auth();
$company_id = (int)($user['company_id'] ?? 1);
$method     = $_SERVER['REQUEST_METHOD'];
$action     = $_GET['action'] ?? '';
$id         = (int)($_GET['id'] ?? 0);

// Top-level DB columns — vše ostatní jde do fields_json
const TOP = ['id','company_id','supplier_id','template_id','stack_id','preset_id',
             'sku','ean','refid','width_mm','height_mm','ing_mode','active',
             'created_at','updated_at','fields_json','fs_json','style_json','sort_order'];

// Převede DB řádek na plochý objekt pro JS (stejná struktura jako starý schema)
function flattenProduct(array $row): array {
    $fields = isset($row['fields_json']) && $row['fields_json']
        ? json_decode($row['fields_json'], true) : [];
    $fs     = isset($row['fs_json']) && $row['fs_json']
        ? json_decode($row['fs_json'], true) : null;
    $style  = isset($row['style_json']) && $row['style_json']
        ? json_decode($row['style_json'], true) : null;
    unset($row['fields_json'], $row['fs_json'], $row['style_json']);
    return array_merge($row, $fields ?? [], ['fs' => $fs, 'style' => $style]);
}

// Ze vstupních dat vytáhne co patří do fields_json
function extractFields(array $data): array {
    $jsonArr = ['ings', 'feats'];
    $fields  = [];
    foreach ($data as $k => $v) {
        if (in_array($k, TOP) || $k === 'fs' || $k === 'style') continue;
        $fields[$k] = in_array($k, $jsonArr)
            ? (is_string($v) ? json_decode($v, true) : $v)
            : $v;
    }
    return $fields;
}

function getPresetId(string $code, int $cid): ?int {
    $s = db()->prepare(
        'SELECT id FROM size_presets WHERE code=? AND (company_id IS NULL OR company_id=?) LIMIT 1'
    );
    $s->execute([$code, $cid]);
    $r = $s->fetch();
    return $r ? (int)$r['id'] : null;
}

switch ($method . ':' . $action) {

    case 'GET:list':
        $stack_id = (int)($_GET['stack_id'] ?? 0);
        if (!$stack_id) json_error('Chybí stack_id.');
        $s = db()->prepare(
            'SELECT * FROM products WHERE stack_id=? AND company_id=? AND active=1 ORDER BY id'
        );
        $s->execute([$stack_id, $company_id]);
        json_out(array_map('flattenProduct', $s->fetchAll()));
        break;

    case 'GET:get':
        $s = db()->prepare('SELECT * FROM products WHERE id=? AND company_id=? LIMIT 1');
        $s->execute([$id, $company_id]);
        $row = $s->fetch();
        if (!$row) json_error('Produkt nenalezen.', 404);
        json_out(flattenProduct($row));
        break;

    case 'GET:all':
        $supplier_id = (int)($_GET['supplier_id'] ?? 0);
        $s = db()->prepare('
            SELECT p.*, st.name AS stack_name
            FROM products p
            JOIN stacks st ON p.stack_id = st.id
            WHERE p.supplier_id=? AND p.company_id=? AND p.active=1
            ORDER BY st.sort_order, p.id
        ');
        $s->execute([$supplier_id, $company_id]);
        json_out(array_map('flattenProduct', $s->fetchAll()));
        break;

    case 'POST:create':
        $data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $fields    = extractFields($data);
        $pcode     = $data['preset_code'] ?? ($fields['preset_code'] ?? '180x70');
        $preset_id = getPresetId($pcode, $company_id);
        $fs        = $data['fs']    ?? null;
        $style     = $data['style'] ?? null;
        $s = db()->prepare('
            INSERT INTO products
              (company_id,supplier_id,template_id,stack_id,preset_id,
               sku,ean,refid,width_mm,height_mm,ing_mode,fields_json,fs_json,style_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $s->execute([
            $company_id,
            (int)($data['supplier_id'] ?? 0),
            (int)($data['template_id'] ?? 1),
            (int)($data['stack_id']    ?? 0),
            $preset_id,
            $data['sku']       ?? null,
            $data['ean']       ?? null,
            $data['refid']     ?? null,
            $data['width_mm']  ?? null,
            $data['height_mm'] ?? null,
            $data['ing_mode']  ?? 'table',
            json_encode($fields, JSON_UNESCAPED_UNICODE),
            $fs    ? json_encode($fs,    JSON_UNESCAPED_UNICODE) : null,
            $style ? json_encode($style, JSON_UNESCAPED_UNICODE) : null,
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'POST:update':
        $data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $fields    = extractFields($data);
        $pcode     = $data['preset_code'] ?? ($fields['preset_code'] ?? null);
        $preset_id = $pcode ? getPresetId($pcode, $company_id) : null;
        $fs        = $data['fs']    ?? null;
        $style     = $data['style'] ?? null;
        $s = db()->prepare('
            UPDATE products SET
              stack_id=?,preset_id=?,sku=?,ean=?,refid=?,
              width_mm=?,height_mm=?,ing_mode=?,
              fields_json=?,fs_json=?,style_json=?
            WHERE id=? AND company_id=?
        ');
        $s->execute([
            (int)($data['stack_id'] ?? 0),
            $preset_id,
            $data['sku']       ?? null,
            $data['ean']       ?? null,
            $data['refid']     ?? null,
            $data['width_mm']  ?? null,
            $data['height_mm'] ?? null,
            $data['ing_mode']  ?? 'table',
            json_encode($fields, JSON_UNESCAPED_UNICODE),
            $fs    ? json_encode($fs,    JSON_UNESCAPED_UNICODE) : null,
            $style ? json_encode($style, JSON_UNESCAPED_UNICODE) : null,
            $id,
            $company_id,
        ]);
        json_out(['ok' => true]);
        break;

    case 'POST:delete':
        db()->prepare('UPDATE products SET active=0 WHERE id=? AND company_id=?')
            ->execute([$id, $company_id]);
        json_out(['ok' => true]);
        break;

    case 'POST:copy':
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $target = (int)($data['target_stack_id'] ?? 0);
        if (!$target) json_error('Chybí target_stack_id.');
        $s = db()->prepare('SELECT * FROM products WHERE id=? AND company_id=? LIMIT 1');
        $s->execute([$id, $company_id]);
        $orig = $s->fetch();
        if (!$orig) json_error('Produkt nenalezen.', 404);
        $ins = db()->prepare('
            INSERT INTO products
              (company_id,supplier_id,template_id,stack_id,preset_id,
               sku,ean,refid,width_mm,height_mm,ing_mode,fields_json,style_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $ins->execute([
            $company_id, $orig['supplier_id'], $orig['template_id'],
            $target,     $orig['preset_id'],   $orig['sku'],
            $orig['ean'],$orig['refid'],        $orig['width_mm'],
            $orig['height_mm'], $orig['ing_mode'],
            $orig['fields_json'], $orig['style_json'],
        ]);
        json_out(['ok' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'POST:move':
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $target = (int)($data['target_stack_id'] ?? 0);
        if (!$target) json_error('Chybí target_stack_id.');
        db()->prepare('UPDATE products SET stack_id=?, fs_json=NULL WHERE id=? AND company_id=?')
            ->execute([$target, $id, $company_id]);
        json_out(['ok' => true]);
        break;

    case 'POST:import_sku':
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $map  = $data['map'] ?? [];
        if (!$map) json_error('Prázdná mapa.');
        $updated = 0;
        $s = db()->prepare('SELECT id, ean FROM products WHERE company_id=? AND active=1');
        $s->execute([$company_id]);
        foreach ($s->fetchAll() as $prod) {
            $ean = preg_replace('/\D/', '', $prod['ean'] ?? '');
            if (!$ean) continue;
            foreach ($map as $entry) {
                $ex = preg_replace('/\D/', '', $entry['ean'] ?? '');
                $match = $ex === $ean
                    || (strlen($ean)>=10 && strlen($ex)>=10 && (
                        strpos($ex, substr($ean,0,10)) !== false ||
                        strpos($ean, substr($ex,0,10)) !== false ||
                        substr($ean,-10) === substr($ex,-10)
                    ));
                if ($match) {
                    db()->prepare('UPDATE products SET sku=?,ean=? WHERE id=? AND company_id=?')
                        ->execute([$entry['sku'], $ex, $prod['id'], $company_id]);
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
