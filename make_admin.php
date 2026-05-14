<?php
require_once __DIR__ . '/config/db.php';

$result = db()->query("SELECT id, name, email, role FROM users ORDER BY id")->fetchAll();
echo "<pre>Uživatelé:\n";
foreach ($result as $u) {
    echo "ID:{$u['id']} | {$u['name']} | {$u['email']} | role: {$u['role']}\n";
}
echo "</pre>";

if (isset($_GET['promote'])) {
    $id = (int)$_GET['promote'];
    db()->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$id]);
    echo "<p style='color:green'>✓ Uživatel ID $id povýšen na admin. <a href='/make_admin.php'>Obnovit</a></p>";
}

echo "<p>Pro povýšení na admin: <a href='?promote=1'>?promote=1</a> (nebo jiné ID z tabulky výše)</p>";
echo "<p style='color:red'><b>Po použití smaž tento soubor!</b></p>";
