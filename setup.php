<?php
/**
 * Spendly one-time setup.
 *  - Creates the database + tables if they do not exist yet (runs database/spendly.sql)
 *  - Creates the admin account (admin@spendly.test / Admin@123)
 *  - Optional: add ?demo=1 to also create a demo user with sample data
 * DELETE THIS FILE after setup.
 */
require_once __DIR__ . '/api/config.php';
header('Content-Type: text/html; charset=utf-8');
$log = [];
$ok = true;

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='" . DB_NAME . "' AND table_name='users'")->fetchColumn();

    if (!$exists) {
        $sql = file_get_contents(__DIR__ . '/database/spendly.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
        $log[] = 'Created database and tables.';
    } else {
        $log[] = 'Tables already exist – schema left untouched.';
    }
    $pdo->exec('USE `' . DB_NAME . '`');

    $adminEmail = 'admin@spendly.test';
    $st = $pdo->prepare('SELECT 1 FROM users WHERE email=?');
    $st->execute([$adminEmail]);
    if (!$st->fetch()) {
        $pdo->prepare("INSERT INTO users (name,email,password_hash,role,status) VALUES ('Administrator',?,?,'admin','active')")
            ->execute([$adminEmail, password_hash('Admin@123', PASSWORD_DEFAULT)]);
        $log[] = 'Created admin account: admin@spendly.test / Admin@123';
    } else {
        $log[] = 'Admin account already exists.';
    }

    if (isset($_GET['demo'])) {
        $demoEmail = 'demo@spendly.test';
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email=?');
        $st->execute([$demoEmail]);
        if ($st->fetch()) {
            $log[] = 'Demo user already exists.';
        } else {
            $pdo->prepare("INSERT INTO users (name,email,password_hash,role,status) VALUES ('Demo Student',?,?,'user','active')")
                ->execute([$demoEmail, password_hash('Demo@1234', PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $cat = [];
            foreach ($pdo->query('SELECT category_id,name FROM categories WHERE user_id IS NULL') as $c) $cat[$c['name']] = (int)$c['category_id'];

            $ins = $pdo->prepare('INSERT INTO transactions (user_id,category_id,type,amount,txn_date,note,payment_method) VALUES (?,?,?,?,?,?,?)');
            mt_srand(42);
            for ($i = 75; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i day"));
                if (mt_rand(1, 10) > 3) $ins->execute([$uid, $cat['Food'], 'expense', mt_rand(80, 350), $d, 'Meal', 'Cash']);
                if (mt_rand(1, 10) > 5) $ins->execute([$uid, $cat['Transport'], 'expense', mt_rand(20, 120), $d, 'Bus / rickshaw fare', 'Cash']);
                if (mt_rand(1, 40) === 1) $ins->execute([$uid, $cat['Education'], 'expense', mt_rand(500, 1800), $d, 'Books & stationery', 'Card']);
                if (mt_rand(1, 40) === 1) $ins->execute([$uid, $cat['Entertainment'], 'expense', mt_rand(200, 700), $d, 'Movie / outing', 'bKash']);
            }
            for ($m = 2; $m >= 0; $m--) {
                $base = date('Y-m', strtotime("-$m month"));
                $ins->execute([$uid, $cat['Allowance'], 'income', 15000, "$base-02", 'Monthly allowance', 'Bank transfer']);
                $ins->execute([$uid, $cat['Part-time'], 'income', 6000, "$base-10", 'Part-time tutoring', 'bKash']);
                $ins->execute([$uid, $cat['Bills'], 'expense', 800, "$base-05", 'Internet bill', 'bKash']);
            }
            $pdo->prepare('INSERT INTO budgets (user_id,category_id,month,limit_amount) VALUES (?,?,?,?),(?,?,?,?),(?,?,?,?)')
                ->execute([$uid, $cat['Food'], date('Y-m'), 5000, $uid, $cat['Transport'], date('Y-m'), 2000, $uid, $cat['Education'], date('Y-m'), 3000]);
            $pdo->prepare('INSERT INTO goals (user_id,title,target_amount,saved_amount,target_date) VALUES (?,?,?,?,?),(?,?,?,?,?)')
                ->execute([$uid, 'New Laptop', 60000, 30000, date('Y-m-d', strtotime('+6 month')), $uid, 'Emergency Fund', 20000, 8000, null]);
            $log[] = 'Created demo user: demo@spendly.test / Demo@1234 (with sample transactions, budgets and goals).';
        }
    }
} catch (Throwable $e) {
    $ok = false;
    $log[] = 'ERROR: ' . $e->getMessage();
    $log[] = 'Check that MySQL is running and the credentials in api/config.php are correct.';
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Spendly setup</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><div class="auth"><div class="card box" style="max-width:560px">
<span class="logo">Spendly setup</span>
<h1><?= $ok ? 'Setup finished' : 'Setup failed' ?></h1>
<p class="sub"></p>
<?php foreach ($log as $l): ?><div class="<?= $ok ? 'form-ok' : 'form-alert' ?>"><?= htmlspecialchars($l) ?></div><?php endforeach; ?>
<?php if ($ok): ?>
<p class="muted" style="margin:14px 0">For security, <b>delete setup.php</b> now.</p>
<a class="btn block" href="login.html">Go to login</a>
<p class="alt"><a href="setup.php?demo=1">Also create demo user with sample data</a></p>
<?php endif; ?>
</div></div></body></html>
