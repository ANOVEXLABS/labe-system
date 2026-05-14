<?php
/**
 * ANOVEX Label System — PDF Parser
 * POST /api/parse_label.php { api_key, pdf_base64, filename }
 * → { ok: true, data: {...} }
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');
$user = auth();

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$api_key = trim($data['api_key'] ?? '');
if ($api_key === '') {
    $api_key = trim(setting('anthropic_api_key', ''));
}
$pdf_b64  = $data['pdf_base64'] ?? '';
$filename = trim($data['filename'] ?? '');

if (!$api_key) json_error('Chybí Anthropic API klíč. Ulož ho v Nastavení → Anthropic API klíč pro parsování PDF.');
if (!$pdf_b64) json_error('Chybí PDF data.');

$prompt = <<<PROMPT
Jsi specializovaný parser etiket doplňků stravy pro systém ANOVEX.

ÚKOL:
Z přiložené originální C2B PDF etikety vytáhni všechna data potřebná pro vložení do ANOVEX etikety. Vrať POUZE validní JSON objekt. Žádný markdown, žádný komentář.

DŮLEŽITÉ:
- Originální etiketa je většinou německy. Výstup přelož do češtiny.
- Nezkracuj regulatorní texty, pokud to není výslovně uvedeno.
- Zachovej čísla, jednotky, procenta, EAN a dávkování přesně.
- Pokud hodnota není na etiketě, vrať prázdný řetězec "".
- Pokud % NRV/RH není stanovena, použij "/".
- Pokud najdeš EAN/čárový kód, vrať 13 číslic bez mezer.
- SKU často není přímo v PDF. Pokud není uvedeno, vrať "".
- Název souboru může napovědět SKU nebo rozměr: {$filename}

VRACEJ TUTO STRUKTURU:
{
  "name": "krátký marketingový název produktu pro střed etikety, např. Shilajit",
  "sub": "krátký podtitulek/role, např. 20 % fulvinových kyselin",
  "nameFull": "plný regulatorní název produktu v češtině",
  "origname": "originální název z etikety nebo souboru, pokud je dostupný",
  "count": "počet kusů / množství pro střed etikety, např. 90 kapslí",
  "net": "obsah včetně čisté hmotnosti, např. 90 kapslí = 41 g",
  "preset": "jeden z těchto kódů podle rozměru etikety: 110x50, 180x70, 200x80, 110x40, 205x82, 205x82sym, 280x112",
  "ean": "EAN kód, 13 číslic",
  "sku": "SKU kód dodavatele, pokud je známý",
  "davkovani": "samotný text dávkování bez nadpisu",
  "w4": "POUZE specifická upozornění navíc. VYNECH obecné zákonné věty: Nepřekračujte doporučené denní dávkování. Není náhradou pestré a vyvážené stravy. Uchovávejte mimo dosah dětí. Pokud žádné specifické upozornění není, vrať prázdný řetězec.",
  "storage": "uchovávání. Pokud je na etiketě pouze běžná věta typu uzavřené/suché/chladné/chráněné před světlem, vrať ji také česky, protože se má zobrazit v etiketě. Pokud je doplněk typu po otevření spotřebujte do 3 měsíců, zahrň ho také.",
  "serv": "nadpis/kontext tabulky, např. na denní dávku (2 kapsle) nebo Složení denní dávky (2 kapsle)",
  "slozeni": "kompletní text složení v češtině bez nadpisu SLOŽENÍ:",
  "ings": [
    ["název složky", "množství", "%RH nebo /"]
  ],
  "feats": [
    "volitelná krátká vlastnost 1",
    "volitelná krátká vlastnost 2",
    "volitelná krátká vlastnost 3",
    "volitelná krátká vlastnost 4"
  ],
  "ingMode": "table"
}

PRAVIDLA PRO ROZMĚR / PRESET:
- Pokud PDF nebo název souboru obsahuje 110x50mm nebo rozměr blízký 110 × 50 mm, preset = "110x50".
- 180x70mm → "180x70".
- 200x80mm → "200x80".
- 110x40mm → "110x40".
- 205x82mm → "205x82".
- 280x112mm → "280x112".
- Pokud si nejsi jistý, odhadni podle PDF rozměru, ne podle obsahu.

PŘÍKLAD PRO SHILAJIT:
{
  "name": "Shilajit",
  "sub": "20 % fulvinových kyselin",
  "nameFull": "Shilajit 20 % fulvinových kyselin",
  "count": "90 kapslí",
  "net": "90 kapslí = 41 g",
  "preset": "110x50",
  "ean": "",
  "sku": "",
  "davkovani": "Denně 2 kapsle s 250 ml vody",
  "w4": "Není vhodné pro těhotné a kojící ženy.",
  "storage": "Uzavřené na suchém, chladném a před světlem chráněném místě",
  "serv": "na denní dávku (2 kapsle)",
  "slozeni": "Shilajit - suchý extrakt (Asphaltum punjabianum) (62,5 %), obalovací látka: hydroxypropylmethylcelulóza, plnidlo: mikrokrystalická celulóza, L-leucin",
  "ings": [
    ["Shilajit extrakt", "600 mg", "/"],
    ["z toho fulvinové kyseliny", "120 mg", "/"]
  ],
  "feats": ["20 % fulvinových kyselin", "Veganské kapsle", "Přírodní extrakt", "Bez umělých přísad"],
  "ingMode": "table"
}

Vrať pouze JSON.
PROMPT;

$payload = [
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => 5000,
    'messages'   => [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'document',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'application/pdf',
                    'data'       => $pdf_b64,
                ],
            ],
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ],
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err)          json_error('cURL chyba: ' . $err, 500);
if ($code !== 200) json_error('Claude API chyba (' . $code . '): ' . substr($resp, 0, 1000), 500);

$ai   = json_decode($resp, true);
$text = $ai['content'][0]['text'] ?? '';

$clean = trim($text);
$clean = preg_replace('/^```(?:json)?\s*/m', '', $clean);
$clean = preg_replace('/```\s*$/m', '', $clean);
$clean = trim($clean);

