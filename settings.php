<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
$user = auth();
if (!isAdmin()) { header('Location: index.php'); exit; }

$company_id = (int)($user['company_id'] ?? 1);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  $keys = ['distributor_name','distributor_address','distributor_ico','warn_color','default_lang','anthropic_api_key'];
  $stmt = db()->prepare(
    'INSERT INTO company_settings (company_id, skey, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=?'
  );
  foreach ($keys as $k) {
    $v = trim($_POST[$k] ?? '');
    $stmt->execute([$company_id, $k, $v, $v]);
  }
  $msg = 'Nastavení uloženo.';
}
$stmt = db()->prepare('SELECT skey, value FROM company_settings WHERE company_id = ?');
$stmt->execute([$company_id]);
$s = array_column($stmt->fetchAll(), 'value', 'skey');

$stmt = db()->prepare('SELECT id, name, email, role, active, last_login FROM users WHERE company_id = ? ORDER BY id');
$stmt->execute([$company_id]);
$users = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM suppliers WHERE company_id = ? ORDER BY id');
$stmt->execute([$company_id]);
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Nastavení — ANOVEX Labels</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="settings">
<div id="hdr">
  <div class="hdr-left">
    <div class="hdr-logo">ANOVEX</div>
    <div class="hdr-sep"></div>
    <div class="hdr-title">Nastavení</div>
  </div>
  <div class="hdr-right">
    <a href="index.php" class="btn btn-ghost">← Zpět</a>
  </div>
</div>

<div class="wrap">
  <h1>NASTAVENÍ</h1>

  <?php if ($msg): ?>
  <div class="ok"><?= htmlspecialchars($msg) ?></div>
  <?php endif ?>

  <!-- GLOBÁLNÍ NASTAVENÍ -->
  <div class="card">
    <h2>Distributor a globální hodnoty</h2>
    <form method="post">
      <div class="fld"><label>Název distributora</label>
        <input name="distributor_name" value="<?= htmlspecialchars($s['distributor_name']??'') ?>"></div>
      <div class="fld"><label>Adresa distributora</label>
        <input name="distributor_address" value="<?= htmlspecialchars($s['distributor_address']??'') ?>"></div>
      <div class="fld"><label>IČO</label>
        <input name="distributor_ico" value="<?= htmlspecialchars($s['distributor_ico']??'') ?>"></div>
      <div class="frow">
        <div class="fld"><label>Barva upozornění</label>
          <input name="warn_color" value="<?= htmlspecialchars($s['warn_color']??'#c13a3a') ?>"></div>
        <div class="fld"><label>Výchozí jazyk</label>
          <select name="default_lang">
            <?php foreach (['cs'=>'Čeština','de'=>'Němčina','en'=>'Angličtina','pl'=>'Polština','sk'=>'Slovenština'] as $val=>$lbl): ?>
            <option value="<?= $val ?>" <?= ($s['default_lang']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="fld" style="margin-top:10px"><label>Anthropic API klíč pro parsování PDF</label>
        <input name="anthropic_api_key" type="password" autocomplete="off" placeholder="sk-ant-…"
               value="<?= htmlspecialchars($s['anthropic_api_key']??'') ?>">
        <div class="fld-hint">Klíč se uloží serverově do tabulky settings a parser ho použije automaticky. Na hlavní stránce už ho nemusíš zadávat.</div>
      </div>
      <button type="submit" name="save_settings" class="btn btn-gold" style="margin-top:8px">Uložit nastavení</button>
    </form>
  </div>

  <!-- DODAVATELÉ -->
  <div class="card">
    <h2>Dodavatelé</h2>
    <table class="settings-table">
      <thead>
        <tr>
          <th style="width:20px"></th>
          <th>Název</th>
          <th>Kód</th>
          <th>Adresa</th>
          <th>SKU prefix</th>
          <th style="width:170px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($suppliers as $sup): ?>
      <tr class="sup-row <?= $sup['active'] ? '' : 'sup-inactive' ?>" data-id="<?= $sup['id'] ?>">
        <td><span class="sup-dot <?= $sup['active'] ? 'dot-on' : 'dot-off' ?>"></span></td>
        <td class="sup-editable">
          <span class="sup-val"><?= htmlspecialchars($sup['name']) ?></span>
          <input class="sup-input" value="<?= htmlspecialchars($sup['name']) ?>" id="se-name-<?= $sup['id'] ?>">
        </td>
        <td class="sup-editable">
          <span class="sup-val"><?= htmlspecialchars($sup['code']) ?></span>
          <input class="sup-input" value="<?= htmlspecialchars($sup['code']) ?>" id="se-code-<?= $sup['id'] ?>">
        </td>
        <td class="sup-editable">
          <span class="sup-val"><?= htmlspecialchars($sup['address']) ?></span>
          <input class="sup-input" value="<?= htmlspecialchars($sup['address']) ?>" id="se-address-<?= $sup['id'] ?>">
        </td>
        <td class="sup-editable">
          <span class="sup-val"><?= htmlspecialchars($sup['sku_prefix']??'') ?></span>
          <input class="sup-input" value="<?= htmlspecialchars($sup['sku_prefix']??'') ?>" id="se-sku-<?= $sup['id'] ?>">
        </td>
        <td class="sup-actions">
          <span class="sup-view-btns">
            <button class="btn btn-ghost btn-xs" onclick="startEditSupplier(<?= $sup['id'] ?>)">Upravit</button>
            <button class="btn btn-ghost btn-xs" onclick="toggleSupplier(<?= $sup['id'] ?>)"><?= $sup['active'] ? 'Deaktivovat' : 'Aktivovat' ?></button>
          </span>
          <span class="sup-edit-btns">
            <button class="btn btn-gold btn-xs" onclick="saveSupplier(<?= $sup['id'] ?>)">Uložit</button>
            <button class="btn btn-ghost btn-xs" onclick="cancelEditSupplier(<?= $sup['id'] ?>)">Zrušit</button>
          </span>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <h2 style="margin-top:24px">Přidat dodavatele</h2>
    <form onsubmit="createSupplier(event)">
      <div class="frow">
        <div class="fld"><label>Název *</label><input id="sn-name" placeholder="Chance2brand s.r.o."></div>
        <div class="fld"><label>Kód *</label><input id="sn-code" placeholder="C2B"></div>
      </div>
      <div class="frow">
        <div class="fld"><label>Adresa</label><input id="sn-address" placeholder="Ulice 1, 110 00 Praha"></div>
        <div class="fld"><label>SKU prefix</label><input id="sn-sku" placeholder="C2B-"></div>
      </div>
      <button type="submit" class="btn btn-ghost" style="margin-top:4px">Přidat dodavatele</button>
      <span id="sup-msg" style="font-size:12px;margin-left:12px"></span>
    </form>
  </div>

  <!-- UŽIVATELÉ -->
  <div class="card">
    <h2>Uživatelé</h2>
    <table class="settings-table">
      <thead><tr><th>Jméno</th><th>Email</th><th>Role</th><th>Poslední přihlášení</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="role role-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
        <td style="color:var(--muted)"><?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?></td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <h2 style="margin-top:20px">Přidat uživatele</h2>
    <form onsubmit="createUser(event)">
      <div class="frow">
        <div class="fld"><label>Jméno</label><input id="u-name" placeholder="Jana Nováková"></div>
        <div class="fld"><label>Email</label><input type="email" id="u-email" placeholder="jana@firma.cz"></div>
      </div>
      <div class="frow">
        <div class="fld"><label>Heslo</label><input type="password" id="u-pass" placeholder="min. 8 znaků"></div>
        <div class="fld"><label>Role</label>
          <select id="u-role">
            <option value="editor">Editor</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-ghost" style="margin-top:4px">Přidat uživatele</button>
      <span id="u-msg" style="font-size:12px;margin-left:12px"></span>
    </form>
  </div>
</div>

<script>
async function createUser(e) {
  e.preventDefault();
  const msg = document.getElementById('u-msg');
  try {
    const r = await fetch('api/settings.php?action=create_user', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        name:     document.getElementById('u-name').value,
        email:    document.getElementById('u-email').value,
        password: document.getElementById('u-pass').value,
        role:     document.getElementById('u-role').value,
      })
    });
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    msg.style.color = 'var(--green)';
    msg.textContent = '✓ Uživatel vytvořen — obnovte stránku';
  } catch(err) {
    msg.style.color = 'var(--red)';
    msg.textContent = '✗ ' + err.message;
  }
}

