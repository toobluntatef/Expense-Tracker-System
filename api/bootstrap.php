<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): void { out(['error' => $msg], $code); }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            fail('Database connection failed. Check api/config.php and that MySQL is running.', 500);
        }
    }
    return $pdo;
}

function input(): array {
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return $data;
}

// Input sanitising: strip tags, trim, limit length (output is also escaped in the browser)
function clean($v, int $max = 255): string {
    return mb_substr(trim(strip_tags((string)$v)), 0, $max);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') return;
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $h)) fail('Invalid security token. Refresh the page.', 403);
}

function require_login(): array {
    if (empty($_SESSION['uid'])) fail('Not logged in', 401);
    check_csrf();
    $st = db()->prepare('SELECT user_id,name,email,role,status FROM users WHERE user_id=?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'active') { session_destroy(); fail('Account unavailable', 401); }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') fail('Admins only', 403);
    return $u;
}

function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

function valid_month(string $m): bool { return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m); }

// Recurring ("Repeat monthly") entries: create any missing monthly copies up to today.
function process_recurring(int $uid): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM transactions WHERE user_id=? AND is_recurring=1 AND parent_id IS NULL');
    $st->execute([$uid]);
    $today = new DateTime('today');
    foreach ($st->fetchAll() as $t) {
        $orig = new DateTime($t['txn_date']);
        $day = (int)$orig->format('j');
        $cur = new DateTime($orig->format('Y-m-01'));
        $cur->modify('+1 month');
        while ($cur <= $today) {
            $dim = (int)$cur->format('t');
            $d = new DateTime($cur->format('Y-m-') . str_pad((string)min($day, $dim), 2, '0', STR_PAD_LEFT));
            if ($d <= $today) {
                $chk = $pdo->prepare('SELECT 1 FROM transactions WHERE parent_id=? AND txn_date=?');
                $chk->execute([$t['txn_id'], $d->format('Y-m-d')]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare('INSERT INTO transactions (user_id,category_id,type,amount,txn_date,note,payment_method,is_recurring,parent_id) VALUES (?,?,?,?,?,?,?,0,?)');
                    $ins->execute([$uid, $t['category_id'], $t['type'], $t['amount'], $d->format('Y-m-d'), $t['note'], $t['payment_method'], $t['txn_id']]);
                }
            }
            $cur->modify('+1 month');
        }
    }
}

// Budget status for one category/month (used for the 80% / 100% warnings)
function budget_status(int $uid, int $catId, string $month): ?array {
    $st = db()->prepare('SELECT limit_amount FROM budgets WHERE user_id=? AND category_id=? AND month=?');
    $st->execute([$uid, $catId, $month]);
    $b = $st->fetch();
    if (!$b) return null;
    $sp = db()->prepare("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE user_id=? AND category_id=? AND type='expense' AND DATE_FORMAT(txn_date,'%Y-%m')=?");
    $sp->execute([$uid, $catId, $month]);
    $spent = (float)$sp->fetch()['s'];
    $limit = (float)$b['limit_amount'];
    return ['spent' => $spent, 'limit' => $limit, 'percent' => $limit > 0 ? (int)round($spent / $limit * 100) : 0];
}
