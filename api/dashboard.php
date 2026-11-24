<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
process_recurring($uid);
$pdo = db();
$month = date('Y-m');

// All-time balance + this-month income / expenses
$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE -amount END),0) AS balance FROM transactions WHERE user_id=?");
$st->execute([$uid]);
$balance = (float)$st->fetch()['balance'];

$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) AS income,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) AS expense
    FROM transactions WHERE user_id=? AND DATE_FORMAT(txn_date,'%Y-%m')=?");
$st->execute([$uid, $month]);
$m = $st->fetch();
$income = (float)$m['income'];
$expense = (float)$m['expense'];
$rate = $income > 0 ? (int)round(($income - $expense) / $income * 100) : 0;

// Spending trend: expenses per day for the last 30 days
$st = $pdo->prepare("SELECT txn_date AS d, SUM(amount) AS total FROM transactions
    WHERE user_id=? AND type='expense' AND txn_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY txn_date");
$st->execute([$uid]);
$byDay = [];
foreach ($st->fetchAll() as $r) $byDay[$r['d']] = (float)$r['total'];
$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trend[] = ['date' => $d, 'total' => $byDay[$d] ?? 0];
}

// Category split (this month)
$st = $pdo->prepare("SELECT COALESCE(c.name,'Uncategorised') AS name, SUM(t.amount) AS total
    FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id
    WHERE t.user_id=? AND t.type='expense' AND DATE_FORMAT(t.txn_date,'%Y-%m')=? GROUP BY COALESCE(c.name,'Uncategorised') ORDER BY total DESC");
$st->execute([$uid, $month]);
$cats = $st->fetchAll();

// Recent transactions
$st = $pdo->prepare("SELECT t.txn_id,t.type,t.amount,t.txn_date,t.note,c.name AS category
    FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id
    WHERE t.user_id=? ORDER BY t.txn_date DESC, t.txn_id DESC LIMIT 6");
$st->execute([$uid]);
$recent = $st->fetchAll();

// Budget alerts for this month (>= 80%)
$st = $pdo->prepare("SELECT c.name AS category, b.limit_amount,
    COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id=b.user_id AND t.category_id=b.category_id
              AND t.type='expense' AND DATE_FORMAT(t.txn_date,'%Y-%m')=b.month),0) AS spent
    FROM budgets b JOIN categories c ON c.category_id=b.category_id WHERE b.user_id=? AND b.month=?");
$st->execute([$uid, $month]);
$alerts = [];
foreach ($st->fetchAll() as $b) {
    $limit = (float)$b['limit_amount'];
    $spent = (float)$b['spent'];
    $pct = $limit > 0 ? (int)round($spent / $limit * 100) : 0;
    if ($pct >= 80) $alerts[] = ['category' => $b['category'], 'spent' => $spent, 'limit' => $limit, 'percent' => $pct];
}

out(['balance' => $balance, 'income' => $income, 'expense' => $expense, 'rate' => $rate,
     'trend' => $trend, 'categories' => $cats, 'recent' => $recent, 'alerts' => $alerts]);
