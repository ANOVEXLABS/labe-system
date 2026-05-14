/**
 * ANOVEX Label System v2 — SVG Renderer
 * Verze 3.0 — doslovná kopie rendereru z původního HTML systému,
 * jen vstupy z DB (parametry product/stack/P) místo STACKS/curStack a gv().
 *
 * Strom života je nahrazen za <image href="/assets/svg/anovex_logo_{color}.svg">
 */

// ── Základní zákonné věty (EU 1169/2011) — přidávány automaticky ──
const BASE_WARNINGS = [
  'Nepřekračujte doporučené denní dávkování.',
  'Není náhradou pestré a vyvážené stravy.',
  'Uchovávejte mimo dosah dětí.'
];
const BASE_STORAGE = 'uzavřené na suchém, chladném a před světlem chráněném místě.';

// ── Helpers (z původního HTML) ──
function esc(t) {
  return String(t || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function wrap(text, max) {
  const words = String(text || '').split(' '), lines = []; let cur = '';
  for (const w of words) {
    if ((cur + ' ' + w).trim().length > max) { if (cur) lines.push(cur); cur = w; }
    else cur = cur ? cur + ' ' + w : w;
  }
  if (cur) lines.push(cur);
  return lines.length ? lines : [''];
}
function txt(x, y, text, fs, fill, weight, anchor, ls, font) {
  if (!text) return '';
  return `<text x="${x}" y="${y}" text-anchor="${anchor || 'start'}" font-family="${font || 'Montserrat,Arial,sans-serif'}" font-weight="${weight || 400}" font-size="${fs}" fill="${fill}" ${ls ? `letter-spacing="${ls}"` : ''}>${esc(text)}</text>`;
}
function line(x1, y1, x2, y2, stroke, sw, op) {
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="${sw || 0.5}" ${op ? `opacity="${op}"` : ''}/>`;
}
function rect(x, y, w, h, fill, rx, op) {
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" ${rx ? `rx="${rx}"` : ''}  fill="${fill}" ${op ? `opacity="${op}"` : ''}/>`;
}
function fitFS(text, maxW, startFS, minFS, uppercase) {
  const ratio = uppercase ? 0.72 : 0.58;
  let fs = startFS;
  while (fs > minFS && text.length * fs * ratio > maxW) fs--;
  return fs;
}
function fitFSWrapped(text, maxW, startFS, minFS, uppercase) {
  const ratio = uppercase ? 0.72 : 0.58;
  let fs = startFS;
  while (fs > minFS) {
    const cpl = Math.floor(maxW / (fs * ratio));
    const lines = wrap(text, cpl);
    const longest = lines.reduce((a, b) => b.length > a.length ? b : a, '');
    if (longest.length * fs * ratio <= maxW) break;
    fs--;
  }
  return fs;
}

// ── EU 1169/2011 — minimální velikost písma ──
// < 80 cm² → x-výška 0,9 mm; jinak → 1,2 mm; x-výška ≈ 52 % velikosti písma při 300 dpi
function legalMinFS(P) {
  const mmW = Math.round(P.VW * 25.4 / 300);
  const mmH = Math.round(P.VH * 25.4 / 300);
  const cm2  = (mmW * mmH) / 100;
  const xHeightMm = cm2 < 80 ? 0.9 : 1.2;
  return Math.ceil(xHeightMm * 300 / 25.4 / 0.52);
}

// ── Color helpers ──
function hexToRgb(hex) {
  const h = String(hex || '').replace('#', '').trim();
  if (h.length === 3) return { r: parseInt(h[0] + h[0], 16), g: parseInt(h[1] + h[1], 16), b: parseInt(h[2] + h[2], 16) };
  return { r: parseInt(h.substr(0, 2), 16), g: parseInt(h.substr(2, 2), 16), b: parseInt(h.substr(4, 2), 16) };
}
function rgbToHex(r, g, b) {
  const h = n => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
  return '#' + h(r) + h(g) + h(b);
}
function mixHex(hex1, hex2, ratio) {
  const a = hexToRgb(hex1), b = hexToRgb(hex2);
  return rgbToHex(a.r + (b.r - a.r) * ratio, a.g + (b.g - a.g) * ratio, a.b + (b.b - a.b) * ratio);
}
function rgbaFromHex(hex, alpha) {
  const c = hexToRgb(hex);
  return `rgba(${c.r},${c.g},${c.b},${alpha})`;
}
function getPrintPalette(stack) {
  const base = (stack && stack.accent) ? stack.accent : '#e0c97a';
  const isSelect = stack && stack.series === 'select';
  return {
    base,
    primary:   isSelect ? mixHex(base, '#ffffff', 0.10) : mixHex(base, '#ffffff', 0.12),
    secondary: isSelect ? mixHex(base, '#ffffff', 0.22) : mixHex(base, '#ffffff', 0.40),
    neutral:   'rgba(255,255,255,0.82)',
    line:      isSelect ? rgbaFromHex(mixHex(base, '#ffffff', 0.18), 0.32) : rgbaFromHex(mixHex(base, '#ffffff', 0.34), 0.34)
  };
}

// ── SVG strom — přesná kopie z původního HTML (vyladěná verze) ──
function treeSVG(cx, by, s, col, _logoColor) {
  const c = col;
  return `
  <line x1="${cx}" y1="${by}" x2="${cx}" y2="${by+25*s}" stroke="${c}" stroke-width="${2.5*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by+17*s}" x2="${cx-24*s}" y2="${by+30*s}" stroke="${c}" stroke-width="${1.8*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by+17*s}" x2="${cx+24*s}" y2="${by+30*s}" stroke="${c}" stroke-width="${1.8*s}" stroke-linecap="round"/>
  <line x1="${cx-16*s}" y1="${by+25*s}" x2="${cx-30*s}" y2="${by+32*s}" stroke="${c}" stroke-width="${1.2*s}" stroke-linecap="round" opacity="0.6"/>
  <line x1="${cx+16*s}" y1="${by+25*s}" x2="${cx+30*s}" y2="${by+32*s}" stroke="${c}" stroke-width="${1.2*s}" stroke-linecap="round" opacity="0.6"/>
  <line x1="${cx}" y1="${by-82*s}" x2="${cx}" y2="${by}" stroke="${c}" stroke-width="${3*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by-20*s}" x2="${cx-32*s}" y2="${by-40*s}" stroke="${c}" stroke-width="${2.2*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by-20*s}" x2="${cx+32*s}" y2="${by-40*s}" stroke="${c}" stroke-width="${2.2*s}" stroke-linecap="round"/>
  <circle cx="${cx-40*s}" cy="${by-47*s}" r="${7*s}" fill="${c}"/>
  <circle cx="${cx+40*s}" cy="${by-47*s}" r="${7*s}" fill="${c}"/>
  <line x1="${cx}" y1="${by-40*s}" x2="${cx-30*s}" y2="${by-64*s}" stroke="${c}" stroke-width="${2*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by-40*s}" x2="${cx+30*s}" y2="${by-64*s}" stroke="${c}" stroke-width="${2*s}" stroke-linecap="round"/>
  <line x1="${cx-30*s}" y1="${by-52*s}" x2="${cx-52*s}" y2="${by-68*s}" stroke="${c}" stroke-width="${1.5*s}" stroke-linecap="round"/>
  <line x1="${cx+30*s}" y1="${by-52*s}" x2="${cx+52*s}" y2="${by-68*s}" stroke="${c}" stroke-width="${1.5*s}" stroke-linecap="round"/>
  <circle cx="${cx-37*s}" cy="${by-72*s}" r="${6*s}" fill="${c}"/>
  <circle cx="${cx+37*s}" cy="${by-72*s}" r="${6*s}" fill="${c}"/>
  <circle cx="${cx-59*s}" cy="${by-75*s}" r="${5.5*s}" fill="${c}" opacity="0.85"/>
  <circle cx="${cx+59*s}" cy="${by-75*s}" r="${5.5*s}" fill="${c}" opacity="0.85"/>
  <line x1="${cx}" y1="${by-62*s}" x2="${cx-22*s}" y2="${by-80*s}" stroke="${c}" stroke-width="${1.8*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by-62*s}" x2="${cx+22*s}" y2="${by-80*s}" stroke="${c}" stroke-width="${1.8*s}" stroke-linecap="round"/>
  <line x1="${cx-22*s}" y1="${by-72*s}" x2="${cx-42*s}" y2="${by-88*s}" stroke="${c}" stroke-width="${1.3*s}" stroke-linecap="round"/>
  <line x1="${cx+22*s}" y1="${by-72*s}" x2="${cx+42*s}" y2="${by-88*s}" stroke="${c}" stroke-width="${1.3*s}" stroke-linecap="round"/>
  <line x1="${cx}" y1="${by-82*s}" x2="${cx}" y2="${by-100*s}" stroke="${c}" stroke-width="${1.8*s}" stroke-linecap="round"/>
  <circle cx="${cx-28*s}" cy="${by-86*s}" r="${5*s}" fill="${c}"/>
  <circle cx="${cx+28*s}" cy="${by-86*s}" r="${5*s}" fill="${c}"/>
  <circle cx="${cx-49*s}" cy="${by-94*s}" r="${4.5*s}" fill="${c}" opacity="0.8"/>
  <circle cx="${cx+49*s}" cy="${by-94*s}" r="${4.5*s}" fill="${c}" opacity="0.8"/>
  <circle cx="${cx}" cy="${by-108*s}" r="${8*s}" fill="${c}"/>`;
}

// ── EAN-13 Barcode (z původního HTML) ──
function buildBarcode(ean, x, y, w, h, col) {
  const raw = String(ean || '').replace(/\D/g, '');
  let full = '';
  if (raw.length >= 13) {
    full = raw.substring(0, 13);
    const base = full.substring(0, 12);
    let s = 0; for (let i = 0; i < 12; i++) s += parseInt(base[i], 10) * (i % 2 === 0 ? 1 : 3);
    const check = (10 - (s % 10)) % 10;
    if (String(check) !== full[12]) full = base + check;
  } else if (raw.length === 12) {
    let s = 0; for (let i = 0; i < 12; i++) s += parseInt(raw[i], 10) * (i % 2 === 0 ? 1 : 3);
    const check = (10 - (s % 10)) % 10;
    full = raw + check;
  } else {
    return '';
  }
  const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
  const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
  const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
  const par = [[L, L, L, L, L, L], [L, L, G, L, G, G], [L, L, G, G, L, G], [L, L, G, G, G, L], [L, G, L, L, G, G], [L, G, G, L, L, G], [L, G, G, G, L, L], [L, G, L, G, L, G], [L, G, L, G, G, L], [L, G, G, L, G, L]];
  const row = par[parseInt(full[0], 10)];
  let bits = '101';
  for (let i = 1; i <= 6; i++) bits += row[i - 1][parseInt(full[i], 10)];
  bits += '01010';
  for (let i = 7; i <= 12; i++) bits += R[parseInt(full[i], 10)];
  bits += '101';
  const quiet = 8;
  const innerW = w - quiet * 2;
  const moduleW = innerW / 95;
  const digitFS = Math.max(7, h * 0.18);
  const barH = h * 0.72;
  const numY = y + h * 0.96;
  let svg = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="1" fill="#ffffff"/>`;
  for (let i = 0; i < 95; i++) {
    if (bits[i] === '1') {
      const guard = (i < 3) || (i >= 45 && i <= 49) || (i >= 92);
      const bh = guard ? barH + h * 0.08 : barH;
      const bx = x + quiet + i * moduleW;
      svg += `<rect x="${bx.toFixed(2)}" y="${(y + 1).toFixed(2)}" width="${Math.max(0.72, moduleW).toFixed(2)}" height="${bh.toFixed(2)}" fill="${col || '#000'}"/>`;
    }
  }
  const digitFont = 'Arial,Helvetica,sans-serif';
  svg += `<text x="${(x + quiet / 2).toFixed(1)}" y="${numY.toFixed(1)}" text-anchor="middle" font-family="${digitFont}" font-size="${digitFS}" fill="#000">${full[0]}</text>`;
  for (let i = 1; i <= 6; i++) {
    const nx = x + quiet + 3 * moduleW + (i - 1) * 7 * moduleW + 3.5 * moduleW;
    svg += `<text x="${nx.toFixed(1)}" y="${numY.toFixed(1)}" text-anchor="middle" font-family="${digitFont}" font-size="${digitFS}" fill="#000">${full[i]}</text>`;
  }
  for (let i = 7; i <= 12; i++) {
    const nx = x + quiet + 50 * moduleW + (i - 7) * 7 * moduleW + 3.5 * moduleW;
    svg += `<text x="${nx.toFixed(1)}" y="${numY.toFixed(1)}" text-anchor="middle" font-family="${digitFont}" font-size="${digitFS}" fill="#000">${full[i]}</text>`;
  }
  return svg;
}

// ════════════════════════════════════════════════════════════════
// HLAVNÍ buildLabel — adaptováno z původního HTML pro DB systém
// ════════════════════════════════════════════════════════════════
function buildLabel(product, stack, P, distributor, lang) {
  if (!product || !stack || !P) {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 300"><rect width="800" height="300" fill="#0f0d08"/><text x="400" y="150" text-anchor="middle" fill="rgba(255,255,255,0.4)" font-size="22" font-family="Arial">Vyber produkt</text></svg>`;
  }

  const { VW, VH, LX1, LX2, CX1, CX2, RX1, RX2, sep1, sep2 } = P;
  const bg = stack.bg || '#0f0d08';
  const palette = getPrintPalette(stack);
  const ac = palette.primary;
  const isSel = stack.series === 'select';
  const isPrem = stack.series === 'premium';
  const logoColor = stack.logo_color || 'gold';

  // Translation overlay
  const tr = (lang && lang !== 'cs' && product._translation) ? product._translation : {};

  // v = form values (mapping dat z DB na tvar v který očekává původní renderer)
  const v = {
    sen:     tr.name      || stack.name      || '',                    // Název stacku (anglicky/CZ)
    ssub:    stack.sub     || '',                                       // Podtitul stacku ("dlouhověkost")
    bname:   tr.name       || product.name      || '',                  // Název látky
    sub:     tr.sub        || product.sub       || '',                  // Role látky
    count:   tr.count      || product.count     || '',                  // Počet (90 kapslí)
    num:                      product.num       || '',                  // Pořadí (1 / 4)
    nfull:                    product.name_full || '',
    net:     tr.obsah_baleni || product.net     || product.obsah_baleni || '',
    dosage:  tr.davkovani  || product.davkovani || '',
    w4:      tr.upozorneni || product.upozorneni || '',                 // Specifická upozornění (bez základních)
    storage: tr.skladovani || product.skladovani || '',                 // Specifické uchovávání (bez základní věty)
    ean:                      product.ean       || '',
    serv:    tr.serv       || product.serv      || '',
    slozeni: tr.slozeni    || product.slozeni   || '',
    f1:                       (product.feats || [])[0] || '',
    f2:                       (product.feats || [])[1] || '',
    f3:                       (product.feats || [])[2] || '',
    f4:                       (product.feats || [])[3] || '',
  };

  const rawIngs = (lang !== 'cs' && tr.ings) ? tr.ings : (product.ings || []);
  const ingsArr = Array.isArray(rawIngs) ? rawIngs.map(r => Array.isArray(r) ? r : [r, '', '/']) : [];

  if (P && P.isPortrait) return buildPortraitLabel(product, stack, P, distributor, lang, v, ingsArr);

  const bOff = LX1, bSW = 4;  // LX1 = buildPreset bOff, proportional to label size
  const pad = 8;
  const yTop = bOff + pad;
  const yBot = VH - bOff - pad;
  const L = { x1: LX1 + pad, x2: LX2 - pad, y1: yTop, y2: yBot, w: LX2 - LX1 - pad * 2 };
  const C = { x1: CX1 + pad, x2: CX2 - pad, y1: yTop, y2: yBot, w: CX2 - CX1 - pad * 2 };
  const R = { x1: RX1 + pad, x2: RX2 - pad, y1: yTop, y2: yBot, w: RX2 - RX1 - pad * 2 };

  let svg = `<svg width="100%" viewBox="0 0 ${VW} ${VH}" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">`;

  svg += `<defs>
    <clipPath id="clipL"><rect x="${LX1}" y="${bOff}" width="${LX2 - LX1}" height="${VH - bOff * 2}"/></clipPath>
    <clipPath id="clipC"><rect x="${CX1}" y="${bOff}" width="${CX2 - CX1}" height="${VH - bOff * 2}"/></clipPath>
    <clipPath id="clipR"><rect x="${RX1}" y="${bOff}" width="${RX2 - RX1}" height="${VH - bOff * 2}"/></clipPath>
  </defs>`;

  svg += rect(0, 0, VW, VH, bg);
  svg += `<rect x="${bOff}" y="${bOff}" width="${VW - bOff * 2}" height="${VH - bOff * 2}" fill="none" stroke="${ac}" stroke-width="${bSW}"/>`;
  svg += rect(LX1, bOff, LX2 - LX1, VH - bOff * 2, '#ffffff', 0, 0.02);
  svg += rect(CX1, bOff, CX2 - CX1, VH - bOff * 2, '#ffffff', 0, 0.01);
  svg += `<line x1="${sep1}" y1="${bOff + 2}" x2="${sep1}" y2="${VH - bOff - 2}" stroke="${ac}" stroke-width="1.12" opacity="0.25"/>`;
  svg += `<line x1="${sep2}" y1="${bOff + 2}" x2="${sep2}" y2="${VH - bOff - 2}" stroke="${ac}" stroke-width="1.12" opacity="0.25"/>`;

  const fs = product.fs || {};
  svg += `<g clip-path="url(#clipL)">${buildLeftZone(P, L, v, ac, isSel, fs)}</g>`;
  svg += `<g clip-path="url(#clipC)">${buildCenterZone(P, C, v, ac, isSel, isPrem, palette, fs, logoColor)}</g>`;
  svg += `<g clip-path="url(#clipR)">${buildRightZone(P, R, v, ac, ingsArr, fs, product, distributor)}</g>`;

  svg += '</svg>';
  return svg;
}

// ── LEFT ZONE — kopie z původního HTML, jen base warnings/storage automaticky ──
function buildLeftZone(P, Z, v, ac, isSel, _fs) {
  const { F } = P;
  const { x1, x2, y1, y2, w } = Z;
  const wh = '#ffffff';
  const red = '#d06060';
  const techFont = 'Arial,Helvetica,sans-serif';

  const lMin   = legalMinFS(P);
  const fs     = _fs.dosage !== undefined ? Math.max(lMin, _fs.dosage) : (F.min + 2);
  const metaFS = _fs.meta   !== undefined ? Math.max(lMin, _fs.meta)   : (F.min + 2);
  const lh     = Math.ceil(fs * 1.1);
  const cpl    = Math.floor(w / (fs * 0.52));
  const bH     = Math.round((y2 - y1) * 0.18);
  const bY     = y2 - bH;
  const zH     = y2 - y1;
  const sp     = Math.min(4, Math.max(1, Math.round(zH / 180)));

  // Zákonné věty + specifické navíc
  const specificWarns = (v.w4 || '').split('\n').map(l => l.trim()).filter(Boolean);
  const allWarns = [...BASE_WARNINGS, ...specificWarns];
  const fullWarnText = allWarns.join(' ');

  // Základní storage + případný doplněk
  // Odfiltruj základní větu pokud ji uživatel/parser vložil do pole
  let specificStorage = (v.storage || '').trim();
  // Odstraň BASE_STORAGE a synonymní varianty (uzavřené/suché/chladné/světlo)
  const baseStorageNorm = BASE_STORAGE.toLowerCase().replace(/[.,]/g, '');
  specificStorage = specificStorage.split(/[.!]\s*/).filter(s => {
    const norm = s.trim().toLowerCase().replace(/[.,]/g, '');
    if (!norm) return false;
    if (baseStorageNorm.includes(norm) || norm.includes(baseStorageNorm.substring(0, 25))) return false;
    // Filtruj věty které popisují totéž jinými slovy (uzavřen + suché|chladn|světl)
    const isBaseVariant = /uzavřen/i.test(norm) && (/such[eéý]/i.test(norm) || /chladn/i.test(norm) || /světl/i.test(norm) || /licht/i.test(norm) || /trocken/i.test(norm));
    return !isBaseVariant;
  }).join('. ').trim();
  if (specificStorage && !specificStorage.endsWith('.')) specificStorage += '.';
  const fullStorageText = specificStorage ? BASE_STORAGE + ' ' + specificStorage : BASE_STORAGE;

  function renderBlock(startY, label, bodyText, maxY2, labelColor) {
    const labelPx  = label.length * fs * 0.65;  // bold uppercase je širší než průměrný znak
    const firstCPL = Math.max(5, Math.floor((w - labelPx) / (fs * 0.52)));
    const words = bodyText.split(' ').filter(Boolean);
    let firstLine = '', rest = [], done = false;
    words.forEach(wd => {
      if (!done) { const t = firstLine ? firstLine + ' ' + wd : wd; if (t.length <= firstCPL) firstLine = t; else { done = true; rest.push(wd); } }
      else rest.push(wd);
    });
    const restLines = wrap(rest.join(' '), cpl);
    const totalLines = 1 + restLines.length;
    let out = '', y2l = startY;
    if (y2l < maxY2) {
      out += `<text x="${x1}" y="${y2l + fs}" font-family="${techFont}" font-size="${fs}" fill="${wh}"><tspan font-weight="700" fill="${labelColor || wh}">${esc(label)}</tspan><tspan font-weight="400"> ${esc(firstLine)}</tspan></text>`;
    }
    restLines.forEach(l => {
      y2l += lh;
      if (y2l < maxY2) { out += `<text x="${x1}" y="${y2l + fs}" font-family="${techFont}" font-size="${fs}" font-weight="400" fill="${wh}">${esc(l)}</text>`; }
    });
    return { s: out, y: startY + (totalLines - 1) * lh + lh };
  }

  let s = '', y = y1 + 2;
  const metaY = bY - (metaFS * 2 + 14);
  const maxY = metaY - 4;

  s += `<text x="${x1}" y="${y + fs}" font-family="${techFont}" font-size="${fs}" font-weight="700" fill="${wh}">${esc('DOPLNĚK STRAVY')}</text>`;
  y += lh;

  { const r = renderBlock(y, 'DÁVKOVÁNÍ:', (v.dosage || '').trim(), maxY); s += r.s; y = r.y; }
  { const r = renderBlock(y, 'UPOZORNĚNÍ:', fullWarnText, maxY, red); s += r.s; y = r.y; }

  if (y < maxY) {
    y += sp;
    const r = renderBlock(y, 'UCHOVÁVÁNÍ:', fullStorageText, maxY); s += r.s; y = r.y;
  }

  s += line(x1, metaY - 4, x2, metaY - 4, ac, 0.5, 0.18);
  if (v.net) s += `<text x="${x1}" y="${metaY + metaFS}" font-family="${techFont}" font-size="${metaFS}" font-weight="700" fill="${wh}">Obsah: ${esc(v.net)}</text>`;
  s += `<text x="${x1}" y="${metaY + metaFS * 2 + 3}" font-family="${techFont}" font-size="${metaFS}" font-weight="400" fill="${wh}">Č. šarže / Min. trvanlivost: viz. obal</text>`;
  if (v.ean) s += buildBarcode(v.ean, x1, bY, w, bH, '#000000');
  return s;
}

// ── CENTER ZONE — kopie z původního HTML, jen treeSVG vrací <image> ──
function buildCenterZone(P, Z, v, ac, isSel, isPrem, palette, _fs, logoColor) {
  const { F } = P;
  const { x1, x2, y1, y2, w } = Z;
  const cx = x1 + w / 2;
  const zH = y2 - y1;
  const wh = '#ffffff';
  const acP = (palette && palette.primary) || ac;
  const acS = (palette && palette.secondary) || ac;
  const neutral = (palette && palette.neutral) || 'rgba(255,255,255,0.82)';
  const lineTone = (palette && palette.line) || acS;
  const fsOvr = _fs || {};
  let s = '';

  // Radial glow za stromem
  const glowId = `glow_${Math.random().toString(36).slice(2, 7)}`;
  s += `<defs>
    <radialGradient id="${glowId}" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="${acS}" stop-opacity="0.22"/>
      <stop offset="55%" stop-color="${acS}" stop-opacity="0.07"/>
      <stop offset="100%" stop-color="${ac}" stop-opacity="0"/>
    </radialGradient>
  </defs>`;

  // ── Horní kotva (shora dolů) — přesně z původního HTML ──
  const snFSmax = w > 650 ? F.big : F.lg + 4;
  const snFS = fsOvr.ssub !== undefined ? fsOvr.ssub
             : fitFS((v.ssub || '').toUpperCase(), w - 16, snFSmax, F.md, true);

  const yAnovex    = y1 + zH * 0.11;
  const yDiamond   = yAnovex + F.sm + 4;
  const ySubtitle  = yDiamond + snFS + 10;
  const treeCenter = y1 + zH * 0.44;
  const treeGlowR  = w * 0.40;
  const ySeriesEN  = y1 + zH * 0.600;

  // Glow — SELECT větev překryje správnou pozicí, non-select zachová treeCenter
  if (!isSel) s += `<ellipse cx="${cx}" cy="${treeCenter}" rx="${treeGlowR}" ry="${treeGlowR * 0.85}" fill="url(#${glowId})"/>`;

  // A N O V E X
  s += txt(cx, yAnovex, 'A N O V E X', F.xl, acP, 900, 'middle', 6);

  if (!isSel) {
    // Diamant
    s += txt(cx, yDiamond, '♦', F.sm, acP, 400, 'middle');

    // Podtitul stacku (DLOUHOVĚKOST) — 1 řádek, auto-fit
    s += txt(cx, ySubtitle, (v.ssub || '').toUpperCase(), snFS, wh, 900, 'middle', 0.5);

    // Strom
    const ts = zH < 350 ? 0.39 : zH < 500 ? 0.50 : 0.61;
    s += treeSVG(cx, treeCenter, ts, acS, logoColor);

    // Linka + Series EN (LONGEVITY BASE)
    s += line(cx - w * 0.28, ySeriesEN - F.sm - 6, cx + w * 0.28, ySeriesEN - F.sm - 6, lineTone, 0.8, 1);
    s += txt(cx, ySeriesEN, (v.sen || '').toUpperCase(), F.sm, acS, 700, 'middle', 3.5);

    // ── Spodní kotva (zdola nahoru) — přesně z původního HTML ──
    const premY  = y2 - 4;
    const lineY  = premY - F.sm - 6;
    const countFS = fsOvr.count !== undefined ? fsOvr.count : F.md;
    const countY = lineY - 8;
    const subFS  = fsOvr.sub !== undefined ? fsOvr.sub : F.sm;
    const subY   = countY - countFS - 10;

    // Název látky — auto-fit, max 2 řádky, centrovaný mezi ySeriesEN a subY
    const bnAvailW = w - 8;
    const bnFSmax = F.xl + 4;
    let bnFS, bnL;
    if (fsOvr.bname !== undefined) {
      bnFS = fsOvr.bname;
      const bnCPL = Math.floor(bnAvailW / (bnFS * 0.72));
      bnL = wrap((v.bname || '').toUpperCase(), bnCPL);
    } else {
      const hasSpace = (v.bname || '').includes(' ');
      const bn1FS = fitFS((v.bname || '').toUpperCase(), bnAvailW, bnFSmax, F.md, true);
      const bn2FS = hasSpace ? fitFSWrapped((v.bname || '').toUpperCase(), bnAvailW, bnFSmax, F.md, true) : bn1FS;
      bnFS = (hasSpace && bn2FS > bn1FS) ? bn2FS : bn1FS;
      const bnCPL = Math.floor(bnAvailW / (bnFS * 0.72));
      bnL = wrap((v.bname || '').toUpperCase(), bnCPL);
    }

    // Střed názvu — 42% vzdálenosti mezi seriesEN a subY (přesně z originálu)
    const bnMidY = ySeriesEN + 8 + (subY - ySeriesEN - 8) * 0.42;
    const bnTotalH = bnL.length * bnFS + (bnL.length - 1) * 5;
    bnL.forEach((l, i) => {
      const lY = bnMidY - bnTotalH / 2 + i * (bnFS + 5) + bnFS / 2;
      s += `<text x="${cx}" y="${lY}" text-anchor="middle" dominant-baseline="middle" font-family="Montserrat,Arial,sans-serif" font-weight="900" font-size="${bnFS}" fill="${wh}" letter-spacing="0.5">${esc(l)}</text>`;
    });

    // Podnadpis (200 mg / klouby ♦ prevence zánětů)
    if (v.sub) s += txt(cx, subY, v.sub, subFS, neutral, 400, 'middle', 0.5);

    // Počet (60 kapslí)
    s += txt(cx, countY, v.count || '', countFS, wh, 600, 'middle');

    // Linka
    s += line(cx - w * 0.3, lineY, cx + w * 0.3, lineY, lineTone, 0.8, 1);

    // Tier (PREMIUM / FORMULA)
    const tier = isPrem ? 'P R E M I U M' : 'F O R M U L A';
    s += txt(cx, premY, tier, F.sm, acS, 700, 'middle', 3);

  } else {
    // ── SELECT layout ──
    s += txt(cx, yDiamond, '♦', F.sm, acP, 400, 'middle');

    // S E L E C T — strom těsně nad ním, feats mají dost místa dolů
    const ySelect2 = y1 + zH * 0.65;
    const tS2      = zH < 350 ? 0.46 : zH < 500 ? 0.60 : 0.76;
    // Strom: spodní okraj stromu (by + 25*s) těsně nad SELECT textem
    const treeCY2  = ySelect2 - F.sm - 12 - 25 * tS2;
    const treeTopY = treeCY2 - 116 * tS2;

    // Glow na správné pozici
    s += `<ellipse cx="${cx}" cy="${treeCY2}" rx="${treeGlowR}" ry="${treeGlowR * 0.85}" fill="url(#${glowId})"/>`;

    // Název produktu — vertikálně centrovaný mezi ♦ a vrchem stromu
    const bnF2 = fsOvr.bname !== undefined ? fsOvr.bname : fitFS((v.bname || '').toUpperCase(), w - 8, F.xl + 4, F.md, true);
    const bnL2 = wrap((v.bname || '').toUpperCase(), Math.floor((w - 8) / (bnF2 * 0.72)));
    const nameTotalH = bnL2.length * bnF2 + (bnL2.length - 1) * 4;
    const nameMidY   = (yDiamond + treeTopY) / 2;
    const nameStartY = nameMidY - nameTotalH / 2 + bnF2;
    bnL2.forEach((l, i) => { s += txt(cx, nameStartY + i * (bnF2 + 4), l, bnF2, wh, 900, 'middle', 0.5); });

    s += treeSVG(cx, treeCY2, tS2, acS, logoColor);
    s += txt(cx, ySelect2, 'S E L E C T', F.sm, acS, 700, 'middle', 4);

    // Zlatá linka a počet — linka kotvena od y2, počet centrován mezi linkou a rámečkem
    const yLine2       = y2 - F.md - 14;
    const borderInnerY = y2 + 6;  // vnitřní hrana zlatého rámečku (y2 + pad - bSW/2)
    const yCount2      = Math.round((yLine2 + borderInnerY) / 2 + F.md * 0.32);
    s += line(cx - w * 0.3, yLine2, cx + w * 0.3, yLine2, lineTone, 0.8, 1);
    s += txt(cx, yCount2, v.count || '', F.md, acS, 700, 'middle');

    // Feats — rovnoměrně rozděleny mezi SELECT a zlatou linkou
    const featFS = fsOvr.feats !== undefined ? fsOvr.feats : F.xs;
    const selItems = [v.f1, v.f2, v.f3, v.f4].filter(Boolean);
    const featsAreaTop    = ySelect2 + F.sm + 10;
    const featsAreaBottom = yLine2 - 8;
    const featsAreaH      = featsAreaBottom - featsAreaTop;
    const featStep = selItems.length > 1 ? (featsAreaH - featFS) / (selItems.length - 1) : featsAreaH / 2;
    selItems.forEach((f, i) => {
      const slotY = featsAreaTop + featStep * i + featFS * 0.85;
      const clean = f.replace(/[✓✔☑]/g, '').trim();
      s += txt(cx, slotY, clean, featFS, wh, 400, 'middle');
    });
  }

  return s;
}


// ── RIGHT ZONE — kopie z původního HTML ──
function buildRightZone(P, Z, v, ac, ingsArr, _fs2, product, distributor) {
  const { F } = P;
  const { x1, x2, y1, y2, w } = Z;
  const wh = '#ffffff';
  const techFont = 'Arial,Helvetica,sans-serif';
  const ingMode = (product && product.ing_mode) || 'table';

  // Minimální velikost písma dle EU 1169/2011 (zákonná norma)
  const lMin = legalMinFS(P);
  // Nutriční tabulka: bodyFS = zákonné minimum nebo slider; headFS = vždy stejné jako bodyFS (slider serv)
  const bodyFS = _fs2.ings !== undefined ? Math.max(lMin, _fs2.ings) : lMin;
  const headFS = _fs2.serv !== undefined ? Math.max(lMin, _fs2.serv) : bodyFS;
  const textFS = bodyFS;
  const storFS = _fs2.storage !== undefined ? Math.max(lMin, _fs2.storage) : (F.min + 2);
  const distFS = _fs2.dist    !== undefined ? Math.max(lMin, _fs2.dist)    : (F.min + 2);
  const numFS  = _fs2.num     !== undefined ? Math.max(lMin, _fs2.num)     : (F.min + 2);

  const servTextRaw = String(v.serv || '').trim();
  let s = '', y = y1 + 2;
  // maxY: obsah musí skončit před footer distributor (ten je vždy ukotven k y2)
  const footerH = distFS * 2 + 16;
  const maxY = y2 - Math.max(v.num ? numFS + 8 : 0, footerH + 10);

  const servBracket = servTextRaw.match(/\([^)]+\)/)?.[0] || '';
  s += txt(x1, y + headFS, 'Složení denní dávky' + (servBracket ? ' ' + servBracket : ''), headFS, wh, 700, 'start', 0, techFont);
  y += headFS + 4;
  s += line(x1, y, x2, y, ac, 0.5, 0.18); y += 3;

  // footFS pro %RH poznámky — zákonné minimum
  const footFS = lMin;

  if (ingMode === 'table') {
    const rowLH     = bodyFS + 1;
    const useTwoCol = (ingsArr || []).length > 20;

    if (useTwoCol) {
      // Two-column table for long ingredient lists (>20 ingredients)
      const colGap = 10;
      const colW   = Math.floor((w - colGap) / 2);
      const nRatio = 0.57;
      const lx1 = x1,            lc2 = lx1 + Math.floor(colW * nRatio), lx2 = lx1 + colW;
      const rx1 = lx2 + colGap,  rc2 = rx1 + Math.floor(colW * nRatio), rx2 = x2;
      const lnW = lc2 - lx1 - 4;
      const rnW = rc2 - rx1 - 4;

      s += txt(lx1+2, y+bodyFS, 'Složka',     bodyFS, wh, 700, 'start', 0, techFont);
      s += txt(lc2+2, y+bodyFS, 'Množ./%RH*', bodyFS, wh, 700, 'start', 0, techFont);
      s += txt(rx1+2, y+bodyFS, 'Složka',     bodyFS, wh, 700, 'start', 0, techFont);
      s += txt(rc2+2, y+bodyFS, 'Množ./%RH*', bodyFS, wh, 700, 'start', 0, techFont);
      y += bodyFS + 3;
      s += line(x1, y, x2, y, ac, 0.5, 0.13); y += 2;

      const half      = Math.ceil((ingsArr || []).length / 2);
      const leftIngs  = (ingsArr || []).slice(0, half);
      const rightIngs = (ingsArr || []).slice(half);
      const maxRows   = Math.max(leftIngs.length, rightIngs.length);

      for (let ri = 0; ri < maxRows; ri++) {
        if (y >= maxY - rowLH) break;
        let rowH = 1;
        if (ri < leftIngs.length) {
          const [nm, am, rh] = leftIngs[ri];
          const nL = wrap(String(nm||'').trim(), Math.max(4, Math.floor(lnW / (bodyFS * 0.50))));
          rowH = Math.max(rowH, nL.length);
          nL.forEach((l, i) => s += txt(lx1+2, y+bodyFS+i*rowLH, l, bodyFS, wh, 400, 'start', 0, techFont));
          const amStr = [am&&am.trim(), rh&&rh.trim()&&rh.trim()!=='/'?rh.trim():''].filter(Boolean).join(' ');
          if (amStr) s += txt(lc2+2, y+bodyFS, amStr, bodyFS, wh, 400, 'start', 0, techFont);
        }
        if (ri < rightIngs.length) {
          const [nm, am, rh] = rightIngs[ri];
          const nL = wrap(String(nm||'').trim(), Math.max(4, Math.floor(rnW / (bodyFS * 0.50))));
          rowH = Math.max(rowH, nL.length);
          nL.forEach((l, i) => s += txt(rx1+2, y+bodyFS+i*rowLH, l, bodyFS, wh, 400, 'start', 0, techFont));
          const amStr = [am&&am.trim(), rh&&rh.trim()&&rh.trim()!=='/'?rh.trim():''].filter(Boolean).join(' ');
          if (amStr) s += txt(rc2+2, y+bodyFS, amStr, bodyFS, wh, 400, 'start', 0, techFont);
        }
        y += rowH * rowLH + 1;
        s += line(x1, y, x2, y, ac, 0.4, 0.10); y += 1;
      }

    } else {
      // Single-column ingredient table
      const c2 = x1 + w * 0.62;
      const c3 = x1 + w * 0.88;
      s += txt(x1 + 2, y + bodyFS, 'Složka',   bodyFS, wh, 700, 'start', 0, techFont);
      s += txt(c2 + 2, y + bodyFS, 'Množství', bodyFS, wh, 700, 'start', 0, techFont);
      s += txt(x2 - 2, y + bodyFS, '%RH*',    bodyFS, wh, 700, 'end',   0, techFont);
      y += bodyFS + 3;
      s += line(x1, y, x2, y, ac, 0.5, 0.13); y += 2;
      const nameW   = c2 - x1 - 6;
      const amountW = c3 - c2 - 6;
      (ingsArr || []).forEach(ing => {
        if (y >= maxY - rowLH) return;
        const [nm, am, rh] = ing;
        const nameLines   = wrap(String(nm || '').trim(), Math.max(6, Math.floor(nameW   / (bodyFS * 0.50))));
        const amountLines = wrap(String(am || '').trim(), Math.max(4, Math.floor(amountW / (bodyFS * 0.50))));
        const linesCount  = Math.max(nameLines.length, amountLines.length, 1);
        for (let i = 0; i < linesCount; i++) {
          if (i < nameLines.length)   s += txt(x1 + 2, y + bodyFS + i * rowLH, nameLines[i],   bodyFS, wh, 400, 'start', 0, techFont);
          if (i < amountLines.length) s += txt(c2 + 2, y + bodyFS + i * rowLH, amountLines[i], bodyFS, wh, 400, 'start', 0, techFont);
          if (i === 0) s += txt(x2 - 2, y + bodyFS, String(rh || '/'), bodyFS, wh, 400, 'end', 0, techFont);
        }
        y += linesCount * rowLH + 1;
        s += line(x1, y, x2, y, ac, 0.4, 0.12); y += 1;
      });
    }

    wrap('*% RH = % referenční hodnoty příjmu dle přílohy XIII nař. (EU) č. 1169/2011', Math.max(8, Math.floor(w / (footFS * 0.50)))).forEach(l => {
      if (y < maxY) { s += txt(x1, y + footFS, l, footFS, wh, 400, 'start', 0, techFont); y += footFS + 1; }
    });
    if (y < maxY) { s += txt(x1, y + footFS, '/ = RH není stanovena', footFS, wh, 400, 'start', 0, techFont); y += footFS + 4; }
    s += line(x1, y, x2, y, ac, 0.5, 0.15); y += 4;

  } else {
    const rowLH = bodyFS + 1;
    const cpl   = Math.max(10, Math.floor(w / (bodyFS * 0.50)));
    const ingText = (ingsArr || []).map(([nm, am, rh]) => {
      let t = String(nm || '').trim();
      if (am && am.trim()) t += ' ' + am.trim();
      if (rh && rh.trim() && rh.trim() !== '/') t += ' (' + rh.trim() + ')';
      return t;
    }).filter(Boolean).join(', ');
    wrap(ingText, cpl).forEach(l => {
      if (y < maxY) { s += txt(x1, y + bodyFS, l, bodyFS, wh, 400, 'start', 0, techFont); y += rowLH; }
    });
    wrap('*% RH = % referenční hodnoty příjmu dle přílohy XIII nař. (EU) č. 1169/2011', Math.max(8, Math.floor(w / (footFS * 0.50)))).forEach(l => {
      if (y < maxY) { s += txt(x1, y + footFS, l, footFS, wh, 400, 'start', 0, techFont); y += footFS + 1; }
    });
    if (y < maxY) { s += txt(x1, y + footFS, '/ = RH není stanovena', footFS, wh, 400, 'start', 0, techFont); y += footFS + 4; }
    s += line(x1, y, x2, y, ac, 0.5, 0.15); y += 4;
  }

  if (y < maxY) {
    s += txt(x1, y + headFS, 'SLOŽENÍ:', headFS, wh, 700, 'start', 0, techFont);
    y += headFS + 3;
    // Automaticky odstraní nepovinný obsah: aminokyseliny za kolagen + latinské botanické názvy
    const slozeniClean = String(v.slozeni || '').trim()
      .replace(/\s*\(obsahuje aminokyseliny:[^)]+\)/gi, '')
      .replace(/\s*\(enthält die Aminosäuren:[^)]+\)/gi, '')
      .replace(/\s*\(contains amino acids:[^)]+\)/gi, '')
      .replace(/\s*\(obsah aminokyselín:[^)]+\)/gi, '')
      .replace(/\s*\(zawiera aminokwasy:[^)]+\)/gi, '')
      .replace(/\s*\([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+ [a-záčďéěíňóřšťúůýž]+\)/g, '');
    wrap(slozeniClean, Math.max(10, Math.floor(w / (textFS * 0.50)))).forEach(l => {
      if (y < maxY) { s += txt(x1, y + textFS, l, textFS, wh, 400, 'start', 0, techFont); y += textFS + 1; }
    });
  }

  if (y + headFS + storFS + distFS * 2 < maxY) {
    y += 4;
    s += line(x1, y, x2, y, ac, 0.5, 0.12); y += 5;
  }

  const distName = (distributor && distributor.name) || 'ANOVEX by AGENA Reality s.r.o.';
  const distAddr = (distributor && distributor.address) || 'Pod radnicí 1328/1, Praha 5 ČR';
  const badgeR = P.VW <= 1200 ? 39 : P.VW <= 1900 ? 51 : 57;
  const badgeCX = x2 - badgeR - 10;
  const badgeCY = y2 - badgeR - 10;
  const footerLineH = distFS + 2;
  const footerTopY = y2 - (distFS * 2 + 10);
  s += line(x1, footerTopY - 6, x2, footerTopY - 6, ac, 0.5, 0.15);
  s += txt(x1, footerTopY + distFS, distName, distFS, wh, 400, 'start', 0, techFont);
  s += txt(x1, footerTopY + distFS + footerLineH, distAddr, distFS, wh, 400, 'start', 0, techFont);
  if (v.num) {
    const badgeText = String(v.num).replace(/\s+/g, ' ').trim();
    const badgeTextFS = P.VW <= 1200 ? 20 : P.VW <= 1900 ? 28 : 32;
    s += `<circle cx="${badgeCX}" cy="${badgeCY}" r="${badgeR}" fill="none" stroke="${ac}" stroke-width="4"></circle>`;
    s += `<text x="${badgeCX}" y="${badgeCY}" text-anchor="middle" dominant-baseline="middle" dy="0.02em" font-family="${techFont}" font-size="${badgeTextFS}" font-weight="700" letter-spacing="0" fill="#ffffff">${esc(badgeText)}</text>`;
  }
  return s;
}

