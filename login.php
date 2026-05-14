<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user'])) {
    header('Location: /index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($email && $pass) {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password'])) {
            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
            $_SESSION['user'] = ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']];
            header('Location: /index.php'); exit;
        }
        $error = 'Nesprávný email nebo heslo.';
    } else {
        $error = 'Vyplňte email a heslo.';
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ANOVEX Labels — Přihlášení</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Montserrat',sans-serif;background:#080808;color:#e2dfd8;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#0e0e0e;border:1px solid #1c1c1c;border-radius:8px;padding:48px 40px;width:400px}
.logo{font-size:22px;font-weight:900;letter-spacing:6px;color:#c9a84c;margin-bottom:4px}
.logo-bar{width:36px;height:2px;background:#c9a84c;margin-bottom:32px}
label{display:block;font-size:10px;letter-spacing:2px;color:#666;text-transform:uppercase;margin-bottom:6px}
input{width:100%;background:#141414;border:1px solid #252525;border-radius:4px;color:#e2dfd8;font-family:'Montserrat',sans-serif;font-size:13px;padding:11px 14px;margin-bottom:18px;outline:none;transition:border-color .15s}
input:focus{border-color:#c9a84c}
button{width:100%;background:#c9a84c;color:#000;border:none;border-radius:4px;padding:13px;font-family:'Montserrat',sans-serif;font-weight:700;font-size:12px;letter-spacing:2px;text-transform:uppercase;cursor:pointer;margin-top:4px;transition:opacity .15s}
button:hover{opacity:.85}
.err{background:#1a0808;border:1px solid #c13a3a;border-radius:4px;padding:10px 14px;color:#e07070;font-size:12px;margin-bottom:18px}
.ver{font-size:10px;color:#333;margin-top:24px;text-align:center;letter-spacing:1px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">ANOVEX</div>
  <div class="logo-bar"></div>
  <?php if ($error): ?>
  <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif ?>
  <form method="post">
    <label>Email</label>
    <input type="email" name="email" placeholder="váš@email.cz" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Heslo</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <button type="submit">Přihlásit se →</button>
  </form>
  <div class="ver">Label System v2.0</div>
</div>
</body>
</html>
