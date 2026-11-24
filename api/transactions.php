<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function validate_txn(array $d, int $uid): array {
    $type = ($d['type'] ?? '') === 'income' ? 'income' : 'expense';
    $amount = round((float)($d['amount'] ?? 0), 2);
    $date = (string)($d['txn_date'] ?? '');
    $cat = (int)($d['category_id'] ?? 0);
    $note = clean($d['note'] ?? '', 255);
    $pay = clean($d['payment_method'] ?? 'Cash', 30) ?: 'Cash';
    $rec = !empty($d['is_recurring']) ? 1 : 0;
    if ($amount <= 0 || $amount > 9999999999) fail('Enter an amount greater than zero.');
    if (!valid_date($date)) fail('Enter a valid date.');
    $st = db()->prepare('SELECT type FROM categories WHERE category_id=? AND (user_id IS NULL OR user_id=?)');
    $st->execute([$cat, $uid]);
    $c = $st->fetch();
    if (!$c) fail('Choose a category.');
    if ($c['type'] !== $type) fail('That category does not match the transaction type.');
    return [$type, $amount, $date, $cat, $note, $pay, $rec];
}

if ($method === 'GET') {
    process_recurring($uid);
    $where = ['t.user_id = ?'];
    $p = [$uid];
    $q = clean($_GET['q'] ?? '', 100);
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where[] = '(t.note LIKE ? OR c.name LIKE ?)';
        array_push($p, $like, $like);
    }
    if (!empty($_GET['category_id'])) { $where[] = 't.category_id = ?'; $p[] = (int)$_GET['category_id']; }
    if (in_array($_GET['type'] ?? '', ['income', 'expense'], true)) { $where[] = 't.type = ?'; $p[] = $_GET['type']; }
    if (!empty($_GET['from']) && valid_date($_GET['from'])) { $where[] = 't.txn_date >= ?'; $p[] = $_GET['from']; }
    if (!empty($_GET['to']) && valid_date($_GET['to'])) { $where[] = 't.txn_date <= ?'; $p[] = $_GET['to']; }
    if (isset($_GET['min']) && $_GET['min'] !== '') { $where[] = 't.amount >= ?'; $p[] = (float)$_GET['min']; }
    if (isset($_GET['max']) && $_GET['max'] !== '') { $where[] = 't.amount <= ?'; $p[] = (float)$_GET['max']; }

    $sortMap = ['date' => 't.txn_date', 'amount' => 't.amount', 'category' => 'c.name'];
    $sort = $sortMap[$_GET['sort'] ?? 'date'] ?? 't.txn_date';
    $dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = min(100, max(1, (int)($_GET['per'] ?? 10)));
    $w = implode(' AND ', $where);

    $cnt = db()->prepare("SELECT COUNT(*) n FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id WHERE $w");
    $cnt->execute($p);
    $total = (int)$cnt->fetch()['n'];

    $off = ($page - 1) * $per;
    $st = db()->prepare("SELECT t.txn_id,t.type,t.amount,t.txn_date,t.note,t.payment_method,t.is_recurring,t.parent_id,t.category_id,c.name AS category
        FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id
        WHERE $w ORDER BY $sort $dir, t.txn_id DESC LIMIT $per OFFSET $off");
    $st->execute($p);
    out(['transactions' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'per' => $per]);
}

if ($method === 'POST') {
    [$type, $amount, $date, $cat, $note, $pay, $rec] = validate_txn(input(), $uid);
    db()->prepare('INSERT INTO transactions (user_id,category_id,type,amount,txn_date,note,payment_method,is_recurring) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$uid, $cat, $type, $amount, $date, $note, $pay, $rec]);
    $warning = null;
    if ($type === 'expense') {
        $bs = budget_status($uid, $cat, substr($date, 0, 7));
        if ($bs && $bs['percent'] >= 80) {
            $n = db()->prepare('SELECT name FROM categories WHERE category_id=?');
            $n->execute([$cat]);
            $warning = ['category' => $n->fetch()['name']] + $bs;
        }
    }
    out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'warning' => $warning]);
}

if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    [$type, $amount, $date, $cat, $note, $pay, $rec] = validate_txn(input(), $uid);
    $st = db()->prepare('UPDATE transactions SET category_id=?,type=?,amount=?,txn_date=?,note=?,payment_method=?,is_recurring=? WHERE txn_id=? AND user_id=?');
    $st->execute([$cat, $type, $amount, $date, $note, $pay, $rec, $id, $uid]);
    $chk = db()->prepare('SELECT 1 FROM transactions WHERE txn_id=? AND user_id=?');
    $chk->execute([$id, $uid]);
    if (!$chk->fetch()) fail('Transaction not found.', 404);
    $warning = null;
    if ($type === 'expense') {
        $bs = budget_status($uid, $cat, substr($date, 0, 7));
        if ($bs && $bs['percent'] >= 80) {
            $n = db()->prepare('SELECT name FROM categories WHERE category_id=?');
            $n->execute([$cat]);
            $warning = ['category' => $n->fetch()['name']] + $bs;
        }
    }
    out(['ok' => true, 'warning' => $warning]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare('DELETE FROM transactions WHERE txn_id=? AND user_id=?');
    $st->execute([$id, $uid]);
    if (!$st->rowCount()) fail('Transaction not found.', 404);
    out(['ok' => true]);
}

fail('Method not allowed', 405);
