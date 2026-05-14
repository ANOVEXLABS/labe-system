<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) json_error('Vyplňte email a heslo.');
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            json_error('Nesprávné přihlašovací údaje.', 401);
        }
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        json_out(['ok' => true, 'user' => $_SESSION['user']]);
        break;

    case 'logout':
        session_destroy();
        json_out(['ok' => true]);
        break;

    case 'me':
        if (empty($_SESSION['user'])) json_error('Nepřihlášen.', 401);
        json_out($_SESSION['user']);
        break;

    case 'set_password':
        // Admin nastaví heslo při prvním spuštění
        $user = auth();
        if ($user['role'] !== 'admin') json_error('Pouze admin.', 403);
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = $_POST['password'] ?? '';
        if (!$uid || strlen($pass) < 8) json_error('Heslo musí mít alespoň 8 znaků.');
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $uid]);
        json_out(['ok' => true]);
        break;

    default:
        json_error('Neznámá akce.', 404);
}
