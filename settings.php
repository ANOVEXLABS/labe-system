<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
$user = auth();
if (!isAdmin()) { header('Location: /index.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  $keys = ['distributor_name','distributor_address','distributor_ico','warn_color','default_lang','anthropic_api_key'];
  $stmt = db()->prepare('INSERT INTO settings (skey, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?');
  foreach ($keys as $k) {
    $v = trim($_POST[$k] ?? '');
    $stmt->execute([$k, $v, $v]);
  }
  $msg = 'Nastavení uloženo.';
}

$s = [];
foreach (db()->query('SELECT skey, value FROM settings')->fetchAll() as $r) {
  $s[$r['skey']] = $r['value'];
}

$users = db()->query('SELECT id, name, email, role, active, last_login FROM users ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Nastavení — ANOVEX Labels</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
.wrap{max-width:700px;margin:80px auto;padding:0 24px}
h1{font-size:16px;letter-spacing:3px;color:var(--gold);margin-bottom:24px}
h2{font-size:12px;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin:28px 0 12px}
.card{background:var(--s1);border:1px solid #1c1c1c;border-radius:6px;padding:24px;margin-bottom:20px}
.ok{background:#0a2a1a;border:1px solid var(--green);border-radius:4px;padding:10px 14px;color:var(--green);font-size:12px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:6px 10px;border-bottom:1px solid #1c1c1c}
td{padding:8px 10px;border-bottom:1px solid #141414}
.role{padding:2px 8px;border-radius:3px;font-size:10px;font-weight:700;letter-spacing:1px}
.role-admin{background:#1a1000;color:var(--gold);border:1px solid var(--gold)}
.role-editor{background:var(--b1);color:var(--muted);border:1px solid var(--border)}
</style>
</head>
<body>
<div id="hdr">
  <div class="hdr-left">
    <div class="hdr-logo">ANOVEX</div>
    <div class="hdr-sep"></div>
    <div class="hdr-title">Nastavení</div>
  </div>
  <div class="hdr-right">
    <a href="/index.php" class="btn btn-ghost">← Zpět</a>
  </div>
</div>

<div class="wrap">
  <h1>NASTAVENÍ</h1>

  <?php if ($msg): ?>
  <div class="ok"><?= htmlspecialchars($msg) ?></div>
  <?php endif ?>

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
          <select name="default_lang" style="width:100%;background:var(--b1);border:1px solid var(--border);border-radius:4px;color:var(--text);font-family:'Montserrat',sans-serif;font-size:12px;padding:7px 9px">
            <option value="cs" <?= ($s['default_lang']??'')=='cs'?'selected':'' ?>>Čeština</option>
            <option value="de" <?= ($s['default_lang']??'')=='de'?'selected':'' ?>>Němčina</option>
          </select>
        </div>
      </div>
      <div class="fld" style="margin-top:10px"><label>Anthropic API klíč pro parsování PDF</label>
        <input name="anthropic_api_key" type="password" autocomplete="off" placeholder="sk-ant-…" value="<?= htmlspecialchars($s['anthropic_api_key']??'') ?>">
        <div style="font-size:10px;color:var(--muted);margin-top:5px;line-height:1.4">Klíč se uloží serverově do tabulky settings a parser ho použije automaticky. Na hlavní stránce už ho nemusíš zadávat.</div>
      </div>
      <button type="submit" name="save_settings" class="btn btn-gold" style="margin-top:8px">Uložit nastavení</button>
    </form>
  </div>

  <div class="card">
    <h2>Uživatelé</h2>
    <table>
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
          <select id="u-role" style="width:100%;background:var(--b1);border:1px solid var(--border);border-radius:4px;color:var(--text);font-family:'Montserrat',sans-serif;font-size:12px;padding:7px 9px">
            <option value="editor">Editor</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-ghost" style="margin-top:4px">Přidat uživatele</button>
      <span id="u-msg" style="font-size:12px;margin-left:12px;color:var(--green)"></span>
    </form>
  </div>
</div>

<script>
async function createUser(e) {
  e.preventDefault();
  const msg = document.getElementById('u-msg');
  try {
    const r = await fetch('/api/settings.php?action=create_user', {
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
</script>
</body>
</html>