// ════════════════════════════════════════════════════════════════
// PORTRAIT LAYOUT — pro etikety kde VH > VW (např. 110×155 mm)
// Zóny: TOP 45% (branding) | MIDDLE 38% (left: text, right: ings) | BOTTOM 17% (barcode)
// ════════════════════════════════════════════════════════════════

function buildPortraitTopZone(w, cx, xL, xR, yT, yDiv1, F, v, ac, palette, isSel, isPrem, fsOvr, logoColor) {
  const wh       = '#ffffff';
  const acP      = (palette && palette.primary)   || ac;
  const acS      = (palette && palette.secondary) || ac;
  const lineTone = (palette && palette.line)      || acS;
  const zH = yDiv1 - yT;
  let s = '';

  const glowId = `pg_${Math.random().toString(36).slice(2, 7)}`;
  s += `<defs><radialGradient id="${glowId}" cx="50%" cy="50%" r="50%">
    <stop offset="0%"   stop-color="${acS}" stop-opacity="0.22"/>
    <stop offset="55%"  stop-color="${acS}" stop-opacity="0.07"/>
    <stop offset="100%" stop-color="${ac}"  stop-opacity="0"/>
  </radialGradient></defs>`;

  const yAnovex  = yT + Math.round(zH * 0.09);
  const yDiamond = yAnovex + F.xl + 4;
  s += txt(cx, yAnovex + F.xl, 'A N O V E X', F.xl, acP, 900, 'middle', 6);
  s += txt(cx, yDiamond + F.sm, '♦', F.sm, acP, 400, 'middle');

  const seriesText  = isSel ? 'S E L E C T' : (isPrem ? 'P R E M I U M' : 'F O R M U L A');
  const ySeriesLine = yT + Math.round(zH * 0.67);
  const tS          = zH < 500 ? 0.50 : zH < 800 ? 0.65 : 0.80;
  const treeCY      = ySeriesLine - F.sm - 14 - Math.round(25 * tS);
  const treeTopY    = treeCY - Math.round(116 * tS);
  const treeGlowR   = w * 0.20;

  s += `<ellipse cx="${cx}" cy="${treeCY}" rx="${treeGlowR}" ry="${Math.round(treeGlowR * 0.85)}" fill="url(#${glowId})"/>`;

  // Product name between ♦ and tree top
  const bnAvailW = w - 16;
  const bnFS = fsOvr.bname !== undefined ? fsOvr.bname
    : fitFS((v.bname || '').toUpperCase(), bnAvailW, F.xl, F.md, true);
  const bnL = wrap((v.bname || '').toUpperCase(), Math.floor(bnAvailW / (bnFS * 0.72)));
  const nameTotalH = bnL.length * bnFS + (bnL.length - 1) * 4;
  const nameMidY   = (yDiamond + F.sm + treeTopY) / 2;
  const nameStartY = nameMidY - nameTotalH / 2 + bnFS;
  bnL.forEach((l, i) => s += txt(cx, nameStartY + i * (bnFS + 4), l, bnFS, wh, 900, 'middle', 0.5));

  s += treeSVG(cx, treeCY, tS, acS, logoColor);
  s += txt(cx, ySeriesLine, seriesText, F.sm, acS, 700, 'middle', 4);

  // Divider + count anchored to bottom of top zone, s paddingem před hlavní linkou
  const bottomPad = Math.round(F.md * 1.0);
  const yLine  = yDiv1 - F.md - 14 - bottomPad;
  const yCount = yLine + Math.round(F.md * 1.15);
  s += line(cx - w * 0.3, yLine, cx + w * 0.3, yLine, lineTone, 0.8, 1);
  s += txt(cx, yCount, v.count || '', F.md, acS, 700, 'middle');

  // Feats / sub between series line and count divider
  const featsTop = ySeriesLine + F.sm + 8;
  const featsBot = yLine - 8;
  const featsH   = featsBot - featsTop;
  if (isSel) {
    const featFS   = fsOvr.feats !== undefined ? fsOvr.feats : F.xs;
    const selItems = [v.f1, v.f2, v.f3, v.f4].filter(Boolean);
    const featStep = selItems.length > 1 ? (featsH - featFS) / (selItems.length - 1) : featsH / 2;
    selItems.forEach((f, i) => {
      const slotY = featsTop + featStep * i + featFS * 0.85;
      s += txt(cx, slotY, f.replace(/[✓✔☑]/g, '').trim(), featFS, wh, 400, 'middle');
    });
  } else if (v.sub) {
    const subFS = fsOvr.sub !== undefined ? fsOvr.sub : F.xs;
    s += txt(cx, featsTop + featsH / 2 + subFS * 0.5, v.sub, subFS, 'rgba(255,255,255,0.82)', 400, 'middle', 0.5);
  }

  return s;
}

