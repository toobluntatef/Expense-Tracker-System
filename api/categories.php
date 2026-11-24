<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $st = db()->prepare('SELECT category_id,name,type,(user_id IS NULL) AS is_default FROM categories WHERE user_id IS NULL OR user_id=? ORDER BY type, name');
    $st->execute([$uid]);
    out(['categories' => $st->fetchAll()]);
}

if ($method === 'POST') {
    $d = input();
    $name = clean($d['name'] ?? '', 60);
    $type = ($d['type'] ?? '') === 'income' ? 'income' : 'expense';
    if ($name === '') fail('Category name is required.');
    $chk = db()->prepare('SELECT 1 FROM categories WHERE (user_id IS NULL OR user_id=?) AND LOWER(name)=LOWER(?) AND type=?');
    $chk->execute([$uid, $name, $type]);
    if ($chk->fetch()) fail('That category already exists.');
    db()->prepare('INSERT INTO categories (user_id,name,type) VALUES (?,?,?)')->execute([$uid, $name, $type]);
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare('DELETE FROM categories WHERE category_id=? AND user_id=?'); // only own custom categories
    $st->execute([$id, $uid]);
    if (!$st->rowCount()) fail('Category not found.', 404);
    out(['ok' => true]);
}

fail('Method not allowed', 405);
