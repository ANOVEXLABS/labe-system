<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$user = auth(); // přesměruje na login.php pokud nepřihlášen
$distributor_name    = setting('distributor_name', 'ANOVEX');
$distributor_address = setting('distributor_address', '');

// Načti dodavatele
$suppliers = db()->query('SELECT * FROM suppliers WHERE active = 1 ORDER BY id')->fetchAll();

// Načti presety (jen code+name pro dropdown)
$presets = db()->query('SELECT id, code, name, width_mm, height_mm FROM size_presets WHERE active = 1 ORDER BY id')->fetchAll();

// Geometrie presetů — hardcoded, nezávislé na DB sloupcích
function buildPreset(float $wMm, float $hMm): array {
    $VW   = (int)round($wMm * 300 / 25.4);
    $VH   = (int)round($hMm * 300 / 25.4);
    $bOff = max(12, (int)round(min($VW, $VH) * 0.025));
    $inner = $VW - 2 * $bOff;
    $LX1  = $bOff;
    $LX2  = $bOff + (int)round($inner * 0.27);
    $CX1  = $LX2;
    $CX2  = $bOff + (int)round($inner * 0.65);
    $RX1  = $CX2;
    $RX2  = $VW - $bOff;
    $sc   = $VH / 827.0;
    return [
        'VW'=>$VW,'VH'=>$VH,'c2bW'=>$VW,'c2bH'=>$VH,
        'LX1'=>$LX1,'LX2'=>$LX2,'CX1'=>$CX1,'CX2'=>$CX2,'RX1'=>$RX1,'RX2'=>$RX2,
        'sep1'=>$LX2,'sep2'=>$RX1,
        'F'=>[
            'min'=>max(14,(int)round(26*$sc)),'xs'=>max(16,(int)round(30*$sc)),
            'sm'=>max(18,(int)round(36*$sc)), 'md'=>max(22,(int)round(46*$sc)),
            'lg'=>max(28,(int)round(58*$sc)), 'xl'=>max(36,(int)round(72*$sc)),
            'xxl'=>max(44,(int)round(90*$sc)),'big'=>max(56,(int)round(112*$sc)),
            'ttl'=>max(70,(int)round(140*$sc)),
        ],
        'wL'=>$LX2-$LX1,'wC'=>$CX2-$CX1,'wR'=>$RX2-$RX1,'wZ'=>$inner,
        'LH'=>[
            'xs'=>max(18,(int)round(32*$sc)),'sm'=>max(22,(int)round(40*$sc)),
            'md'=>max(26,(int)round(52*$sc)),'lg'=>max(32,(int)round(64*$sc)),
        ],
    ];
}
$presets_hardcoded = [
    '110x40'    => buildPreset(110,  40),
    '110x50'    => buildPreset(110,  50),
    '180x70'    => buildPreset(180,  70),
    '200x80'    => buildPreset(200,  80),
    '205x82'    => buildPreset(205,  82),
    '205x82sym' => buildPreset(205,  82),
    '280x112'   => buildPreset(280, 112),
];
$preset_labels = [
    '110x40'    => '110 × 40 mm',
    '110x50'    => '110 × 50 mm',
    '180x70'    => '180 × 70 mm',
    '200x80'    => '200 × 80 mm',
    '205x82'    => '205 × 82 mm',
    '205x82sym' => '205 × 82 mm (sym)',
    '280x112'   => '280 × 112 mm',
];
// Přidej případné DB presety s neznámým kódem
foreach ($presets as $p) {
    if (!isset($presets_hardcoded[$p['code']]) && $p['width_mm'] && $p['height_mm']) {
        $presets_hardcoded[$p['code']] = buildPreset((float)$p['width_mm'], (float)$p['height_mm']);
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ANOVEX Label System v2</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">
<link rel="stylesheet" href="assets/css/app.css?v=<?= time() ?>">

</head>
<body>

<!-- HEADER -->
<div id="hdr">
  <div class="hdr-left">
    <div class="hdr-logo">ANOVEX</div>
    <div class="hdr-sep"></div>
    <div class="hdr-title">Label System <span class="hdr-ver">v2</span></div>
  </div>
  <div class="hdr-center">
    <select id="supplier-select" onchange="App.setSupplier(this.value)">
      <?php foreach ($suppliers as $s): ?>
      <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach ?>
    </select>
    <div class="hdr-sep"></div>
    <div id="lang-tabs">
      <button class="lang-tab active" data-lang="cs" onclick="App.switchLang('cs')"><span class="fi fi-cz"></span><span class="lt-label">CZ</span></button>
      <button class="lang-tab" data-lang="de" onclick="App.switchLang('de')"><span class="fi fi-de"></span><span class="lt-label">DE</span></button>
      <button class="lang-tab" data-lang="en" onclick="App.switchLang('en')"><span class="fi fi-gb"></span><span class="lt-label">EN</span></button>
      <button class="lang-tab" data-lang="pl" onclick="App.switchLang('pl')"><span class="fi fi-pl"></span><span class="lt-label">PL</span></button>
      <button class="lang-tab" data-lang="sk" onclick="App.switchLang('sk')"><span class="fi fi-sk"></span><span class="lt-label">SK</span></button>
    </div>
  </div>
  <div class="hdr-right">
    <button class="btn btn-ghost" onclick="App.exportSVG()">SVG</button>
    <button class="btn btn-ghost" onclick="App.exportPNG()">PNG</button>
    <button class="btn btn-gold" onclick="App.exportPDF()">Exportovat PDF</button>
    <div class="hdr-sep"></div>
    <span class="hdr-user"><?= htmlspecialchars($user['name']) ?></span>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="settings.php" class="btn btn-ghost">⚙</a>
    <?php endif ?>
    <a href="api/auth.php?action=logout" onclick="fetch('api/auth.php?action=logout').then(()=>location.href='login.php');return false;" class="btn btn-ghost">Odhlásit</a>
  </div>
</div>

<!-- LAYOUT -->
<div id="layout" style="margin-top:48px;height:calc(100vh - 48px)">

  <!-- LEVÝ PANEL -->
  <aside id="pnl">

    <!-- PDF PARSER -->
    <div class="ps">
      <div class="ps-lbl">Načíst z PDF etikety (C2B)</div>
      <div style="font-size:10px;color:var(--muted);line-height:1.35;margin-bottom:8px">
        API klíč se ukládá v <a href="settings.php" style="color:var(--gold);text-decoration:none">Nastavení</a>. Tady už ho není potřeba zadávat.
      </div>

      <div id="pdf-drop" role="button" tabindex="0"
        onclick="document.getElementById('pdf-file').click();"
        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();document.getElementById('pdf-file').click();}"
        ondragenter="event.preventDefault();this.style.borderColor='var(--gold)';"
        ondragover="event.preventDefault();this.style.borderColor='var(--gold)';"
        ondragleave="event.preventDefault();this.style.borderColor='#2a2a2a';"
        ondrop="event.preventDefault();this.style.borderColor='#2a2a2a';var f=Array.from(event.dataTransfer.files||[]).find(function(x){return /\.pdf$/i.test(x.name)||x.type==='application/pdf';});if(f){document.getElementById('pdf-status').style.color='var(--gold)';document.getElementById('pdf-status').textContent='Soubor zachycen: '+f.name;App.parseLabelPDF(f);}else{document.getElementById('pdf-status').style.color='var(--red)';document.getElementById('pdf-status').textContent='Vybraný soubor není PDF.';}"
        style="border:2px dashed #2a2a2a;border-radius:6px;padding:12px 10px;text-align:center;cursor:pointer;user-select:none">
        <div style="font-size:12px;color:var(--text);font-weight:600">Přetáhněte PDF nebo klikněte</div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px">Automaticky vyplní formulář</div>
      </div>

      <input type="file" id="pdf-file" accept=".pdf,application/pdf" style="display:none" onchange="if(this.files&&this.files[0]){document.getElementById('pdf-status').style.color='var(--gold)';document.getElementById('pdf-status').textContent='Vybrán soubor: '+this.files[0].name;App.parseLabelPDF(this.files[0]);}this.value='';">
      <div id="pdf-status" style="font-size:12px;color:var(--muted);min-height:18px;margin-top:8px"></div>
    </div>

    <!-- SKU IMPORT -->
    <div class="ps">
      <div class="ps-lbl">Import SKU z C2B Excelu</div>
      <div id="sku-drop" onclick="document.getElementById('sku-file').click()"
        style="border:2px dashed #2a2a2a;border-radius:6px;padding:12px 10px;text-align:center;cursor:pointer">
        <div style="font-size:12px;color:var(--text);font-weight:600">Přetáhni nebo klikni — .xlsx</div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px">Spáruje SKU podle EAN</div>
      </div>
      <input type="file" id="sku-file" accept=".xlsx,.xls" style="display:none" onchange="App.importSKU(this.files[0])">
      <div id="sku-status" style="font-size:12px;color:var(--muted);min-height:18px;margin-top:8px"></div>
    </div>

    <!-- STACKY -->
    <div class="ps">
      <div class="ps-lbl">Formulace / Řada
        <?php if (isAdmin()): ?>
        <button class="ps-add" onclick="App.createStack()" title="Nový stack">+</button>
        <?php endif ?>
      </div>
      <select id="stack-select" onchange="App._selectStackById(this.value)"></select>
    </div>

    <!-- PRODUKTY -->
    <div class="ps">
      <div class="ps-lbl">Produkty ve stacku
        <button class="ps-add" onclick="App.createProduct()" title="Nový produkt">+</button>
      </div>
      <div id="prod-list"></div>
      <div style="display:flex;gap:6px;margin-top:8px">
        <button class="hdr-btn btn-ghost" style="flex:1;font-size:9px" onclick="App.createProduct()">+ Prázdný</button>
        <button class="hdr-btn btn-ghost" style="flex:1;font-size:9px" onclick="App.deleteProduct()">✕ Smazat</button>
        <button class="hdr-btn btn-ghost" style="flex:0 0 30px;font-size:11px" onclick="App.moveUp()">↑</button>
        <button class="hdr-btn btn-ghost" style="flex:0 0 30px;font-size:11px" onclick="App.moveDown()">↓</button>
      </div>
      <div class="ps-lbl" style="margin-top:12px">Přesunout do stacku</div>
      <select id="move-target"></select>
      <button class="hdr-btn btn-ghost" style="width:100%;font-size:9px;margin-top:6px" onclick="App.moveToStack()">Přesunout →</button>
    </div>

    <!-- CHYBĚJÍCÍ PŘEKLAD -->
    <div id="trans-missing-bar" class="ps" style="display:none">
      <div style="font-size:11px;color:var(--muted);margin-bottom:8px">
        Překlad neexistuje. Vyplňte ručně nebo vygenerujte z CZ.
      </div>
      <button class="hdr-btn btn-gold" style="width:100%;font-size:9px;padding:6px 0"
        onclick="App.aiTranslate()">AI překlad z CZ</button>
      <div id="trans-status" style="font-size:11px;color:var(--muted);min-height:16px;margin-top:6px"></div>
    </div>

    <!-- FORMULÁŘ — LEVÁ ZÓNA -->
    <div class="ps">
      <div class="ps-lbl">Levá zóna</div>
      <div class="fld"><label>Název stacku (na etiketě)</label><input id="f-sen" oninput="App.update()"></div>
      <div class="fld"><label>Podnadpis stacku</label><input id="f-ssub" oninput="App.update()"></div>
      <div class="fld"><label>Doplněk stravy</label><input id="f-doplnek" oninput="App.update()" value="DOPLNĚK STRAVY"></div>
      <div class="fld"><label>Dávkování</label><textarea id="f-dosage" rows="3" oninput="App.update()"></textarea></div>
      <div class="fs-inline" data-fskey="dosage">
        <button type="button" onclick="App._fsChange('dosage', parseFloat(document.getElementById('fsv_dosage').textContent) - 0.5)">−</button>
        <span id="fsv_dosage" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('dosage', parseFloat(document.getElementById('fsv_dosage').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('dosage', parseFloat(this.value))" id="fsr_dosage">
      </div>
      <div class="fld"><label>Upozornění — specifické věty navíc <small style="font-weight:400;opacity:0.6">(zákonné věty přidávány automaticky)</small></label><textarea id="f-warn" rows="3" oninput="App.update()" placeholder="Není vhodné pro těhotné a kojící ženy."></textarea></div>
      <div class="fld"><label>Uchovávání — doplněk navíc <small style="font-weight:400;opacity:0.6">(základní věta přidána automaticky)</small></label><textarea id="f-storage" rows="2" oninput="App.update()" placeholder="Po otevření spotřebujte do 3 měsíců."></textarea></div>
      <div class="frow">
        <div class="fld"><label>Obsah balení</label><input id="f-net" oninput="App.update()"></div>
        <div class="fld"><label>Čistá hmotnost</label><input id="f-w4" oninput="App.update()"></div>
      </div>
      <div class="fld"><label>Šarže / Trvanlivost</label><input id="f-sarze" oninput="App.update()" value="Č. šarže / Min. trvanlivost: viz. obal"></div>
      <div class="fs-inline" data-fskey="meta">
        <button type="button" onclick="App._fsChange('meta', parseFloat(document.getElementById('fsv_meta').textContent) - 0.5)">−</button>
        <span id="fsv_meta" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('meta', parseFloat(document.getElementById('fsv_meta').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('meta', parseFloat(this.value))" id="fsr_meta">
      </div>
    </div>

    <!-- FORMULÁŘ — CENTRUM -->
    <div class="ps">
      <div class="ps-lbl">Centrum</div>
      <div class="fld"><label>Název látky (velký)</label><input id="f-bname" oninput="App.update()"></div>
      <div class="fs-inline" data-fskey="bname">
        <button type="button" onclick="App._fsChange('bname', parseFloat(document.getElementById('fsv_bname').textContent) - 0.5)">−</button>
        <span id="fsv_bname" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('bname', parseFloat(document.getElementById('fsv_bname').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('bname', parseFloat(this.value))" id="fsr_bname">
      </div>
      <div class="fld"><label>Podtitulek</label><input id="f-sub" oninput="App.update()"></div>
      <div class="fs-inline" data-fskey="sub">
        <button type="button" onclick="App._fsChange('sub', parseFloat(document.getElementById('fsv_sub').textContent) - 0.5)">−</button>
        <span id="fsv_sub" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('sub', parseFloat(document.getElementById('fsv_sub').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('sub', parseFloat(this.value))" id="fsr_sub">
      </div>
      <div class="fld"><label>Obsah / počet</label><input id="f-count" oninput="App.update()"></div>
      <div class="fs-inline" data-fskey="count">
        <button type="button" onclick="App._fsChange('count', parseFloat(document.getElementById('fsv_count').textContent) - 0.5)">−</button>
        <span id="fsv_count" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('count', parseFloat(document.getElementById('fsv_count').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('count', parseFloat(this.value))" id="fsr_count">
      </div>
      <div class="frow">
        <div class="fld"><label>Pořadí (1/4)</label><input id="f-num" oninput="App.update()"></div>
        <div class="fld"><label>Plný název</label><input id="f-nfull" oninput="App.update()"></div>
      </div>
      <div class="frow">
        <div class="fld"><label>Feat 1</label><input id="f-f1" oninput="App.update()"></div>
        <div class="fld"><label>Feat 2</label><input id="f-f2" oninput="App.update()"></div>
      </div>
      <div class="frow">
        <div class="fld"><label>Feat 3</label><input id="f-f3" oninput="App.update()"></div>
        <div class="fld"><label>Feat 4</label><input id="f-f4" oninput="App.update()"></div>
      </div>
    </div>

    <!-- FORMULÁŘ — PRAVÁ ZÓNA -->
    <div class="ps">
      <div class="ps-lbl">Pravá zóna — složení</div>
      <div style="display:flex;gap:6px;margin-bottom:10px">
        <button id="mode-btn-table" class="hdr-btn btn-gold" style="flex:1;padding:6px 0;font-size:9px" onclick="App.setIngMode('table')">Tabulka</button>
        <button id="mode-btn-text"  class="hdr-btn btn-ghost" style="flex:1;padding:6px 0;font-size:9px" onclick="App.setIngMode('text')">Text (čárky)</button>
      </div>
      <div class="fld"><label>Denní dávka (záhlaví tabulky)</label><input id="f-serv" oninput="App.update()"></div>
      <div class="fs-inline" data-fskey="serv">
        <button type="button" onclick="App._fsChange('serv', parseFloat(document.getElementById('fsv_serv').textContent) - 0.5)">−</button>
        <span id="fsv_serv" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('serv', parseFloat(document.getElementById('fsv_serv').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('serv', parseFloat(this.value))" id="fsr_serv">
      </div>
      <div class="fld">
        <label>Složení (každý řádek: Název | Množství | %RH)</label>
        <textarea id="f-ings" rows="8" oninput="App.update()" style="font-family:monospace;font-size:11px"></textarea>
      <div class="fs-inline" data-fskey="ings">
        <button type="button" onclick="App._fsChange('ings', parseFloat(document.getElementById('fsv_ings').textContent) - 0.5)">−</button>
        <span id="fsv_ings" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('ings', parseFloat(document.getElementById('fsv_ings').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('ings', parseFloat(this.value))" id="fsr_ings">
      </div>
        <div style="font-size:10px;color:var(--muted);margin-top:3px">Nové řádky = nové řádky na etiketě</div>
      </div>
      <div class="fld"><label>SLOŽENÍ (text)</label><textarea id="f-slozeni" rows="4" oninput="App.update()"></textarea></div>
      <div class="fs-inline" data-fskey="slozeni">
        <button type="button" onclick="App._fsChange('slozeni', parseFloat(document.getElementById('fsv_slozeni').textContent) - 0.5)">−</button>
        <span id="fsv_slozeni" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('slozeni', parseFloat(document.getElementById('fsv_slozeni').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('slozeni', parseFloat(this.value))" id="fsr_slozeni">
      </div>

      <div class="fld" style="margin-top:12px"><label>Distributor <small style="font-weight:400;opacity:0.6">(velikost textu)</small></label></div>
      <div class="fs-inline" data-fskey="dist">
        <button type="button" onclick="App._fsChange('dist', parseFloat(document.getElementById('fsv_dist').textContent) - 0.5)">−</button>
        <span id="fsv_dist" class="fs-val-inline">20</span>
        <button type="button" onclick="App._fsChange('dist', parseFloat(document.getElementById('fsv_dist').textContent) + 0.5)">+</button>
        <input type="range" min="8" max="90" step="0.5" class="fs-slider-inline"
          oninput="App._fsChange('dist', parseFloat(this.value))" id="fsr_dist">
      </div>
    </div>

    <!-- IDENTIFIKACE -->
    <div class="ps">
      <div class="ps-lbl">Identifikace a technické</div>
      <div class="frow">
        <div class="fld"><label>EAN barcode</label><input id="f-ean" oninput="App.update()"></div>
        <div class="fld"><label>SKU (C2B)</label><input id="f-sku" oninput="App.update()" placeholder="C2B-XXX-00"></div>
      </div>
      <div class="fld"><label>Reference ID (jen u duplikátů)</label><input id="f-refid" oninput="App.update()" placeholder="8896000001"></div>
      <div class="fld"><label>Originální název (C2B)</label><input id="f-origname" oninput="App.update()"></div>
      <div class="frow">
        <div class="fld"><label>Preset etikety</label>
          <select id="f-preset" onchange="App.update()">
            <?php foreach ($presets_hardcoded as $code => $_): ?>
            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($preset_labels[$code] ?? $code) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>



    <!-- KATALOG -->
    <div class="ps">
      <div class="ps-lbl">Katalog produktů</div>
      <input id="cat-search" placeholder="Hledat název nebo EAN…" oninput="App.buildCatalog()"
        style="width:100%;background:var(--b1);border:1px solid #252525;border-radius:4px;color:var(--text);font-family:'Montserrat',sans-serif;font-size:12px;padding:6px 9px;margin-bottom:8px">
      <div id="cat-list"></div>
    </div>

  </aside>

  <!-- HLAVNÍ PLOCHA — PREVIEW -->
  <main id="main">
    <div id="preview-wrap" style="flex:1;display:flex;flex-direction:column;overflow:hidden">
      <div id="preview-toolbar" style="display:flex;align-items:center;gap:12px;padding:10px 20px;font-size:11px;color:var(--muted);flex-shrink:0;border-bottom:1px solid #1c1c1c">
        <span id="prv-dims"></span>
        <input type="range" id="zoom-range" min="20" max="400" value="100" oninput="App.setZoom(this.value)" style="width:140px;accent-color:var(--gold)">
        <span id="zoom-val">100%</span>
      </div>
      <div id="prv-inner" style="flex:1;overflow:auto;padding:24px;position:relative">
        <div id="lbl-wrap" style="transform-origin:top left;transition:transform .1s;display:inline-block">
          <div id="lbl-svg"></div>
        </div>
      </div>
    </div>
  </main>

</div>

<!-- DATA pro JS -->
<script>
const PRESETS_MAP = <?= json_encode($presets_hardcoded, JSON_PRETTY_PRINT) ?>;

const DISTRIBUTOR = {
  name:    <?= json_encode($distributor_name) ?>,
  address: <?= json_encode($distributor_address) ?>
};

const CURRENT_USER = <?= json_encode($user) ?>;
const API_KEY_STORAGE = 'anovex_api_key_v2';
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="assets/js/pdf-builder.js?v=<?= time() ?>"></script>
<script src="assets/js/label-renderer.js?v=<?= time() ?>"></script>
<script src="assets/js/app.js?v=<?= time() ?>"></script>

<script>
// Patch: jazykové záložky — pokud app.js na serveru je stará verze bez switchLang
(function() {
  if (typeof App !== 'undefined' && typeof App.switchLang === 'function') return; // nová verze — nic nedělat

  let _lang = 'cs';
  let _translation = null;
  let _existingLangs = [];

  function _api(url, data) {
    const opts = data
      ? { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) }
      : { method:'GET' };
    return fetch(url, opts).then(r => r.json());
  }

  function _updateTabs() {
    document.querySelectorAll('.lang-tab').forEach(btn => {
      const l = btn.dataset.lang;
      btn.classList.toggle('active', l === _lang);
      btn.classList.toggle('has-translation', l === 'cs' || _existingLangs.includes(l));
    });
    const bar = document.getElementById('trans-missing-bar');
    if (bar) bar.style.display = (_lang !== 'cs' && !_translation) ? '' : 'none';
  }

  async function switchLang(lang) {
    if (lang === _lang) return;
    _lang = lang;
    _translation = null;
    if (lang !== 'cs' && App && App._currentProductId) {
      try {
        const tr = await _api(`/api/translations.php?action=get&product_id=${App._currentProductId}&lang=${lang}`);
        _translation = (tr && Object.keys(tr).length > 0) ? tr : null;
      } catch(e) {}
    }
    _updateTabs();
    // Nastav lang v App state pokud metoda existuje
    if (App && typeof App.setLang === 'function') App.setLang(lang);
  }

  async function aiTranslate() {
    const bar = document.getElementById('trans-status');
    if (bar) { bar.style.color = 'var(--gold)'; bar.textContent = '⏳ Překládám…'; }
    // Deleguj na App pokud existuje
    if (App && typeof App.aiTranslate === 'function') { App.aiTranslate(); return; }
  }

  // Přidej do window aby onclick handlery fungovaly
  if (typeof App === 'undefined') {
    window.App = { switchLang, aiTranslate, updateLangTabs: _updateTabs };
  } else {
    App.switchLang = switchLang;
    App.updateLangTabs = _updateTabs;
    if (typeof App.aiTranslate !== 'function') App.aiTranslate = aiTranslate;
  }

  document.addEventListener('DOMContentLoaded', _updateTabs);
})();
</script>

</body>
</html>
