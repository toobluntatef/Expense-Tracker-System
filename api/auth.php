<?php
require_once __DIR__ . '/bootstrap.php';
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'csrf': // lets static pages obtain a token before login/register
        out(['csrf' => csrf_token()]);

    case 'me':
        if (empty($_SESSION['uid'])) out(['user' => null, 'csrf' => csrf_token()]);
        $u = require_login();
        out(['user' => $u, 'csrf' => csrf_token()]);

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
        check_csrf();
        $d = input();
        $name = clean($d['name'] ?? '', 100);
        $email = strtolower(clean($d['email'] ?? '', 150));
        $pw = (string)($d['password'] ?? '');
        $pw2 = (string)($d['confirm'] ?? '');
        if ($name === '' || mb_strlen($name) < 2) fail('Please enter your name.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
        if (strlen($pw) < 8) fail('Password must be at least 8 characters.');
        if ($pw !== $pw2) fail('Passwords do not match.');
        $st = db()->prepare('SELECT 1 FROM users WHERE email=?');
        $st->execute([$email]);
        if ($st->fetch()) fail('That email is already registered.');
        $ins = db()->prepare("INSERT INTO users (name,email,password_hash,role,status) VALUES (?,?,?,'user','active')");
        $ins->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        unset($_SESSION['csrf']);
        out(['ok' => true, 'csrf' => csrf_token()]);

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
        check_csrf();
        $d = input();
        $email = strtolower(clean($d['email'] ?? '', 150));
        $pw = (string)($d['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !password_verify($pw, $u['password_hash'])) fail('Incorrect email or password.', 401);
        if ($u['status'] !== 'active') fail('This account has been deactivated. Contact the administrator.', 403);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['user_id'];
        unset($_SESSION['csrf']);
        db()->prepare('UPDATE users SET last_login=NOW() WHERE user_id=?')->execute([$u['user_id']]);
        out(['ok' => true, 'role' => $u['role'], 'csrf' => csrf_token()]);

    case 'logout':
        check_csrf();
        $_SESSION = [];
        session_destroy();
        out(['ok' => true]);

    default:
        fail('Unknown action', 404);
}
