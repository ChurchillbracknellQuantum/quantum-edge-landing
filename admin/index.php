<?php
// ============================================================
// TITAN Demo Admin Dashboard — /admin/index.php
// URL: getquantumedgeai.com/admin/ (NOT linked from site)
// Password protected via PHP session.
// ============================================================

session_name('titan_adm');
session_start();

$configPath = __DIR__ . '/../api/config.php';
if (!file_exists($configPath)) {
    die('<h2 style="font-family:sans-serif;color:red;padding:40px;">Config file not found. Upload api/config.php to the server first.</h2>');
}
require_once $configPath;

// --- Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// --- Auth ---
$loggedIn = isset($_SESSION['titan_admin']) && $_SESSION['titan_admin'] === true;

if (!$loggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $ADMIN_PASSWORD) {
            $_SESSION['titan_admin'] = true;
            $loggedIn = true;
        } else {
            $loginError = 'Wrong password.';
        }
    }
    if (!$loggedIn) {
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TITAN Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0D0A00;color:#fff;font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#121000;border:1px solid #2A1F00;border-radius:12px;padding:40px;width:320px;text-align:center}
h1{color:#F5C842;font-size:1.3rem;margin-bottom:6px}p{color:#888;font-size:.83rem;margin-bottom:22px}
input[type=password]{width:100%;padding:12px;background:#1A1300;border:1px solid #2A1F00;border-radius:8px;color:#fff;font-size:1rem;margin-bottom:14px;outline:none}
input:focus{border-color:rgba(245,200,66,.4)}
button{width:100%;padding:12px;background:#F5C842;color:#000;font-weight:700;font-size:1rem;border:none;border-radius:8px;cursor:pointer}
button:hover{background:#E8A800}
.err{color:#ff6b6b;font-size:.83rem;margin-bottom:10px}
</style>
</head>
<body>
<div class="box">
  <h1>TITAN Admin</h1>
  <p>Demo trial dashboard</p>
  <?php if (isset($loginError)): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
  <form method="post">
    <input type="password" name="password" placeholder="Admin password" autofocus>
    <button type="submit">Sign In &rarr;</button>
  </form>
</div>
</body></html><?php
        exit;
    }
}

// --- Load trial data ---
$rows  = [];
$stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'runs' => 0];
$dbError = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS demo_trials (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fingerprint VARCHAR(64) NOT NULL UNIQUE,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_check DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        run_count INT UNSIGNED NOT NULL DEFAULT 1,
        INDEX idx_fp (fingerprint), INDEX idx_seen (first_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rows = $pdo->query(
        "SELECT *, TIMESTAMPDIFF(HOUR, first_seen, NOW()) AS hours_used FROM demo_trials ORDER BY first_seen DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $stats['total']++;
        $stats['runs'] += $r['run_count'];
        ($r['hours_used'] < $TRIAL_HOURS) ? $stats['active']++ : $stats['expired']++;
    }
} catch (PDOException $e) {
    $dbError = 'DB error: ' . htmlspecialchars($e->getMessage());
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TITAN Demo Admin</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0D0A00;color:#fff;font-family:-apple-system,sans-serif;padding-bottom:60px}
nav{background:#121000;border-bottom:1px solid #2A1F00;padding:15px 32px;display:flex;justify-content:space-between;align-items:center}
nav h1{color:#F5C842;font-size:1rem;font-weight:800}
nav a{color:#666;font-size:.8rem;text-decoration:none}nav a:hover{color:#F5C842}
.wrap{max-width:1080px;margin:0 auto;padding:28px 20px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.stat{background:#121000;border:1px solid #2A1F00;border-radius:10px;padding:18px;text-align:center}
.stat .n{font-size:2rem;font-weight:900;color:#F5C842}.stat .l{color:#666;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.stat.act .n{color:#2DB87A}.stat.exp .n{color:#ff6b6b}
.err{background:#200;border:1px solid #ff3333;color:#ff6b6b;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.83rem}
h2{color:#F5C842;font-size:.93rem;font-weight:700;margin-bottom:12px}
.empty{color:#444;text-align:center;padding:40px}
table{width:100%;border-collapse:collapse;background:#121000;border:1px solid #2A1F00;border-radius:10px;overflow:hidden}
thead{background:#1A1300}
th{padding:11px 14px;text-align:left;color:#666;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #2A1F00}
td{padding:10px 14px;border-bottom:1px solid rgba(42,31,0,.4);color:#aaa;font-size:.81rem}
tr:last-child td{border-bottom:none}tr:hover td{background:rgba(245,200,66,.02)}
.fp{font-family:monospace;color:#555;font-size:.75rem}
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700}
.badge.act{background:rgba(45,184,122,.12);color:#2DB87A;border:1px solid rgba(45,184,122,.25)}
.badge.exp{background:rgba(255,80,80,.1);color:#ff6b6b;border:1px solid rgba(255,80,80,.2)}
@media(max-width:600px){.stats{grid-template-columns:1fr 1fr}.wrap{padding:16px 12px}th,td{padding:8px 10px;font-size:.75rem}}
</style>
</head>
<body>
<nav>
  <h1>&#x1F4CA; TITAN Demo Admin</h1>
  <a href="?logout=1">Sign out</a>
</nav>
<div class="wrap">

<?php if ($dbError): ?><div class="err"><?= $dbError ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="l">Total Trials</div></div>
  <div class="stat act"><div class="n"><?= $stats['active'] ?></div><div class="l">Active (&lt;24h)</div></div>
  <div class="stat exp"><div class="n"><?= $stats['expired'] ?></div><div class="l">Expired</div></div>
  <div class="stat"><div class="n"><?= $stats['runs'] ?></div><div class="l">Total Runs</div></div>
</div>

<h2>All Demo Trials</h2>

<?php if (empty($rows) && !$dbError): ?>
<div class="empty">No trials recorded yet. Waiting for first demo EA run.</div>
<?php elseif (!empty($rows)): ?>
<table>
  <thead>
    <tr><th>#</th><th>Fingerprint</th><th>IP Address</th><th>First Seen</th><th>Last Check</th><th>Hours Used</th><th>Runs</th><th>Status</th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $i => $r):
    $hu = (int)$r['hours_used'];
    $isAct = $hu < $TRIAL_HOURS;
  ?>
    <tr>
      <td><?= ($stats['total'] - $i) ?></td>
      <td class="fp"><?= substr(htmlspecialchars($r['fingerprint']), 0, 10) ?>&hellip;</td>
      <td><?= htmlspecialchars($r['ip']) ?></td>
      <td><?= htmlspecialchars($r['first_seen']) ?></td>
      <td><?= htmlspecialchars($r['last_check']) ?></td>
      <td><?= $hu ?>h</td>
      <td><?= (int)$r['run_count'] ?></td>
      <td><span class="badge <?= $isAct ? 'act' : 'exp' ?>"><?= $isAct ? 'ACTIVE' : 'EXPIRED' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

</div>
</body>
</html>
