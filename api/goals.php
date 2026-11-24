<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $st = db()->prepare('SELECT goal_id,title,target_amount,saved_amount,target_date FROM goals WHERE user_id=? ORDER BY goal_id DESC');
    $st->execute([$uid]);
    out(['goals' => $st->fetchAll()]);
}

if ($method === 'POST') {
    $d = input();
    $action = $_GET['action'] ?? 'create';

    if ($action === 'add_money') { // add (or withdraw with a negative value) money toward a goal
        $id = (int)($d['goal_id'] ?? 0);
        $amt = round((float)($d['amount'] ?? 0), 2);
        if ($amt == 0) fail('Enter an amount.');
        $st = db()->prepare('SELECT saved_amount FROM goals WHERE goal_id=? AND user_id=?');
        $st->execute([$id, $uid]);
        $g = $st->fetch();
        if (!$g) fail('Goal not found.', 404);
        $new = (float)$g['saved_amount'] + $amt;
        if ($new < 0) fail('You cannot withdraw more than has been saved.');
        db()->prepare('UPDATE goals SET saved_amount=? WHERE goal_id=? AND user_id=?')->execute([$new, $id, $uid]);
        out(['ok' => true]);
    }

    $title = clean($d['title'] ?? '', 100);
    $target = round((float)($d['target_amount'] ?? 0), 2);
    $date = (string)($d['target_date'] ?? '');
    if ($title === '') fail('Give your goal a title.');
    if ($target <= 0) fail('Target must be greater than zero.');
    if ($date !== '' && !valid_date($date)) fail('Invalid target date.');
    db()->prepare('INSERT INTO goals (user_id,title,target_amount,target_date) VALUES (?,?,?,?)')
        ->execute([$uid, $title, $target, $date ?: null]);
    out(['ok' => true]);
}

if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $d = input();
    $title = clean($d['title'] ?? '', 100);
    $target = round((float)($d['target_amount'] ?? 0), 2);
    $date = (string)($d['target_date'] ?? '');
    if ($title === '' || $target <= 0) fail('Title and a positive target are required.');
    if ($date !== '' && !valid_date($date)) fail('Invalid target date.');
    $chk = db()->prepare('SELECT 1 FROM goals WHERE goal_id=? AND user_id=?');
    $chk->execute([$id, $uid]);
    if (!$chk->fetch()) fail('Goal not found.', 404);
    db()->prepare('UPDATE goals SET title=?,target_amount=?,target_date=? WHERE goal_id=? AND user_id=?')
        ->execute([$title, $target, $date ?: null, $id, $uid]);
    out(['ok' => true]);
}

if ($method === 'DELETE') {
    $st = db()->prepare('DELETE FROM goals WHERE goal_id=? AND user_id=?');
    $st->execute([(int)($_GET['id'] ?? 0), $uid]);
    if (!$st->rowCount()) fail('Goal not found.', 404);
    out(['ok' => true]);
}

fail('Method not allowed', 405);