function buildPortraitLeftZone(P, Fm, Z, v, ac, fsOvr, topPadOverride) {
  const { x1, x2, y1, y2, w } = Z;
  const wh       = '#ffffff';
  const red      = '#d06060';
  const techFont = 'Arial,Helvetica,sans-serif';

  const lMin   = legalMinFS(P);
  const fs     = fsOvr.dosage !== undefined ? Math.max(lMin, fsOvr.dosage) : (Fm.min + 2);
  const metaFS = fsOvr.meta   !== undefined ? Math.max(lMin, fsOvr.meta)   : (Fm.min + 2);
  const lh     = Math.ceil(fs * 1.1);
  const cpl    = Math.floor(w / (fs * 0.52));
  const sp     = Math.min(4, Math.max(1, Math.round((y2 - y1) / 180)));

  const specificWarns = (v.w4 || '').split('\n').map(l => l.trim()).filter(Boolean);
  const fullWarnText  = [...BASE_WARNINGS, ...specificWarns].join(' ');

  let specificStorage = (v.storage || '').trim();
  const baseStorageNorm = BASE_STORAGE.toLowerCase().replace(/[.,]/g, '');
  specificStorage = specificStorage.split(/[.!]\s*/).filter(seg => {
    const norm = seg.trim().toLowerCase().replace(/[.,]/g, '');
    if (!norm) return false;
    if (baseStorageNorm.includes(norm) || norm.includes(baseStorageNorm.substring(0, 25))) return false;
    return !(/uzavřen/i.test(norm) && (/such[eéý]/i.test(norm) || /chladn/i.test(norm) || /světl/i.test(norm) || /licht/i.test(norm) || /trocken/i.test(norm)));
  }).join('. ').trim();
  if (specificStorage && !specificStorage.endsWith('.')) specificStorage += '.';
  const fullStorageText = specificStorage ? BASE_STORAGE + ' ' + specificStorage : BASE_STORAGE;

  // Obsah/Šarže anchored to zone bottom (no barcode area)
  const metaY = y2 - (metaFS * 2 + 12);
  // Gap pod tlustou linkou — stejný jako gap nad ní (předán z buildPortraitLabel)
  const topPad = topPadOverride !== undefined ? topPadOverride : Math.round((y2 - y1) * 0.22);
  const maxY  = metaY - 4;

  function renderBlock(startY, label, bodyText, maxY2, labelColor) {
    const labelPx  = label.length * fs * 0.65;
    const firstCPL = Math.max(5, Math.floor((w - labelPx) / (fs * 0.52)));
    const words = bodyText.split(' ').filter(Boolean);
    let firstLine = '', rest = [], done = false;
    words.forEach(wd => {
      if (!done) { const t = firstLine ? firstLine + ' ' + wd : wd; if (t.length <= firstCPL) firstLine = t; else { done = true; rest.push(wd); } }
      else rest.push(wd);
    });
    const restLines  = wrap(rest.join(' '), cpl);
    const totalLines = 1 + restLines.length;
    let out = '', yl = startY;
    if (yl < maxY2) {
      out += `<text x="${x1}" y="${yl + fs}" font-family="${techFont}" font-size="${fs}" fill="${wh}"><tspan font-weight="700" fill="${labelColor || wh}">${esc(label)}</tspan><tspan font-weight="400"> ${esc(firstLine)}</tspan></text>`;
    }
    restLines.forEach(l => {
      yl += lh;
      if (yl < maxY2) out += `<text x="${x1}" y="${yl + fs}" font-family="${techFont}" font-size="${fs}" font-weight="400" fill="${wh}">${esc(l)}</text>`;
    });
    return { s: out, y: startY + (totalLines - 1) * lh + lh };
  }

  let s = '', y = y1 + topPad;
  s += `<text x="${x1}" y="${y + fs}" font-family="${techFont}" font-size="${fs}" font-weight="700" fill="${wh}">DOPLNĚK STRAVY</text>`;
  y += lh;
  { const r = renderBlock(y, 'DÁVKOVÁNÍ:',  (v.dosage || '').trim(), maxY);          s += r.s; y = r.y; }
  { const r = renderBlock(y, 'UPOZORNĚNÍ:', fullWarnText,             maxY, red);     s += r.s; y = r.y; }
  if (y < maxY) { y += sp; const r = renderBlock(y, 'UCHOVÁVÁNÍ:', fullStorageText, maxY); s += r.s; y = r.y; }

  s += line(x1, metaY - 4, x2, metaY - 4, ac, 0.5, 0.18);
  if (v.net) s += `<text x="${x1}" y="${metaY + metaFS}" font-family="${techFont}" font-size="${metaFS}" font-weight="700" fill="${wh}">Obsah: ${esc(v.net)}</text>`;
  s += `<text x="${x1}" y="${metaY + metaFS * 2 + 3}" font-family="${techFont}" font-size="${metaFS}" font-weight="400" fill="${wh}">Č. šarže / Min. trvanlivost: viz. obal</text>`;
  return s;
}

