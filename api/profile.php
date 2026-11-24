<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
$d = input();
$action = $_GET['action'] ?? '';

if ($action === 'update') {
    $name = clean($d['name'] ?? '', 100);
    $email = strtolower(clean($d['email'] ?? '', 150));
    if (mb_strlen($name) < 2) fail('Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
    $st = db()->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');
    $st->execute([$email, $uid]);
    if ($st->fetch()) fail('That email is used by another account.');
    db()->prepare('UPDATE users SET name=?,email=? WHERE user_id=?')->execute([$name, $email, $uid]);
    out(['ok' => true]);
}

if ($action === 'password') {
    $cur = (string)($d['current'] ?? '');
    $new = (string)($d['password'] ?? '');
    if ($new !== (string)($d['confirm'] ?? '')) fail('New passwords do not match.');
    if (strlen($new) < 8) fail('New password must be at least 8 characters.');
    $st = db()->prepare('SELECT password_hash FROM users WHERE user_id=?');
    $st->execute([$uid]);
    if (!password_verify($cur, $st->fetch()['password_hash'])) fail('Current password is incorrect.');
    db()->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
    out(['ok' => true]);
}

fail('Unknown action', 404);
