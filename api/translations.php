<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user   = auth();
$action = $_GET['action'] ?? '';
$pid    = (int)($_GET['product_id'] ?? 0);
$lang   = preg_replace('/[^a-z]/', '', strtolower($_GET['lang'] ?? 'de'));

$TRANS_FIELDS = ['name','sub','count','name_full','davkovani','upozorneni',
                 'skladovani','obsah_baleni','serv','slozeni','storage'];

switch ($action) {

    // GET ?action=get&product_id=X&lang=de
    case 'get':
        if (!$pid) json_error('Chybí product_id.');
        $stmt = db()->prepare('SELECT * FROM translations WHERE product_id = ? AND lang = ? LIMIT 1');
        $stmt->execute([$pid, $lang]);
        $row = $stmt->fetch();
        if ($row && $row['ings']) $row['ings'] = json_decode($row['ings'], true);
        json_out($row ?: (object)[]);
        break;

    // GET ?action=all_langs&product_id=X — seznam dostupných překladů
    case 'all_langs':
        if (!$pid) json_error('Chybí product_id.');
        $stmt = db()->prepare('SELECT lang, updated_at FROM translations WHERE product_id = ? ORDER BY lang');
        $stmt->execute([$pid]);
        json_out($stmt->fetchAll());
        break;

    // POST ?action=save&product_id=X&lang=de
    case 'save':
        if (!$pid) json_error('Chybí product_id.');
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $cols = ['product_id' => $pid, 'lang' => $lang];
        foreach ($TRANS_FIELDS as $f) $cols[$f] = $data[$f] ?? null;
        $ings = $data['ings'] ?? null;
        $cols['ings'] = $ings ? json_encode($ings, JSON_UNESCAPED_UNICODE) : null;
        $keys   = implode(',', array_keys($cols));
        $vals   = implode(',', array_fill(0, count($cols), '?'));
        $update = implode(',', array_map(fn($k) => "$k=VALUES($k)", array_keys($cols)));
        $stmt   = db()->prepare("INSERT INTO translations ($keys) VALUES ($vals) ON DUPLICATE KEY UPDATE $update");
        $stmt->execute(array_values($cols));
        json_out(['ok' => true]);
        break;

    // POST ?action=delete&product_id=X&lang=de
    case 'delete':
        if (!$pid) json_error('Chybí product_id.');
        db()->prepare('DELETE FROM translations WHERE product_id = ? AND lang = ?')->execute([$pid, $lang]);
        json_out(['ok' => true]);
        break;

    // POST ?action=ai_translate — AI překlad přes Anthropic API
    case 'ai_translate':
        if (!$pid) json_error('Chybí product_id.');
        $data     = json_decode(file_get_contents('php://input'), true) ?? [];
        $api_key  = $data['api_key'] ?? '';
        $target   = $data['target_lang'] ?? 'de';
        $source   = $data['source'] ?? [];
        if (!$api_key) json_error('Chybí API klíč.');

        $fields_to_translate = ['name','sub','davkovani','upozorneni','skladovani',
                                'obsah_baleni','serv','slozeni','storage'];
        $to_translate = [];
        foreach ($fields_to_translate as $f) {
            if (!empty($source[$f])) $to_translate[$f] = $source[$f];
        }

        $lang_names = ['de' => 'němčina', 'en' => 'angličtina', 'sk' => 'slovenština',
                       'pl' => 'polština', 'hu' => 'maďarština', 'fr' => 'francouzština'];
        $lang_name  = $lang_names[$target] ?? $target;

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
        $ai   = json_decode($resp, true);
        $text = $ai['content'][0]['text'] ?? '';
        $clean = preg_replace('/```json|```/', '', $text);
        $translated = json_decode(trim($clean), true);
        if (!$translated) json_error('AI vrátila neplatný JSON.');

        json_out(['ok' => true, 'translated' => $translated]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