function buildPortraitLabel(product, stack, P, distributor, lang, v, ingsArr) {
  const { VW, VH, LX1 } = P;
  const bg      = stack.bg || '#0f0d08';
  const palette = getPrintPalette(stack);
  const ac      = palette.primary;
  const isSel   = stack.series === 'select';
  const isPrem  = stack.series === 'premium';
  const logoColor = stack.logo_color || 'gold';
  const fsOvr   = product.fs || {};

  const bOff = LX1, bSW = 4;
  const pad  = 8;
  const xL   = bOff + pad;
  const xR   = VW - bOff - pad;
  const w    = xR - xL;
  const cx   = Math.round(xL + w / 2);
  const yT   = bOff + pad;
  const yB   = VH - bOff - pad;
  const h    = yB - yT;

  const yDiv1 = yT + Math.round(h * 0.53);
  const yDiv2 = yT + Math.round(h * 0.87);
  const xMid  = xL + Math.round(w * 0.50);

  // Top zone fonts: height of top zone ≈ reference landscape height (827px)
  const topH = yDiv1 - yT;
  const psc  = topH / 827.0;
  const F = {
    min: Math.max(14, Math.round(26 * psc)),
    xs:  Math.max(16, Math.round(30 * psc)),
    sm:  Math.max(18, Math.round(36 * psc)),
    md:  Math.max(22, Math.round(44 * psc)),
    lg:  Math.max(26, Math.round(56 * psc)),
    xl:  Math.max(32, Math.round(68 * psc)),
  };

  // Middle zone fonts: scale based on middle zone height
  const midH = yDiv2 - yDiv1;
  const msc  = midH / 827.0;
  const Fm = {
    min: Math.max(14, Math.round(26 * msc)),
    xs:  Math.max(16, Math.round(30 * msc)),
    sm:  Math.max(18, Math.round(36 * msc)),
    md:  Math.max(22, Math.round(44 * msc)),
    lg:  Math.max(26, Math.round(56 * msc)),
    xl:  Math.max(32, Math.round(68 * msc)),
  };

  let svg = `<svg width="100%" viewBox="0 0 ${VW} ${VH}" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">`;
  svg += `<defs>
    <clipPath id="pClipT"><rect x="${bOff}" y="${bOff}" width="${VW-bOff*2}" height="${yDiv1-bOff}"/></clipPath>
    <clipPath id="pClipML"><rect x="${bOff}" y="${yDiv1}" width="${xMid-bOff}" height="${yDiv2-yDiv1}"/></clipPath>
    <clipPath id="pClipMR"><rect x="${xMid}" y="${yDiv1}" width="${VW-xMid-bOff}" height="${yDiv2-yDiv1}"/></clipPath>
    <clipPath id="pClipB"><rect x="${bOff}" y="${yDiv2}" width="${VW-bOff*2}" height="${VH-yDiv2-bOff}"/></clipPath>
  </defs>`;

  svg += rect(0, 0, VW, VH, bg);
  svg += `<rect x="${bOff}" y="${bOff}" width="${VW-bOff*2}" height="${VH-bOff*2}" fill="none" stroke="${ac}" stroke-width="${bSW}"/>`;
  svg += line(bOff + 2, yDiv1, VW - bOff - 2, yDiv1, ac, bSW, 1);
  svg += line(bOff + 2, yDiv2, VW - bOff - 2, yDiv2, ac, bSW, 1);
  svg += line(xMid, yDiv1 + 2, xMid, yDiv2 - 2, ac, bSW, 1);

  svg += `<g clip-path="url(#pClipT)">`;
  svg += buildPortraitTopZone(w, cx, xL, xR, yT, yDiv1, F, v, ac, palette, isSel, isPrem, fsOvr, logoColor);
  svg += `</g>`;

  // Gap pod tlustou linkou = gap nad ní (bottomPad z buildPortraitTopZone)
  const contentGap = Math.round(F.md * 1.0);

  const ZL = { x1: xL, x2: xMid - pad, y1: yDiv1 + pad, y2: yDiv2 - pad, w: xMid - pad - xL };
  svg += `<g clip-path="url(#pClipML)">`;
  svg += buildPortraitLeftZone(P, Fm, ZL, v, ac, fsOvr, contentGap);
  svg += `</g>`;

  const PRmod = { ...P, F: Fm };
  const ZR = { x1: xMid + pad, x2: xR, y1: yDiv1 + contentGap, y2: yDiv2 - pad, w: xR - (xMid + pad) };
  svg += `<g clip-path="url(#pClipMR)">`;
  svg += buildRightZone(PRmod, ZR, v, ac, ingsArr, fsOvr, product, distributor);
  svg += `</g>`;

  // Bottom: barcode centered
  const botAvailH = VH - bOff - yDiv2;
  const bcH = Math.round(botAvailH * 0.72);
  const bcW = Math.round(w * 0.60);
  const bcX = bOff + Math.round((VW - 2 * bOff - bcW) / 2);
  const bcY = yDiv2 + Math.round((botAvailH - bcH) * 0.5);
  svg += `<g clip-path="url(#pClipB)">`;
  if (v.ean) svg += buildBarcode(v.ean, bcX, bcY, bcW, bcH, '#000000');
  svg += `</g>`;

  svg += '</svg>';
  return svg;
}