function startEditSupplier(id) {
  document.querySelectorAll('.sup-row.editing').forEach(row => row.classList.remove('editing'));
  const row = document.querySelector(`.sup-row[data-id="${id}"]`);
  if (row) row.classList.add('editing');
}

function cancelEditSupplier(id) {
  const row = document.querySelector(`.sup-row[data-id="${id}"]`);
  if (row) row.classList.remove('editing');
}

async function saveSupplier(id) {
  const name       = document.getElementById(`se-name-${id}`).value.trim();
  const code       = document.getElementById(`se-code-${id}`).value.trim();
  const address    = document.getElementById(`se-address-${id}`).value.trim();
  const sku_prefix = document.getElementById(`se-sku-${id}`).value.trim();
  if (!name || !code) { alert('Název a kód jsou povinné.'); return; }
  const btn = document.querySelector(`.sup-row[data-id="${id}"] .btn-gold`);
  if (btn) btn.textContent = '…';
  try {
    const r = await fetch(`api/settings.php?action=supplier_update&id=${id}`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ name, code, address, sku_prefix })
    });
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    location.reload();
  } catch(err) {
    alert('Chyba: ' + err.message);
    if (btn) btn.textContent = 'Uložit';
  }
}

async function toggleSupplier(id) {
  const r = await fetch(`api/settings.php?action=supplier_toggle&id=${id}`, { method: 'POST' });
  const d = await r.json();
  if (d.error) { alert(d.error); return; }
  location.reload();
}

async function createSupplier(e) {
  e.preventDefault();
  const msg = document.getElementById('sup-msg');
  const name = document.getElementById('sn-name').value.trim();
  const code = document.getElementById('sn-code').value.trim();
  if (!name || !code) {
    msg.style.color = 'var(--red)';
    msg.textContent = 'Název a kód jsou povinné.';
    return;
  }
  try {
    const r = await fetch('api/settings.php?action=supplier_create', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        name,
        code,
        address:    document.getElementById('sn-address').value.trim(),
        sku_prefix: document.getElementById('sn-sku').value.trim(),
      })
    });
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    msg.style.color = 'var(--green)';
    msg.textContent = '✓ Dodavatel přidán — obnovte stránku';
    e.target.reset();
  } catch(err) {
    msg.style.color = 'var(--red)';
    msg.textContent = '✗ ' + err.message;
  }
}
</script>
</body>
</html>
