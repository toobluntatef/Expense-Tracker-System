<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    process_recurring($uid);
    $month = $_GET['month'] ?? date('Y-m');
    if (!valid_month($month)) fail('Invalid month.');
    $st = db()->prepare("SELECT b.budget_id,b.category_id,c.name AS category,b.limit_amount,
            COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id=b.user_id AND t.category_id=b.category_id
                      AND t.type='expense' AND DATE_FORMAT(t.txn_date,'%Y-%m')=b.month),0) AS spent
        FROM budgets b JOIN categories c ON c.category_id=b.category_id
        WHERE b.user_id=? AND b.month=? ORDER BY c.name");
    $st->execute([$uid, $month]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['limit_amount'] = (float)$r['limit_amount'];
        $r['spent'] = (float)$r['spent'];
        $r['percent'] = $r['limit_amount'] > 0 ? (int)round($r['spent'] / $r['limit_amount'] * 100) : 0;
    }
    out(['budgets' => $rows, 'month' => $month]);
}

if ($method === 'POST') { // create or update (upsert) the budget for a category + month
    $d = input();
    $cat = (int)($d['category_id'] ?? 0);
    $month = (string)($d['month'] ?? '');
    $limit = round((float)($d['limit_amount'] ?? 0), 2);
    if (!valid_month($month)) fail('Invalid month.');
    if ($limit <= 0) fail('Enter a limit greater than zero.');
    $st = db()->prepare("SELECT 1 FROM categories WHERE category_id=? AND type='expense' AND (user_id IS NULL OR user_id=?)");
    $st->execute([$cat, $uid]);
    if (!$st->fetch()) fail('Choose an expense category.');
    db()->prepare('INSERT INTO budgets (user_id,category_id,month,limit_amount) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE limit_amount=VALUES(limit_amount)')->execute([$uid, $cat, $month, $limit]);
    out(['ok' => true]);
}

if ($method === 'DELETE') {
    $st = db()->prepare('DELETE FROM budgets WHERE budget_id=? AND user_id=?');
    $st->execute([(int)($_GET['id'] ?? 0), $uid]);
    if (!$st->rowCount()) fail('Budget not found.', 404);
    out(['ok' => true]);
}

fail('Method not allowed', 405);