$parsed = json_decode($clean, true);

// Záchrana, pokud model omylem přidá text kolem JSONu
if (!$parsed && preg_match('/\{.*\}/s', $clean, $m)) {
    $parsed = json_decode($m[0], true);
}

if (!$parsed || !is_array($parsed)) {
    json_error('Claude vrátil neplatný JSON. Odpověď: ' . substr($text, 0, 700), 500);
}

// Normalizace pro frontend
$parsed['name']     = trim($parsed['name'] ?? '');
$parsed['sub']      = trim($parsed['sub'] ?? '');
$parsed['nameFull'] = trim($parsed['nameFull'] ?? ($parsed['name_full'] ?? ''));
$parsed['origname'] = trim($parsed['origname'] ?? ($parsed['orig_name'] ?? ''));
$parsed['count']    = trim($parsed['count'] ?? '');
$parsed['net']      = trim($parsed['net'] ?? '');
$parsed['preset']   = trim($parsed['preset'] ?? ($parsed['preset_code'] ?? ''));
$parsed['ean']      = preg_replace('/\D+/', '', (string)($parsed['ean'] ?? ''));
$parsed['sku']      = trim($parsed['sku'] ?? '');
$parsed['davkovani'] = trim($parsed['davkovani'] ?? '');
$parsed['w4']        = trim($parsed['w4'] ?? ($parsed['upozorneni'] ?? ''));
$parsed['storage']   = trim($parsed['storage'] ?? ($parsed['skladovani'] ?? ''));
$parsed['serv']      = trim($parsed['serv'] ?? '');
$parsed['slozeni']   = trim($parsed['slozeni'] ?? '');
$parsed['ingMode']   = $parsed['ingMode'] ?? ($parsed['ing_mode'] ?? 'table');

if (!$parsed['preset'] && preg_match('/(110x50|180x70|200x80|110x40|205x82|280x112)/i', $filename, $m)) {
    $parsed['preset'] = strtolower($m[1]);
}
if ($parsed['preset'] === '') $parsed['preset'] = '180x70';

if (!isset($parsed['ings']) || !is_array($parsed['ings'])) $parsed['ings'] = [];
if (!isset($parsed['feats']) || !is_array($parsed['feats'])) $parsed['feats'] = [];

json_out(['ok' => true, 'data' => $parsed]);
