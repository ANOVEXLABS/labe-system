<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$user = auth(); // přesměruje na login.php pokud nepřihlášen
$distributor_name    = setting('distributor_name', 'ANOVEX');
$distributor_address = setting('distributor_address', '');

// Načti dodavatele
$suppliers = db()->query('SELECT * FROM suppliers WHERE active = 1 ORDER BY id')->fetchAll();

// Načti presety
$presets = db()->query('SELECT * FROM size_presets WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$presets_map = array_column($presets, null, 'code');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ANOVEX Label System v2</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= time() ?>">
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
    <select id="lang-select" onchange="App.setLang(this.value)">
      <option value="cs">🇨🇿 Čeština</option>
      <option value="de">🇩🇪 Němčina</option>
      <option value="en">🇬🇧 Angličtina</option>
      <option value="sk">🇸🇰 Slovenština</option>
    </select>
  </div>
  <div class="hdr-right">
    <button class="btn btn-ghost" onclick="App.exportSVG()">SVG</button>
    <button class="btn btn-ghost" onclick="App.exportPNG()">PNG</button>
    <button class="btn btn-gold" onclick="App.exportPDF()">Exportovat PDF</button>
    <div class="hdr-sep"></div>
    <span class="hdr-user"><?= htmlspecialchars($user['name']) ?></span>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="/settings.php" class="btn btn-ghost">⚙</a>
    <?php endif ?>
    <a href="/api/auth.php?action=logout" onclick="fetch('/api/auth.php?action=logout').then(()=>location.href='/login.php');return false;" class="btn btn-ghost">Odhlásit</a>
  </div>
</div>

<!-- LAYOUT -->
<div id="layout">

  <!-- LEVÝ PANEL -->
  <aside id="pnl">

    <!-- PDF PARSER -->
    <div class="ps">
      <div class="ps-lbl">Načíst z PDF etikety (C2B)</div>
      <div style="font-size:10px;color:var(--muted);line-height:1.35;margin-bottom:8px">
        API klíč se ukládá v <a href="/settings.php" style="color:var(--gold);text-decoration:none">Nastavení</a>. Tady už ho není potřeba zadávat.
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
        <?php if ($user['role'] === 'admin'): ?>
        <button class="ps-add" onclick="App.createStack()" title="Nový stack">+</button>
        <?php endif ?>
      </div>
      <div id="stack-list"></div>
    </div>

    <!-- PRODUKTY -->
    <div class="ps">
      <div class="ps-lbl">Produkty ve stacku
        <button class="ps-add" onclick="App.createProduct()" title="Nový produkt">+</button>
      </div>
      <div id="prod-list"></div>
      <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
        <button class="hdr-btn btn-ghost" style="flex:1;font-size:9px" onclick="App.deleteProduct()">✕ Smazat</button>
        <button class="hdr-btn btn-ghost" style="flex:1;font-size:9px" onclick="App.moveUp()">↑</button>
        <button class="hdr-btn btn-ghost" style="flex:1;font-size:9px" onclick="App.moveDown()">↓</button>
      </div>
      <div class="ps-lbl" style="margin-top:12px">Přesunout do stacku</div>
      <select id="move-target" style="width:100%;background:var(--b1);border:1px solid #252525;border-radius:4px;color:var(--text);font-family:'Montserrat',sans-serif;font-size:11px;padding:5px 8px;margin-bottom:6px"></select>
      <button class="hdr-btn btn-ghost" style="width:100%;font-size:9px" onclick="App.moveToStack()">Přesunout →</button>
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
            <?php foreach ($presets as $p): ?>
            <option value="<?= $p['code'] ?>"><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
    </div>



    <!-- PŘEKLADY -->
    <div class="ps">
      <div class="ps-lbl">Překlady</div>
      <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px">
        <select id="trans-lang" style="flex:1;background:var(--b1);border:1px solid #252525;border-radius:4px;color:var(--text);font-family:'Montserrat',sans-serif;font-size:11px;padding:5px 8px">
          <option value="de">🇩🇪 Němčina</option>
          <option value="en">🇬🇧 Angličtina</option>
          <option value="sk">🇸🇰 Slovenština</option>
        </select>
        <button class="hdr-btn btn-ghost" style="font-size:9px;padding:5px 10px" onclick="App.loadTranslation()">Načíst</button>
        <button class="hdr-btn btn-gold" style="font-size:9px;padding:5px 10px" onclick="App.aiTranslate()">AI překlad</button>
      </div>
      <div id="trans-status" style="font-size:11px;color:var(--muted);min-height:16px"></div>
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
const PRESETS_MAP = <?= json_encode(array_map(function($p) {
    return [
        'VW'=>(int)$p['vw'],'VH'=>(int)$p['vh'],
        'c2bW'=>(int)$p['c2b_w'],'c2bH'=>(int)$p['c2b_h'],
        'LX1'=>(int)$p['lx1'],'LX2'=>(int)$p['lx2'],
        'CX1'=>(int)$p['cx1'],'CX2'=>(int)$p['cx2'],
        'RX1'=>(int)$p['rx1'],'RX2'=>(int)$p['rx2'],
        'sep1'=>(int)$p['sep1'],'sep2'=>(int)$p['sep2'],
        'F'=>['min'=>(int)$p['f_min'],'xs'=>(int)$p['f_xs'],'sm'=>(int)$p['f_sm'],
              'md'=>(int)$p['f_md'],'lg'=>(int)$p['f_lg'],'xl'=>(int)$p['f_xl'],
              'xxl'=>(int)$p['f_xxl'],'big'=>(int)$p['f_big'],'ttl'=>(int)$p['f_ttl']],
        'wL'=>(int)$p['w_l'],'wC'=>(int)$p['w_c'],'wR'=>(int)$p['w_r'],'wZ'=>(int)$p['w_z'],
        'LH'=>['xs'=>(int)$p['lh_xs'],'sm'=>(int)$p['lh_sm'],'md'=>(int)$p['lh_md'],'lg'=>(int)$p['lh_lg']],
    ];
}, $presets_map), JSON_PRETTY_PRINT) ?>;

const DISTRIBUTOR = {
  name:    <?= json_encode($distributor_name) ?>,
  address: <?= json_encode($distributor_address) ?>
};

const CURRENT_USER = <?= json_encode($user) ?>;
const API_KEY_STORAGE = 'anovex_api_key_v2';
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="/assets/js/pdf-builder.js?v=<?= time() ?>"></script>
<script src="/assets/js/label-renderer.js?v=<?= time() ?>"></script>
<script src="/assets/js/app.js?v=<?= time() ?>"></script>

</body>
</html>
