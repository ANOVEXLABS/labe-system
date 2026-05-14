/**
 * ANOVEX Label System v2 — App.js
 * Hlavní aplikační logika — komunikace s PHP API, UI, export
 */

const App = (() => {
  // ── State ─────────────────────────────────────────────────────────────────
  let state = {
    supplierId: 1,
    stacks:     [],
    products:   [],
    curStack:   0,
    curProduct: null,
    product:    null,   // aktuální produkt (objekt z DB)
    stack:      null,   // aktuální stack (objekt z DB)
    lang:       'cs',
    translation: null,     // aktuální překlad pro non-CS záložku, nebo null
    existingLangs: [],     // které překlady existují v DB pro aktuální produkt
    distributor: DISTRIBUTOR,
    zoom:        100,
    ingMode:     'table',
    pending:     false, // probíhá ukládání
  };

  // ── PDF dimension reader ───────────────────────────────────────────────────
  async function readPdfDimensions(file) {
    const buf   = await file.arrayBuffer();
    const bytes = new Uint8Array(buf);
    let text = '';
    const limit = Math.min(bytes.length, 32768);
    for (let i = 0; i < limit; i++) text += String.fromCharCode(bytes[i]);
    const m = text.match(/MediaBox\s*\[\s*([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)\s+([\d.+-]+)\s*\]/);
    if (!m) return null;
    const ptW = parseFloat(m[3]) - parseFloat(m[1]);
    const ptH = parseFloat(m[4]) - parseFloat(m[2]);
    const mmW = Math.round(ptW * 25.4 / 72 * 10) / 10;
    const mmH = Math.round(ptH * 25.4 / 72 * 10) / 10;
    const pxW = Math.round(ptW * 300 / 72);
    const pxH = Math.round(ptH * 300 / 72);
    return { mmW, mmH, pxW, pxH, isPortrait: mmH > mmW };
  }

  // ── Dynamický buildPreset (kopie PHP buildPreset) ──────────────────────────
  function buildPresetJs(mmW, mmH) {
    const ri = n => Math.round(n);
    const VW   = ri(mmW * 300 / 25.4);
    const VH   = ri(mmH * 300 / 25.4);
    const bOff = Math.max(12, ri(Math.min(VW, VH) * 0.025));
    const inner = VW - 2 * bOff;
    const LX1  = bOff, LX2 = bOff + ri(inner * 0.27);
    const CX1  = LX2,  CX2 = bOff + ri(inner * 0.65);
    const RX1  = CX2,  RX2 = VW - bOff;
    const sc   = VH / 827.0;
    return {
      VW, VH, c2bW: VW, c2bH: VH,
      LX1, LX2, CX1, CX2, RX1, RX2, sep1: LX2, sep2: RX1,
      isPortrait: VH > VW,
      F: {
        min: Math.max(14, ri(26*sc)), xs: Math.max(16, ri(30*sc)),
        sm:  Math.max(18, ri(36*sc)), md: Math.max(22, ri(44*sc)),
        lg:  Math.max(26, ri(56*sc)), xl: Math.max(32, ri(68*sc)),
        xxl: Math.max(44, ri(90*sc)), big: Math.max(56, ri(112*sc)),
        ttl: Math.max(70, ri(140*sc)),
      },
      wL: LX2-LX1, wC: CX2-CX1, wR: RX2-RX1, wZ: inner,
      LH: {
        xs: Math.max(18, ri(32*sc)), sm: Math.max(22, ri(40*sc)),
        md: Math.max(26, ri(52*sc)), lg: Math.max(32, ri(64*sc)),
      },
    };
  }

  // ── Jednotný getter presetu — dynamický z rozměrů nebo z PRESETS_MAP ───────
  function getPreset(product) {
    if (product && product.width_mm && product.height_mm) {
      return buildPresetJs(parseFloat(product.width_mm), parseFloat(product.height_mm));
    }
    const pk = (product && product.preset_code) || '180x70';
    return PRESETS_MAP[pk] || PRESETS_MAP['180x70'];
  }

  // ── Font embedding cache (pro SVG export do PNG/PDF) ──────────────────────
  let _svgFontStyle = null;

  function _bufToBase64(buf) {
    const bytes = new Uint8Array(buf);
    let out = '';
    for (let i = 0; i < bytes.length; i += 8192) {
      out += String.fromCharCode(...bytes.subarray(i, i + 8192));
    }
    return btoa(out);
  }

  async function _getSvgFontStyle() {
    if (_svgFontStyle !== null) return _svgFontStyle;
    try {
      const css = await fetch(
        'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,600;0,700;0,800;1,400&display=swap'
      ).then(r => r.text());
      const urls = [...css.matchAll(/url\((https:\/\/[^)]+)\)/g)].map(m => m[1]);
      let embedded = css;
      for (const url of urls) {
        const b64 = _bufToBase64(await fetch(url).then(r => r.arrayBuffer()));
        embedded = embedded.replace(url, `data:font/woff2;base64,${b64}`);
      }
      _svgFontStyle = `<style>${embedded}</style>`;
    } catch(e) {
      _svgFontStyle = '';
    }
    return _svgFontStyle;
  }

  async function _buildExportSvg(c2bW, c2bH) {
    const fontStyle = await _getSvgFontStyle();
    return buildLabel(state.product, state.stack, getPreset(state.product), state.distributor, state.lang)
      .replace('width="100%"', `width="${c2bW}" height="${c2bH}"`)
      .replace('<defs>', `<defs>${fontStyle}`);
  }

  // ── Toast ──────────────────────────────────────────────────────────────────
  function toast(msg, type='ok', ms=2500) {
    let el = document.getElementById('toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.className = 'show ' + type;
    clearTimeout(el._t);
    el._t = setTimeout(() => el.className = '', ms);
  }

  // ── API helper ─────────────────────────────────────────────────────────────
  async function api(url, data) {
    const opts = data
      ? { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) }
      : { method: 'GET' };
    const r = await fetch(url, opts);
    const txt = await r.text();
    let json;
    try { json = JSON.parse(txt); }
    catch (e) { throw new Error('Server nevrátil JSON (' + r.status + '): ' + txt.slice(0, 220)); }
    if (!r.ok || json.error) throw new Error(json.error || ('HTTP ' + r.status));
    return json;
  }

  // ── Načtení stacků ─────────────────────────────────────────────────────────
  async function loadStacks() {
    state.stacks = await api(`api/stacks.php?supplier_id=${state.supplierId}`);
    buildStackList();
    if (state.stacks.length) {
      await selectStack(state.stacks[0]);
    }
  }

  function buildStackList() {
    const el = document.getElementById('stack-select');
    if (!el) return;
    const curId = state.stack ? state.stack.id : 0;
    el.innerHTML = state.stacks.map(st =>
      `<option value="${st.id}" ${st.id === curId ? 'selected' : ''}>${esc(st.name)}${st.sub ? ' — ' + esc(st.sub) : ''}</option>`
    ).join('');
    const mv = document.getElementById('move-target');
    if (mv) {
      mv.innerHTML = state.stacks
        .filter(st => st.id !== curId)
        .map(st => `<option value="${st.id}">${esc(st.name)}</option>`)
        .join('');
    }
  }

  async function selectStack(st) {
    state.stack     = st;
    state.curProduct = null;
    state.product    = null;
    buildStackList();
    await loadProducts();
  }

  // ── Načtení produktů ───────────────────────────────────────────────────────
  async function loadProducts() {
    if (!state.stack) return;
    state.products = await api(`api/products.php?action=list&stack_id=${state.stack.id}`);
    buildProdList();
    if (state.products.length) {
      await selectProduct(state.products[0]);
    } else {
      state.product = null;
      clearForm();
      update();
    }
  }

  function buildProdList() {
    const el = document.getElementById('prod-list');
    if (!el) return;
    const curId = state.product ? state.product.id : 0;
    el.innerHTML = state.products.map((p, i) => {
      const active = p.id === curId ? 'active' : '';
      const nm  = esc(p.name || p.sku || 'Produkt #' + p.id);
      const sub = p.sub  ? `<div class="pi-sub">${esc(p.sub)}</div>` : '';
      const sz  = p.preset_code ? `<span class="pi-sz">${esc(p.preset_code)}</span>` : '';
      const ean = p.ean  ? `<span class="pi-ean">${esc(p.ean)}</span>` : '';
      const meta = (ean || sz) ? `<div class="pi-meta">${ean}${sz}</div>` : '';
      return `<div class="pi ${active}" onclick="App._selectProductById(${p.id})">
        <div class="pi-num">${i + 1}</div>
        <div class="pi-body"><div class="pi-name">${nm}</div>${sub}${meta}</div>
      </div>`;
    }).join('');
    buildCatalog();
  }

  async function selectProduct(p) {
    if (state.product && state.product.id && state.product.id !== p.id) {
      if (state.lang === 'cs') await saveProduct();
      else if (state.translation) await saveTranslation();
    }
    state.product     = p;
    state.curProduct  = p.id;
    state.translation = null;
    state.existingLangs = [];
    buildProdList();
    if (state.lang !== 'cs') await _loadTranslationForActiveLang();
    await _loadExistingLangs();
    loadForm();
    updateLangTabs();
    update();
  }

  // ── Formulář ───────────────────────────────────────────────────────────────
  function g(id) { return document.getElementById(id); }
  function gv(id) { return (g(id) ? g(id).value : '') || ''; }
  function sv(id, val) { if (g(id)) g(id).value = val || ''; }

  function loadForm() {
    const p  = state.product;
    const st = state.stack;
    if (!p || !st) { clearForm(); return; }

    // Sdílená pole — vždy z produktu
    sv('f-sen',    st.name);
    sv('f-ssub',   st.sub);
    sv('f-ean',    p.ean);
    sv('f-sku',    p.sku);
    sv('f-origname', p.orig_name);
    if (g('f-preset')) g('f-preset').value = p.preset_code || '180x70';
    sv('f-doplnek', p.doplnek_stravy || 'DOPLNĚK STRAVY');
    sv('f-net',     p.net);
    sv('f-sarze',   p.sarze || 'Č. šarže / Min. trvanlivost: viz. obal');
    sv('f-num',     p.num);
    const feats = p.feats || [];
    ['f-f1','f-f2','f-f3','f-f4'].forEach((id, i) => sv(id, feats[i] || ''));

    if (state.lang === 'cs') {
      sv('f-bname',  p.name);     sv('f-sub',    p.sub);
      sv('f-count',  p.count);    sv('f-nfull',  p.name_full);
      sv('f-dosage', p.davkovani);sv('f-warn',   p.upozorneni);
      sv('f-storage',p.skladovani);sv('f-w4',    p.obsah_baleni);
      sv('f-serv',   p.serv);     sv('f-slozeni',p.slozeni);
      sv('f-refid',  p.refid);
      const ings = p.ings || [];
      sv('f-ings', ings.map(r => Array.isArray(r) ? r.join('|') : r).join('\n'));
    } else if (state.translation) {
      const tr = state.translation;
      sv('f-bname',  tr.name);    sv('f-sub',    tr.sub);
      sv('f-count',  tr.count);   sv('f-nfull',  tr.name_full);
      sv('f-dosage', tr.davkovani);sv('f-warn',  tr.upozorneni);
      sv('f-storage',tr.skladovani);sv('f-w4',   tr.obsah_baleni);
      sv('f-serv',   tr.serv);    sv('f-slozeni',tr.slozeni);
      sv('f-refid',  tr.refid);
      const ings = tr.ings || [];
      sv('f-ings', ings.map(r => Array.isArray(r) ? r.join('|') : r).join('\n'));
    } else {
      ['f-bname','f-sub','f-count','f-nfull','f-dosage','f-warn',
       'f-storage','f-w4','f-serv','f-ings','f-slozeni','f-refid']
      .forEach(id => sv(id, ''));
    }

    setIngMode(p.ing_mode || 'table', true);
    buildFsControls();
  }

  function clearForm() {
    ['f-sen','f-ssub','f-doplnek','f-dosage','f-warn','f-storage','f-net','f-w4','f-sarze',
     'f-bname','f-sub','f-count','f-num','f-nfull','f-f1','f-f2','f-f3','f-f4',
     'f-serv','f-ings','f-slozeni','f-ean','f-sku','f-refid','f-origname']
    .forEach(id => sv(id, ''));
    if (g('f-doplnek')) g('f-doplnek').value = 'DOPLNĚK STRAVY';
    if (g('f-sarze'))   g('f-sarze').value = 'Č. šarže / Min. trvanlivost: viz. obal';
  }

  function readForm() {
    if (!state.product) return;
    const p  = state.product;
    const st = state.stack;

    // Sdílená pole vždy do produktu
    if (st) { st.name = gv('f-sen'); st.sub = gv('f-ssub'); }
    p.doplnek_stravy = gv('f-doplnek');
    p.net        = gv('f-net');
    p.sarze      = gv('f-sarze');
    p.num        = gv('f-num');
    p.feats      = ['f-f1','f-f2','f-f3','f-f4'].map(id => gv(id)).filter(Boolean);
    p.ean        = gv('f-ean');
    p.sku        = gv('f-sku');
    p.orig_name  = gv('f-origname');
    p.preset_code = gv('f-preset') || '180x70';
    p.ing_mode   = state.ingMode;

    if (state.lang === 'cs') {
      p.name       = gv('f-bname');  p.sub        = gv('f-sub');
      p.count      = gv('f-count');  p.name_full  = gv('f-nfull');
      p.davkovani  = gv('f-dosage'); p.upozorneni = gv('f-warn');
      p.skladovani = gv('f-storage');p.obsah_baleni = gv('f-w4');
      p.serv       = gv('f-serv');   p.slozeni    = gv('f-slozeni');
      p.refid      = gv('f-refid');
      p.ings = gv('f-ings').split('\n').filter(l=>l.trim()).map(l=>{const pt=l.split('|');return[pt[0]||'',pt[1]||'',pt[2]||'/'];});
    } else if (state.translation) {
      const tr = state.translation;
      tr.name       = gv('f-bname');  tr.sub        = gv('f-sub');
      tr.count      = gv('f-count');  tr.name_full  = gv('f-nfull');
      tr.davkovani  = gv('f-dosage'); tr.upozorneni = gv('f-warn');
      tr.skladovani = gv('f-storage');tr.obsah_baleni = gv('f-w4');
      tr.serv       = gv('f-serv');   tr.slozeni    = gv('f-slozeni');
      tr.refid      = gv('f-refid');
      tr.ings = gv('f-ings').split('\n').filter(l=>l.trim()).map(l=>{const pt=l.split('|');return[pt[0]||'',pt[1]||'',pt[2]||'/'];});
    }
    // Pokud state.translation === null (non-CS bez překladu) — nic nepiš
  }

  // ── Font size controls ─────────────────────────────────────────────────────
  const FS_DEFS = [
    {key:'bname',  label:'Název látky'},
    {key:'logo',   label:'ANOVEX logo'},
    {key:'stack',  label:'Název stacku'},
    {key:'sub',    label:'Podtitulek'},
    {key:'count',  label:'Počet/obsah'},
    {key:'dosage', label:'Dávkování'},
    {key:'warn',   label:'Upozornění'},
    {key:'storage',label:'Uchovávání'},
    {key:'serv',   label:'Záhlaví složení'},
    {key:'ings',   label:'Složení tabulka'},
    {key:'slozeni',label:'SLOŽENÍ text'},
    {key:'meta',   label:'Meta (obsah/šarže)'},
    {key:'dist',   label:'Distributor'},
  ];

  function _activeFsObject() {
    if (state.lang === 'cs') {
      if (!state.product.fs) state.product.fs = {};
      return state.product.fs;
    } else {
      if (!state.translation) return null;
      if (!state.translation.fs) state.translation.fs = {};
      return state.translation.fs;
    }
  }

  function buildFsControls() {
    if (!state.product) return;
    const fs = _activeFsObject() || {};
    const P  = getPreset(state.product);
    const F  = P ? P.F : {min:16, xs:20, sm:24, md:30};

    // Pro portrait etikety: slider default z middle-zone Fm (ne z VH-scaled F)
    let Fbase = F;
    if (P && P.isPortrait) {
      const bOff = P.LX1, pad = 8;
      const h    = P.VH - 2*bOff - 2*pad;
      const midH = Math.round(h * 0.83) - Math.round(h * 0.45);
      const msc  = midH / 827.0;
      Fbase = { min: Math.max(14, Math.round(26*msc)), xs: Math.max(16, Math.round(30*msc)) };
    }

    // Aktualizuj všechny inline fs kontrolky
    FS_DEFS.forEach(({key}) => {
      const cur = fs[key] !== undefined ? fs[key] : (Fbase.min + 2);
      const valEl = g('fsv_' + key);
      if (valEl) valEl.textContent = Math.round(cur * 10) / 10;
      const slider = g('fsr_' + key);
      if (slider) {
        slider.min = 6;
        slider.max = 90;
        slider.value = Math.min(cur, 90);
      }
    });
  }

  // ── Rendering ──────────────────────────────────────────────────────────────
  function update() {
    readForm();
    const svgEl = g('lbl-svg');
    if (!svgEl) return;
    if (!state.product || !state.stack) {
      svgEl.innerHTML = '';
      return;
    }
    const P  = getPreset(state.product);
    // Automatické číslování: "1 / 4" apod. — pro SELECT sérii se nevykresluje
    const curIdx = state.products.findIndex(p => p && p.id === state.product.id);
    const isSel = state.stack && state.stack.series === 'select';
    const autoNum = isSel
      ? ''
      : (curIdx !== -1 && state.products.length > 0)
        ? `${curIdx + 1} / ${state.products.length}`
        : (state.product.num || '');
    // Dočasně nastavíme num pro renderování
    const origNum = state.product.num;
    state.product.num = autoNum;
    state.product._translation = state.lang !== 'cs' ? (state.translation || null) : null;
    const svgStr = buildLabel(state.product, state.stack, P, state.distributor, state.lang);
    state.product.num = origNum; // Vrátíme zpět
    svgEl.innerHTML = svgStr;

    // Dims
    const dims = g('prv-dims');
    if (dims && P) {
      dims.textContent = `${P.c2bW}×${P.c2bH}px · ${Math.round(P.c2bW*25.4/300)}×${Math.round(P.c2bH*25.4/300)}mm · 300dpi`;
    }

    applyZoom();
    scheduleSave();
  }

  function applyZoom() {
    const wrap = g('lbl-wrap');
    if (!wrap) return;
    wrap.style.transform = `scale(${state.zoom / 100})`;
    const el = g('zoom-val');
    if (el) el.textContent = Math.round(state.zoom) + '%';
  }

  function autoFitZoom() {
    const inner = g('prv-inner');
    const wrap  = g('lbl-wrap');
    if (!inner || !wrap) return;
    const aw = inner.clientWidth  - 48;
    const ah = inner.clientHeight - 48;
    if (aw <= 0 || ah <= 0) return;
    const svg = wrap.querySelector('svg');
    if (!svg) return;
    const vb = svg.getAttribute('viewBox');
    if (!vb) return;
    const [,, vw, vh] = vb.split(' ').map(Number);
    const fitW = (aw / vw) * 100;
    const fitH = (ah / vh) * 100;
    const pct  = Math.min(fitW, fitH, 150);
    state.zoom = Math.round(pct);
    const zr = g('zoom-range');
    if (zr) zr.value = state.zoom;
    applyZoom();
  }

  // ── Autosave (debounce 1.5s) ───────────────────────────────────────────────
  let saveTimer = null;
  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      if (state.lang === 'cs') saveProduct();
      else if (state.translation) saveTranslation();
    }, 1500);
  }

  async function saveProduct() {
    if (!state.product || !state.product.id || state.pending) return;
    state.pending = true;
    try {
      const p = state.product;
      const isNew = !p.id;
      const url = isNew
        ? `api/products.php?action=create`
        : `api/products.php?action=update&id=${p.id}`;
      const payload = {
        ...p,
        stack_id:    state.stack?.id,
        supplier_id: state.supplierId,
        ings:        p.ings || [],
        feats:       p.feats || [],
        fs:          p.fs || {},
      };
      const res = await api(url, payload);
      if (isNew && res.id) {
        p.id = res.id;
        // Aktualizuj stack jméno
        if (state.stack) {
          await api(`api/stacks.php?action=update&id=${state.stack.id}`, state.stack);
        }
      }
    } catch (e) {
      toast('Chyba ukládání: ' + e.message, 'err');
    } finally {
      state.pending = false;
    }
  }

  async function saveTranslation() {
    if (!state.product || !state.product.id || !state.translation || state.pending) return;
    state.pending = true;
    try {
      const tr = state.translation;
      await api(
        `api/translations.php?action=save&product_id=${state.product.id}&lang=${state.lang}`,
        { ...tr, ings: tr.ings || [], fs: tr.fs || {} }
      );
      if (!state.existingLangs.includes(state.lang)) {
        state.existingLangs.push(state.lang);
        updateLangTabs();
      }
    } catch (e) {
      toast('Chyba ukládání překladu: ' + e.message, 'err');
    } finally {
      state.pending = false;
    }
  }

  // ── Export ─────────────────────────────────────────────────────────────────
  function getFilename(ext) {
    const p  = state.product;
    const st = state.stack;
    if (!p || !st) return `anovex_label.${ext}`;
    const sku    = p.sku ? p.sku + '_' : '';
    const rid    = (state.lang !== 'cs' && state.translation?.refid)
                     ? state.translation.refid : (p.refid || '');
    const refid  = rid ? 'ID_' + rid + '_' : '';
    const stName = st.name.replace(/\s+/g, '-');
    const bname  = (p.name||'').replace(/\s+/g,'_').replace(/[^\w\-]/g,'');
    const pk     = p.width_mm ? `${p.width_mm}x${p.height_mm}` : (p.preset_code || '180x70');
    const lang   = state.lang !== 'cs' ? `_${state.lang.toUpperCase()}` : '';
    return `${sku}${refid}${stName}_${bname}_${pk}${lang}.${ext}`;
  }

  function exportSVG() {
    if (!state.product) return;
    const P  = getPreset(state.product);
    const svgStr = buildLabel(state.product, state.stack, P, state.distributor, state.lang);
    const blob = new Blob([svgStr], {type:'image/svg+xml'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = getFilename('svg');
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 3000);
  }

  async function exportPNG() {
    if (!state.product) return;
    const P  = getPreset(state.product);
    const {c2bW, c2bH} = P;
    const btn = document.querySelector('[onclick="App.exportPNG()"]');
    if (btn) { btn.textContent = '⏳'; btn.disabled = true; }
    try {
      const svgStr = await _buildExportSvg(c2bW, c2bH);
      const img = new Image();
      img.width = c2bW; img.height = c2bH;
      const blob = new Blob([svgStr], {type:'image/svg+xml'});
      const url  = URL.createObjectURL(blob);
      await new Promise((res,rej) => { img.onload=res; img.onerror=rej; img.src=url; });
      URL.revokeObjectURL(url);
      const canvas = document.createElement('canvas');
      canvas.width = c2bW; canvas.height = c2bH;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = state.stack?.bg || '#080808';
      ctx.fillRect(0, 0, c2bW, c2bH);
      ctx.drawImage(img, 0, 0, c2bW, c2bH);
      canvas.toBlob(b => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(b);
        a.download = getFilename('png');
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 5000);
      }, 'image/png');
      toast(`PNG ${c2bW}×${c2bH}px`, 'ok');
    } catch(e) {
      toast('Chyba PNG: ' + e.message, 'err');
    } finally {
      if (btn) { btn.textContent = 'PNG'; btn.disabled = false; }
    }
  }

  async function exportPDF() {
    if (!state.product) return;
    const P  = getPreset(state.product);
    const {c2bW, c2bH} = P;
    const btn = document.querySelector('[onclick="App.exportPDF()"]');
    if (btn) { btn.textContent = '⏳ Generuji…'; btn.disabled = true; }
    try {
      const svgStr = await _buildExportSvg(c2bW, c2bH);
      const img = new Image();
      img.width = c2bW; img.height = c2bH;
      const blob = new Blob([svgStr], {type:'image/svg+xml'});
      const url  = URL.createObjectURL(blob);
      await new Promise((res,rej) => { img.onload=res; img.onerror=rej; img.src=url; });
      URL.revokeObjectURL(url);
      const canvas = document.createElement('canvas');
      canvas.width = c2bW; canvas.height = c2bH;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = state.stack?.bg || '#080808';
      ctx.fillRect(0, 0, c2bW, c2bH);
      ctx.drawImage(img, 0, 0, c2bW, c2bH);
      const pdfBlob = await buildPDF(canvas, c2bW, c2bH);
      const a = document.createElement('a');
      a.href = URL.createObjectURL(pdfBlob);
      a.download = getFilename('pdf');
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 5000);
      toast(`PDF ${c2bW}×${c2bH}px`, 'ok');
    } catch(e) {
      toast('Chyba PDF: ' + e.message, 'err');
    } finally {
      if (btn) { btn.textContent = 'Exportovat PDF'; btn.disabled = false; }
    }
  }

  // ── Ing mode ───────────────────────────────────────────────────────────────
  function setIngMode(mode, silent) {
    state.ingMode = mode;
    if (state.product) state.product.ing_mode = mode;
    const btnT = g('mode-btn-table'), btnX = g('mode-btn-text');
    if (btnT && btnX) {
      btnT.className = 'hdr-btn ' + (mode === 'table' ? 'btn-gold' : 'btn-ghost');
      btnX.className = 'hdr-btn ' + (mode === 'text'  ? 'btn-gold' : 'btn-ghost');
    }
    if (!silent) update();
  }

  // ── Zoom ───────────────────────────────────────────────────────────────────
  function setZoom(val) {
    state.zoom = parseInt(val);
    const el = g('zoom-val');
    if (el) el.textContent = state.zoom + '%';
    applyZoom();
  }

  // ── CRUD akce ──────────────────────────────────────────────────────────────
  async function createStack() {
    const name = prompt('Název nového stacku:');
    if (!name) return;
    const res = await api('api/stacks.php?action=create', {
      supplier_id: state.supplierId,
      name,
    });
    await loadStacks();
    const st = state.stacks.find(s => s.id === res.id);
    if (st) await selectStack(st);
  }

  async function createProduct() {
    if (!state.stack) return;
    const res = await api('api/products.php?action=create', {
      stack_id:    state.stack.id,
      supplier_id: state.supplierId,
      name:        'Nový produkt',
      preset_code: '180x70',
      doplnek_stravy: 'DOPLNĚK STRAVY',
      sarze: 'Č. šarže / Min. trvanlivost: viz. obal',
      sort_order: state.products.length,
    });
    await loadProducts();
    const p = state.products.find(p => p.id === res.id);
    if (p) await selectProduct(p);
  }

  async function deleteProduct() {
    if (!state.product) return;
    if (!confirm('Smazat produkt "' + state.product.name + '"?')) return;
    try {
      clearTimeout(saveTimer);
      state.pending = false;
      const pid = state.product.id;
      state.product = null;
      state.curProduct = null;
      await api(`api/products.php?action=delete&id=${pid}`, {});
      toast('Produkt smazán', 'ok');
      await loadProducts();
    } catch(e) {
      console.error('deleteProduct error:', e);
      toast('Chyba mazání: ' + e.message, 'err');
      await loadProducts();
    }
  }

  async function moveUp() {
    if (!state.product) return;
    const idx = state.products.findIndex(p => p.id === state.product.id);
    if (idx <= 0) return;
    [state.products[idx-1], state.products[idx]] = [state.products[idx], state.products[idx-1]];
    await updateSortOrder();
  }

  async function moveDown() {
    if (!state.product) return;
    const idx = state.products.findIndex(p => p.id === state.product.id);
    if (idx >= state.products.length - 1) return;
    [state.products[idx], state.products[idx+1]] = [state.products[idx+1], state.products[idx]];
    await updateSortOrder();
  }

  async function updateSortOrder() {
    for (let i = 0; i < state.products.length; i++) {
      await api(`api/products.php?action=update&id=${state.products[i].id}`, {...state.products[i], sort_order: i});
    }
    buildProdList();
    update();
  }

  async function moveToStack() {
    if (!state.product) return;
    const targetId = parseInt(g('move-target')?.value || '0');
    if (!targetId) return;
    try {
      // Zruš autosave aby nepřepsal přesun
      clearTimeout(saveTimer);
      state.pending = false;
      const pid = state.product.id;
      // Odstraň produkt z lokálního stavu PŘED API voláním
      state.product = null;
      state.curProduct = null;
      await api(`api/products.php?action=move&id=${pid}`, {target_stack_id: targetId});
      toast('Produkt přesunut', 'ok');
      await loadProducts();
    } catch(e) {
      console.error('moveToStack error:', e);
      toast('Chyba přesunu: ' + e.message, 'err');
      await loadProducts(); // reload i při chybě
    }
  }

  // ── Katalog ────────────────────────────────────────────────────────────────
  async function buildCatalog() {
    const el = g('cat-list');
    if (!el) return;
    const q = (g('cat-search')?.value || '').toLowerCase().trim();
    try {
      const all = await api(`api/products.php?action=all&supplier_id=${state.supplierId}`);
      // Group by EAN
      const map = {};
      all.forEach(p => {
        const key = p.ean || ('__' + p.name);
        if (!map[key]) map[key] = {name: p.name, ean: p.ean, stacks: []};
        map[key].stacks.push({id: p.id, accent: p.accent, stackName: p.stack_name});
      });
      const items = Object.values(map).filter(it =>
        !q || it.name.toLowerCase().includes(q) || (it.ean||'').includes(q)
      );
      el.innerHTML = items.map(item => {
        const inCurrent = item.stacks.some(s => {
          const prod = state.products.find(p => p.ean === item.ean && p.id === s.id);
          return prod && state.stack && state.products.some(p => p.ean === item.ean);
        });
        const dots = item.stacks.map(s =>
          `<span style="width:8px;height:8px;border-radius:50%;background:${s.accent};display:inline-block" title="${esc(s.stackName)}"></span>`
        ).join('');
        const srcId = item.stacks[0]?.id;
        return `<div class="cat-item">
          <div style="display:flex;gap:3px;flex-shrink:0">${dots}</div>
          <div style="flex:1;min-width:0">
            <div class="cat-name">${esc(item.name)}</div>
            ${item.ean ? `<div class="cat-ean">${item.ean}</div>` : ''}
          </div>
          <button class="hdr-btn btn-ghost" style="padding:3px 8px;font-size:9px"
            onclick="App._copyFromCatalog(${srcId})">+ Přidat</button>
        </div>`;
      }).join('') || '<div style="font-size:11px;color:var(--muted);padding:4px">Katalog je prázdný</div>';
    } catch(e) { el.innerHTML = ''; }
  }

  async function _copyFromCatalog(srcId) {
    if (!state.stack) return;
    await api(`api/products.php?action=copy&id=${srcId}`, {target_stack_id: state.stack.id});
    await loadProducts();
    toast('Produkt přidán', 'ok');
  }

  // ── SKU Import ─────────────────────────────────────────────────────────────
  async function importSKU(file) {
    if (!file || typeof XLSX === 'undefined') return;
    const drop = g('sku-drop');
    const status = g('sku-status');
    if (status) { status.style.color = 'var(--gold)'; status.textContent = '⏳ Načítám…'; }
    const reader = new FileReader();
    reader.onload = async e => {
      try {
        const wb = XLSX.read(e.target.result, {type:'array'});
        const ws = wb.Sheets[wb.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(ws, {defval:''});
        const map = rows
          .map(r => ({
            ean:   String(r['EAN']||r['ean']||'').replace(/\D/g,'').trim(),
            sku:   String(r['SKU']||r['sku']||'').trim(),
            title: String(r['Title']||r['title']||'').trim(),
          }))
          .filter(r => r.ean.length >= 12 && r.sku);
        const res = await api('api/products.php?action=import_sku', {map});
        if (status) {
          status.style.color = 'var(--green)';
          status.textContent = `✓ Aktualizováno: ${res.updated} produktů`;
        }
        if (drop) drop.style.borderColor = 'var(--green)';
        await loadProducts();
        g('sku-file') && (g('sku-file').value = '');
      } catch(err) {
        if (status) { status.style.color = 'var(--red)'; status.textContent = '✗ ' + err.message; }
      }
    };
    reader.readAsArrayBuffer(file);
  }

  // ── Překlady ───────────────────────────────────────────────────────────────
  async function aiTranslate() {
    if (!state.product || state.lang === 'cs') return;
    const lang   = state.lang;
    const apiKey = localStorage.getItem(API_KEY_STORAGE) || '';
    const status = g('trans-status');
    if (status) { status.style.color = 'var(--gold)'; status.textContent = '⏳ Překládám…'; }
    try {
      const res = await api(`api/translations.php?action=ai_translate&product_id=${state.product.id}`, {
        api_key:     apiKey,
        target_lang: lang,
        source:      state.product,
      });
      const existing = state.translation || {};
      const merged = { ...existing, ...res.translated, product_id: state.product.id, lang };
      await api(
        `api/translations.php?action=save&product_id=${state.product.id}&lang=${lang}`,
        merged
      );
      state.translation = merged;
      if (!state.existingLangs.includes(lang)) state.existingLangs.push(lang);
      if (status) { status.style.color = 'var(--green)'; status.textContent = `✓ Přeloženo do ${lang.toUpperCase()}`; }
      updateLangTabs();
      loadForm();
      update();
    } catch(e) {
      if (status) { status.style.color = 'var(--red)'; status.textContent = '✗ ' + e.message; }
    }
  }

  // ── PDF Label Parser ───────────────────────────────────────────────────────
  async function parseLabelPDF(file) {
    if (state.parsingPDF) return;  // guard proti dvojitému volání (drag + click)
    const status = g('pdf-status');
    if (!file) {
      if (status) { status.style.color = 'var(--red)'; status.textContent = '✗ Nebyl vybrán žádný soubor.'; }
      return;
    }
    if (!(/\.pdf$/i.test(file.name) || file.type === 'application/pdf')) {
      if (status) { status.style.color = 'var(--red)'; status.textContent = '✗ Vybraný soubor není PDF: ' + file.name; }
      return;
    }
    state.parsingPDF = true;

    const apiKey = (g('pdf-api-key')?.value || '').trim(); // může být prázdné, backend použije klíč z Nastavení
    if (status) { status.style.color = 'var(--gold)'; status.textContent = '⏳ Čtu PDF: ' + file.name; }

    // Přečíst rozměry z MediaBox před base64 konverzí
    const dims = await readPdfDimensions(file);

    try {
      const b64 = await new Promise((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(String(r.result || '').split(',')[1] || '');
        r.onerror = () => rej(new Error('Nelze přečíst soubor'));
        r.readAsDataURL(file);
      });
      if (!b64) throw new Error('PDF se nepodařilo převést do base64.');

      if (status) status.textContent = '⏳ Analyzuji etiketu přes AI…';

      const result = await api('api/parse_label.php', {
        api_key:    apiKey,
        pdf_base64: b64,
        filename:   file.name,
      });

      const d = result.data || {};
      if (!Object.keys(d).length) throw new Error('Parser vrátil prázdná data.');

      // Pokud není vybraný žádný produkt, vytvoříme nový v aktuálním stacku.
      // Dříve se data načetla, ale neměla se kam zapsat — proto se nic nezobrazilo.
      if (!state.stack) throw new Error('Nejdříve vyber stack/formulaci vlevo.');
      // Každý nahraný PDF = nový produkt (nikdy nepřepisovat stávající)
      const createPayload = {
        stack_id:        state.stack.id,
        supplier_id:     state.supplierId,
        name:            d.name || d.nameFull || d.name_full || file.name.replace(/\.pdf$/i, ''),
        sub:             d.sub || '',
        count:           d.count || '',
        net:             d.net || '',
        ean:             d.ean || '',
        sku:             d.sku || '',
        orig_name:       d.origname || d.orig_name || d.original_name || '',
        name_full:       d.nameFull || d.name_full || d.full_name || d.name || '',
        preset_code:     d.preset || d.preset_code || '180x70',
        width_mm:        dims ? dims.mmW : null,
        height_mm:       dims ? dims.mmH : null,
        doplnek_stravy:  'DOPLNĚK STRAVY',
        sarze:           'Č. šarže / Min. trvanlivost: viz. obal',
        ing_mode:        d.ingMode || d.ing_mode || 'table',
        sort_order:      state.products.length,
        ings:            [],
        feats:           [],
        fs:              {},
      };
      const created = await api('api/products.php?action=create', createPayload);
      state.product = { ...createPayload, id: created.id };
      state.curProduct = created.id;
      state.products.push(state.product);
      buildProdList();
      loadForm();

      // Normalizace názvů klíčů ze staré HTML aplikace i nového PHP parseru
      const nameFull = d.nameFull || d.name_full || d.full_name || d.nfull || '';
      const origName = d.origname || d.orig_name || d.original_name || '';
      const warn     = d.w4 !== undefined ? d.w4 : (d.upozorneni !== undefined ? d.upozorneni : '');
      const storage  = d.storage !== undefined ? d.storage : (d.skladovani !== undefined ? d.skladovani : '');
      const preset   = d.preset || d.preset_code || d.size_preset || '';
      const ingMode  = d.ingMode || d.ing_mode || '';

      if (d.name)       sv('f-bname',   d.name);
      if (d.sub !== undefined)        sv('f-sub',     d.sub || '');
      if (d.count)      sv('f-count',   d.count);
      if (d.net)        sv('f-net',     d.net);
      if (d.ean)        sv('f-ean',     String(d.ean).replace(/\D/g,'').slice(0,13));
      if (d.sku)        sv('f-sku',     d.sku);
      if (nameFull)     sv('f-nfull',   nameFull);
      if (origName)     sv('f-origname', origName);
      if (d.davkovani !== undefined)  sv('f-dosage',  d.davkovani || '');
      if (warn !== undefined)         sv('f-warn',    warn || '');
      if (storage !== undefined)      sv('f-storage', storage || '');
      if (d.serv !== undefined)       sv('f-serv',    d.serv || '');
      if (d.slozeni !== undefined)    sv('f-slozeni', d.slozeni || '');
      if (preset && g('f-preset'))    g('f-preset').value = preset;
      if (ingMode) setIngMode(ingMode, true);

      if (Array.isArray(d.ings) && d.ings.length) {
        sv('f-ings', d.ings.map(r => Array.isArray(r) ? r.join('|') : r).join('\n'));
      }
      if (Array.isArray(d.feats)) {
        ['f-f1','f-f2','f-f3','f-f4'].forEach((id, i) => sv(id, d.feats[i] || ''));
      }

      // Přepsat objekt produktu z formuláře, vykreslit a ihned uložit do DB.
      readForm();
      update();
      clearTimeout(saveTimer);  // zruší timer z update() — sami voláme saveProduct() hned
      await saveProduct();
      buildProdList();

      const name = d.name || nameFull || file.name;
      const dimStr = dims ? ` · ${dims.mmW}×${dims.mmH}mm (${dims.pxW}×${dims.pxH}px)` : '';
      if (status) {
        status.style.color = 'var(--green)';
        status.textContent = '✓ ' + name + dimStr;
      }

    } catch(e) {
      console.error('parseLabelPDF error:', e);
      if (status) { status.style.color = 'var(--red)'; status.textContent = '✗ ' + (e.message || 'Chyba parseru'); }
    } finally {
      state.parsingPDF = false;
    }
  }

  // ── FS change ──────────────────────────────────────────────────────────────
  function _fsChange(key, val) {
    if (!state.product) return;
    const fsObj = _activeFsObject();
    if (!fsObj) return;
    val = Math.max(6, Math.min(90, val));
    fsObj[key] = val;
    // Aktualizuj inline zobrazení
    const el = g('fsv_' + key);
    if (el) el.textContent = Math.round(val * 10) / 10;
    const slider = g('fsr_' + key);
    if (slider) slider.value = val;
    update();
  }

  // ── Supplier / Lang ────────────────────────────────────────────────────────
  async function setSupplier(id) {
    state.supplierId = parseInt(id);
    await loadStacks();
  }

  async function switchLang(lang) {
    if (lang === state.lang) return;
    clearTimeout(saveTimer);
    if (state.lang === 'cs') { readForm(); await saveProduct(); }
    else if (state.translation) { readForm(); await saveTranslation(); }
    state.lang = lang;
    state.translation = null;
    if (lang !== 'cs') await _loadTranslationForActiveLang();
    updateLangTabs();
    loadForm();
    update();
  }

  function setLang(lang) { switchLang(lang); }

  async function _loadTranslationForActiveLang() {
    if (!state.product || state.lang === 'cs') return;
    try {
      const tr = await api(
        `api/translations.php?action=get&product_id=${state.product.id}&lang=${state.lang}`
      );
      state.translation = (tr && Object.keys(tr).length > 0) ? tr : null;
    } catch(e) {
      state.translation = null;
      toast('Chyba načtení překladu: ' + e.message, 'err');
    }
  }

  async function _loadExistingLangs() {
    if (!state.product || !state.product.id) { state.existingLangs = []; return; }
    try {
      const rows = await api(`api/translations.php?action=all_langs&product_id=${state.product.id}`);
      state.existingLangs = rows.map(r => r.lang);
    } catch(e) {
      state.existingLangs = [];
    }
  }

  function updateLangTabs() {
    document.querySelectorAll('.lang-tab').forEach(btn => {
      const l = btn.dataset.lang;
      btn.classList.toggle('active', l === state.lang);
      btn.classList.toggle('has-translation', l === 'cs' || state.existingLangs.includes(l));
    });
    const bar = g('trans-missing-bar');
    if (bar) bar.style.display = (state.lang !== 'cs' && !state.translation) ? '' : 'none';
  }

  // ── Public selectory (volané z HTML) ───────────────────────────────────────
  async function _selectStackById(id) {
    const st = state.stacks.find(s => s.id === id);
    if (st) await selectStack(st);
  }

  async function _selectProductById(id) {
    const p = state.products.find(p => p.id === id);
    if (p) await selectProduct(p);
  }

  // ── Init ───────────────────────────────────────────────────────────────────
  async function init() {
    // Zabrání prohlížeči otevřít soubor při přetažení mimo drop zónu
    ['dragover','drop'].forEach(evt => document.addEventListener(evt, e => e.preventDefault()));

    // Drag & drop pro SKU import
    const drop = g('sku-drop');
    if (drop) {
      drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor = 'var(--gold)'; });
      drop.addEventListener('dragleave', () => drop.style.borderColor = '#2a2a2a');
      drop.addEventListener('drop', e => {
        e.preventDefault(); drop.style.borderColor = '#2a2a2a';
        const f = Array.from(e.dataTransfer.files).find(f => f.name.match(/\.xlsx?$/i));
        if (f) importSKU(f);
      });
    }

    // Drag & drop pro PDF parser
    const pdfDrop = g('pdf-drop');
    if (pdfDrop) {
      pdfDrop.addEventListener('dragover', e => { e.preventDefault(); pdfDrop.style.borderColor = 'var(--gold)'; });
      pdfDrop.addEventListener('dragleave', () => pdfDrop.style.borderColor = '#2a2a2a');
      pdfDrop.addEventListener('drop', e => {
        e.preventDefault(); pdfDrop.style.borderColor = '#2a2a2a';
        const f = Array.from(e.dataTransfer.files).find(f => f.name.match(/\.pdf$/i));
        if (f) parseLabelPDF(f);
      });
    }
    // Zoom slider
    const zr = g('zoom-range');
    if (zr) setZoom(zr.value);

    await loadStacks();
    await _loadExistingLangs();
    updateLangTabs();
    toast('Label System v2 připraven', 'ok', 2000);
  }

  // Spusť po načtení DOM
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // ── Public API ─────────────────────────────────────────────────────────────
  return {
    update, setIngMode, setZoom, setSupplier, setLang, switchLang, updateLangTabs,
    exportSVG, exportPNG, exportPDF,
    createStack, createProduct, deleteProduct,
    moveUp, moveDown, moveToStack,
    buildCatalog, importSKU, aiTranslate, parseLabelPDF,
    _selectStackById, _selectProductById, _copyFromCatalog, _fsChange,
  };
})();
