<?php
/**
 * ANOVEX Label System v2 — Setup
 * Spusť JEDNOU po nahrání na server: https://labels.anovex.eu/setup.php
 * Po dokončení SMAŽ tento soubor!
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password']   ?? '';
    $pass2 = $_POST['password2']  ?? '';

    if (!$name || !$email || !$pass) {
        $error = 'Vyplňte všechna pole.';
    } elseif ($pass !== $pass2) {
        $error = 'Hesla se neshodují.';
    } elseif (strlen($pass) < 8) {
        $error = 'Heslo musí mít alespoň 8 znaků.';
    } else {
        try {
            // Spusť SQL schéma — rozdělíme na jednotlivé příkazy aby fungovalo i bez exec multi-query
            $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
            // Odstraň komentáře řádek
            $sql = preg_replace('/^--.*$/m', '', $sql);
            // Rozděl na příkazy podle středníku
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt) db()->exec($stmt);
            }

            // Vytvoř admina
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE password=?, role="admin"');
            $stmt->execute([$name, $email, $hash, 'admin', $hash]);

            $success = 'Setup dokončen! Přihlas se a SMAŽ tento soubor (setup.php).';
        } catch (Exception $e) {
            $error = 'Chyba: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>ANOVEX Setup</title>
<style>
body{font-family:Arial,sans-serif;background:#0a0a0a;color:#e0ddd6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#141414;border:1px solid #222;border-radius:8px;padding:40px;width:400px}
h1{font-size:18px;letter-spacing:4px;color:#c9a84c;margin-bottom:8px}
p{font-size:12px;color:#666;margin-bottom:28px}
label{display:block;font-size:11px;letter-spacing:1px;color:#888;margin-bottom:4px;text-transform:uppercase}
input{width:100%;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:4px;color:#e0ddd6;font-size:14px;padding:10px 12px;margin-bottom:16px;box-sizing:border-box}
button{width:100%;background:#c9a84c;color:#000;border:none;border-radius:4px;padding:12px;font-weight:700;font-size:13px;letter-spacing:1px;cursor:pointer;margin-top:8px}
.err{background:#3a1010;border:1px solid #c13a3a;border-radius:4px;padding:10px 14px;color:#e07070;font-size:13px;margin-bottom:16px}
.ok{background:#0a2a1a;border:1px solid #2d9e75;border-radius:4px;padding:10px 14px;color:#4dd4a0;font-size:13px;margin-bottom:16px}
.warn{font-size:11px;color:#666;margin-top:20px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h1>ANOVEX</h1>
  <p>Label System v2 — první spuštění</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif ?>
  <?php if ($success): ?><div class="ok"><?= htmlspecialchars($success) ?> <a href="/login.php" style="color:#4dd4a0">Přihlásit se →</a></div><?php endif ?>
  <?php if (!$success): ?>
  <form method="post">
    <label>Jméno</label>
    <input type="text" name="name" placeholder="Vladimír" required>
    <label>Email</label>
    <input type="email" name="email" placeholder="admin@anovex.eu" required>
    <label>Heslo</label>
    <input type="password" name="password" placeholder="min. 8 znaků" required>
    <label>Heslo znovu</label>
    <input type="password" name="password2" placeholder="min. 8 znaků" required>
    <button type="submit">Vytvořit admina a spustit setup</button>
  </form>
  <?php endif ?>
  <p class="warn">⚠ Po dokončení smaž tento soubor ze serveru.</p>
</div>
</body>
</html>
