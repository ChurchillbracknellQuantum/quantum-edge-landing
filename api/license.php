<?php
// ============================================================
// TITAN Paid License API — getquantumedgeai.com/api/license.php
//
// Three actions:
//   GET  action=generate — FastSpring calls this to create a key
//   POST action=activate — EA calls on first run (locks key to account)
//   POST action=validate — EA calls on every startup after first
//
// Each license key:
//   Format: QE-XXXX-XXXX-XXXX-XXXX
//   Locks to ONE MT5 account + ONE MT4 account independently
//   Buyer can run MT4 + MT5 on separate accounts (they paid for both)
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Robots-Tag: noindex');

if (!file_exists(__DIR__ . '/config.php')) {
    echo json_encode(['status' => 'active', 'message' => 'Server not configured — grace mode']);
    exit;
}
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// ----------------------------------------------------------------
// ACTION: generate — FastSpring calls this when order completes
// GET params: secret, orderID, product, email
// Returns PLAIN TEXT key (FastSpring puts it in receipt email)
// ----------------------------------------------------------------
if ($action === 'generate') {
    $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
    if ($secret !== $FS_LICENSE_SECRET) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'UNAUTHORIZED';
        exit;
    }

    $orderId = isset($_GET['orderID']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['orderID']) : 'unknown';
    $product = isset($_GET['product']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['product']) : 'titan';
    $email   = isset($_GET['email'])   ? filter_var(trim($_GET['email']), FILTER_SANITIZE_EMAIL) : '';

    $tier = 'titan';
    if (strpos($product, 'suite') !== false) $tier = 'titan-suite';
    elseif (strpos($product, 'pro') !== false) $tier = 'titan-pro';

    try {
        $pdo = getDB();
        createLicenseTable($pdo);

        $key = generateLicenseKey();
        for ($i = 0; $i < 10; $i++) {
            $chk = $pdo->prepare("SELECT id FROM paid_licenses WHERE license_key = ?");
            $chk->execute([$key]);
            if (!$chk->fetch()) break;
            $key = generateLicenseKey();
        }

        $ins = $pdo->prepare(
            "INSERT INTO paid_licenses (license_key, product_tier, order_id, buyer_email) VALUES (?, ?, ?, ?)"
        );
        $ins->execute([$key, $tier, $orderId, $email]);

        header('Content-Type: text/plain; charset=utf-8');
        echo $key;

    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ERROR-RETRY';
    }
    exit;
}

// ----------------------------------------------------------------
// POST actions: activate / validate
// EA sends: action, key, account, platform (MT4|MT5)
// ----------------------------------------------------------------
$key      = isset($_POST['key'])      ? preg_replace('/[^A-Z0-9\-]/', '', strtoupper(trim($_POST['key']))) : '';
$account  = isset($_POST['account'])  ? preg_replace('/[^0-9]/', '', $_POST['account']) : '';
$platform = isset($_POST['platform']) ? (strtoupper(trim($_POST['platform'])) === 'MT4' ? 'MT4' : 'MT5') : 'MT5';

if (strlen($key) < 10 || strlen($account) < 1) {
    echo json_encode(['status' => 'invalid', 'message' => 'Missing license key or account number.']);
    exit;
}

try {
    $pdo = getDB();
    createLicenseTable($pdo);

    $stmt = $pdo->prepare("SELECT * FROM paid_licenses WHERE license_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            'status'  => 'invalid',
            'message' => 'License key not found. Check your purchase email. Support: churchillbracknellportfolio@gmail.com'
        ]);
        exit;
    }

    if ($row['status'] === 'suspended') {
        echo json_encode([
            'status'  => 'unauthorized',
            'message' => 'License suspended. Contact support: churchillbracknellportfolio@gmail.com'
        ]);
        exit;
    }

    $accountCol  = ($platform === 'MT4') ? 'mt4_account'   : 'mt5_account';
    $lockedAtCol = ($platform === 'MT4') ? 'mt4_locked_at' : 'mt5_locked_at';
    $locked      = $row[$accountCol];

    if (empty($locked)) {
        // First activation on this platform — lock permanently
        $upd = $pdo->prepare(
            "UPDATE paid_licenses SET {$accountCol} = ?, {$lockedAtCol} = NOW(),
             last_check = NOW(), check_count = check_count + 1 WHERE license_key = ?"
        );
        $upd->execute([$account, $key]);

        echo json_encode([
            'status'  => 'active',
            'message' => "License activated. {$platform} account {$account} locked. Welcome to TITAN!"
        ]);

    } elseif ($locked === $account) {
        $upd = $pdo->prepare(
            "UPDATE paid_licenses SET last_check = NOW(), check_count = check_count + 1 WHERE license_key = ?"
        );
        $upd->execute([$key]);

        echo json_encode(['status' => 'active', 'message' => 'License valid.']);

    } else {
        echo json_encode([
            'status'  => 'unauthorized',
            'message' => "This license is already locked to a different {$platform} account. Each license covers ONE account per platform. Support: churchillbracknellportfolio@gmail.com"
        ]);
    }

} catch (PDOException $e) {
    // DB unreachable — fail open so buyer is never blocked by server issue
    echo json_encode(['status' => 'active', 'message' => 'License check OK (fallback)']);
}

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------
function generateLicenseKey(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // excludes 0/O/1/I to avoid confusion
    $key   = 'QE';
    for ($g = 0; $g < 4; $g++) {
        $key .= '-';
        for ($c = 0; $c < 4; $c++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $key; // e.g. QE-A4KM-PX2R-BNVQ-7TLD
}

function getDB(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    return new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
}

function createLicenseTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS paid_licenses (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        license_key    VARCHAR(32)   NOT NULL UNIQUE,
        product_tier   ENUM('titan','titan-pro','titan-suite') NOT NULL DEFAULT 'titan',
        order_id       VARCHAR(64)   NOT NULL DEFAULT '',
        buyer_email    VARCHAR(255)  NOT NULL DEFAULT '',
        mt5_account    VARCHAR(32)   NOT NULL DEFAULT '',
        mt4_account    VARCHAR(32)   NOT NULL DEFAULT '',
        mt5_locked_at  DATETIME      NULL,
        mt4_locked_at  DATETIME      NULL,
        created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_check     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        check_count    INT UNSIGNED  NOT NULL DEFAULT 0,
        status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
        INDEX idx_key   (license_key),
        INDEX idx_order (order_id),
        INDEX idx_email (buyer_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
