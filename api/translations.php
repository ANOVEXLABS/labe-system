<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user   = auth();
$action = $_GET['action'] ?? '';
$pid    = (int)($_GET['product_id'] ?? 0);
$lang   = preg_replace('/[^a-z]/', '', strtolower($_GET['lang'] ?? 'de'));

// Flatten product_translations row → plain object for JS
function flattenTrans(array $row): array {
    $fields = isset($row['fields_json']) && $row['fields_json']
        ? json_decode($row['fields_json'], true) : [];
    $fs     = isset($row['fs_json']) && $row['fs_json']
        ? json_decode($row['fs_json'], true) : null;
    $style  = isset($row['style_json']) && $row['style_json']
        ? json_decode($row['style_json'], true) : null;
    unset($row['fields_json'], $row['fs_json'], $row['style_json']);
    return array_merge($row, $fields ?? [], ['fs' => $fs, 'style' => $style]);
}

// Extract fields_json from flat POST data
function extractTransFields(array $data): array {
    $skip    = ['id','product_id','lang','created_at','updated_at','fs','style','fields_json','fs_json','style_json'];
    $jsonArr = ['ings','feats'];
    $fields  = [];
    foreach ($data as $k => $v) {
        if (in_array($k, $skip)) continue;
        $fields[$k] = in_array($k, $jsonArr)
            ? (is_string($v) ? json_decode($v, true) : $v)
            : $v;
    }
    return $fields;
}

switch ($action) {

    // GET ?action=get&product_id=X&lang=de
    case 'get':
        if (!$pid) json_error('Chybí product_id.');
        $stmt = db()->prepare(
            'SELECT * FROM product_translations WHERE product_id=? AND lang=? LIMIT 1'
        );
        $stmt->execute([$pid, $lang]);
        $row = $stmt->fetch();
        json_out($row ? flattenTrans($row) : (object)[]);
        break;

    // GET ?action=all_langs&product_id=X
    case 'all_langs':
        if (!$pid) json_error('Chybí product_id.');
        $stmt = db()->prepare(
            'SELECT lang, updated_at FROM product_translations WHERE product_id=? ORDER BY lang'
        );
        $stmt->execute([$pid]);
        json_out($stmt->fetchAll());
        break;

    // POST ?action=save&product_id=X&lang=de
    case 'save':
        if (!$pid) json_error('Chybí product_id.');
        $data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $fields = extractTransFields($data);
        $fs     = $data['fs']    ?? null;
        $style  = $data['style'] ?? null;
        $stmt   = db()->prepare('
            INSERT INTO product_translations (product_id, lang, fields_json, fs_json, style_json)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              fields_json = VALUES(fields_json),
              fs_json     = VALUES(fs_json),
              style_json  = VALUES(style_json),
              updated_at  = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $pid, $lang,
            json_encode($fields, JSON_UNESCAPED_UNICODE),
            $fs    ? json_encode($fs,    JSON_UNESCAPED_UNICODE) : null,
            $style ? json_encode($style, JSON_UNESCAPED_UNICODE) : null,
        ]);
        json_out(['ok' => true]);
        break;

    // POST ?action=delete&product_id=X&lang=de
    case 'delete':
        if (!$pid) json_error('Chybí product_id.');
        db()->prepare(
            'DELETE FROM product_translations WHERE product_id=? AND lang=?'
        )->execute([$pid, $lang]);
        json_out(['ok' => true]);
        break;

    // POST ?action=ai_translate&product_id=X
    case 'ai_translate':
        if (!$pid) json_error('Chybí product_id.');
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $api_key = $data['api_key'] ?? '';
        if (!$api_key) $api_key = setting('anthropic_api_key', '');
        if (!$api_key) json_error('Chybí API klíč — nastav ho v Nastavení.');
        $target = $data['target_lang'] ?? 'de';
        $source = $data['source']      ?? [];

        $translatable = ['name','sub','name_full','doplnek','davkovani','upozorneni',
                         'skladovani','obsah_baleni','serv','slozeni','sarze','feats'];
        $to_translate = [];
        foreach ($translatable as $f) {
            if (!empty($source[$f])) $to_translate[$f] = $source[$f];
        }
        // ings — přeložit textové části
        if (!empty($source['ings']) && is_array($source['ings'])) {
            $to_translate['ings'] = $source['ings'];
        }

        $lang_names = [
            'de'=>'němčina','en'=>'angličtina','sk'=>'slovenština','pl'=>'polština',
            'hu'=>'maďarština','fr'=>'francouzština','it'=>'italština','ro'=>'rumunština',
            'hr'=>'chorvatština','nl'=>'nizozemština','sv'=>'švédština','da'=>'dánština',
        ];
        $lang_name = $lang_names[$target] ?? $target;

        $prompt = "Přelož do jazyka $lang_name tato pole pro etiketu doplňku stravy. " .
                  "Zachovej formátování a odbornou terminologii. " .
                  "Vrať pouze JSON objekt se stejnými klíči.\n\n" .
                  json_encode($to_translate, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 2000,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) json_error('AI API chyba: ' . $resp, 500);
        $ai        = json_decode($resp, true);
        $text      = $ai['content'][0]['text'] ?? '';
        $clean     = preg_replace('/```json|```/', '', $text);
        $translated = json_decode(trim($clean), true);
        if (!$translated) json_error('AI vrátila neplatný JSON.');

        json_out(['ok' => true, 'translated' => $translated]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
