<?php
require_once __DIR__ . '/bootstrap.php';
$admin = require_admin();
$aid = (int)$admin['user_id'];
$pdo = db();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// NOTE: the admin deliberately has NO endpoint that reads other users' transactions,
// budgets or goals. Only account data and aggregate counts are exposed.

if ($action === 'stats') {
    out([
        'users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
        'active_today' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_login)=CURDATE()")->fetchColumn(),
        'transactions' => (int)$pdo->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
    ]);
}

if ($action === 'users' && $method === 'GET') {
    $q = clean($_GET['q'] ?? '', 100);
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $st = $pdo->prepare('SELECT user_id,name,email,role,status,last_login,created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY user_id LIMIT 200');
    $st->execute([$like, $like]);
    out(['users' => $st->fetchAll()]);
}

if ($action === 'set_status' && $method === 'POST') {
    $id = (int)(input()['user_id'] ?? 0);
    $status = (input()['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    if ($id === $aid) fail('You cannot change your own status.');
    $st = $pdo->prepare('UPDATE users SET status=? WHERE user_id=?');
    $st->execute([$status, $id]);
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE user_id=?');
    $chk->execute([$id]);
    if (!$chk->fetch()) fail('User not found.', 404);
    out(['ok' => true]);
}

if ($action === 'reset_password' && $method === 'POST') {
    $id = (int)(input()['user_id'] ?? 0);
    $pw = (string)(input()['password'] ?? '');
    if ($id === $aid) fail('Change your own password from Settings.');
    if (strlen($pw) < 8) fail('Password must be at least 8 characters.');
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE user_id=?');
    $chk->execute([$id]);
    if (!$chk->fetch()) fail('User not found.', 404);
    $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
    out(['ok' => true]);
}

if ($action === 'categories') {
    if ($method === 'GET') {
        out(['categories' => $pdo->query('SELECT category_id,name,type FROM categories WHERE user_id IS NULL ORDER BY type,name')->fetchAll()]);
    }
    if ($method === 'POST') {
        $name = clean(input()['name'] ?? '', 60);
        $type = (input()['type'] ?? '') === 'income' ? 'income' : 'expense';
        if ($name === '') fail('Category name is required.');
        $chk = $pdo->prepare('SELECT 1 FROM categories WHERE user_id IS NULL AND LOWER(name)=LOWER(?) AND type=?');
        $chk->execute([$name, $type]);
        if ($chk->fetch()) fail('That default category already exists.');
        $pdo->prepare('INSERT INTO categories (user_id,name,type) VALUES (NULL,?,?)')->execute([$name, $type]);
        out(['ok' => true]);
    }
    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        $name = clean(input()['name'] ?? '', 60);
        if ($name === '') fail('Category name is required.');
        $st = $pdo->prepare('UPDATE categories SET name=? WHERE category_id=? AND user_id IS NULL');
        $st->execute([$name, $id]);
        out(['ok' => true]);
    }
    if ($method === 'DELETE') {
        $st = $pdo->prepare('DELETE FROM categories WHERE category_id=? AND user_id IS NULL');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        if (!$st->rowCount()) fail('Category not found.', 404);
        out(['ok' => true]);
    }
}

fail('Unknown action', 404);
