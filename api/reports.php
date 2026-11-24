<?php
require_once __DIR__ . '/bootstrap.php';
$u = require_login();
$uid = (int)$u['user_id'];
process_recurring($uid);
$pdo = db();
$month = $_GET['month'] ?? date('Y-m');
if (!valid_month($month)) fail('Invalid month.');

// CSV export for the chosen month
if (($_GET['action'] ?? '') === 'csv') {
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="spendly-' . $month . '.csv"');
    $st = $pdo->prepare("SELECT t.txn_date,t.type,c.name AS category,t.amount,t.payment_method,t.note
        FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id
        WHERE t.user_id=? AND DATE_FORMAT(t.txn_date,'%Y-%m')=? ORDER BY t.txn_date, t.txn_id");
    $st->execute([$uid, $month]);
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly
    fputcsv($f, ['Date', 'Type', 'Category', 'Amount', 'Payment method', 'Note']);
    foreach ($st->fetchAll() as $r) {
        // Prevent CSV/formula injection when opened in Excel
        $note = $r['note'] ?? '';
        if ($note !== '' && strpos('=+-@', $note[0]) !== false) $note = "'" . $note;
        fputcsv($f, [$r['txn_date'], $r['type'], $r['category'], $r['amount'], $r['payment_method'], $note]);
    }
    fclose($f);
    exit;
}

// Summary for the month
$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) AS income,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) AS expense
    FROM transactions WHERE user_id=? AND DATE_FORMAT(txn_date,'%Y-%m')=?");
$st->execute([$uid, $month]);
$s = $st->fetch();
$income = (float)$s['income'];
$expense = (float)$s['expense'];

$st = $pdo->prepare("SELECT COALESCE(c.name,'Uncategorised') AS name, SUM(t.amount) AS total
    FROM transactions t LEFT JOIN categories c ON c.category_id=t.category_id
    WHERE t.user_id=? AND t.type='expense' AND DATE_FORMAT(t.txn_date,'%Y-%m')=? GROUP BY COALESCE(c.name,'Uncategorised') ORDER BY total DESC");
$st->execute([$uid, $month]);
$cats = $st->fetchAll();

$days = (int)date('t', strtotime($month . '-01'));
if ($month === date('Y-m')) $days = (int)date('j');

// Last 6 months income vs expense ending at the chosen month
$series = [];
for ($i = 5; $i >= 0; $i--) {
    $mm = date('Y-m', strtotime($month . '-01 -' . $i . ' month'));
    $q = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) AS income,
        COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) AS expense
        FROM transactions WHERE user_id=? AND DATE_FORMAT(txn_date,'%Y-%m')=?");
    $q->execute([$uid, $mm]);
    $r = $q->fetch();
    $series[] = ['month' => $mm, 'income' => (float)$r['income'], 'expense' => (float)$r['expense']];
}

out(['month' => $month, 'income' => $income, 'expense' => $expense, 'net' => $income - $expense,
     'top_category' => $cats[0]['name'] ?? '—', 'avg_daily' => $days > 0 ? round($expense / $days, 2) : 0,
     'categories' => $cats, 'series' => $series]);
