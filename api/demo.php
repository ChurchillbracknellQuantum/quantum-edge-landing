<?php
// ============================================================
// TITAN Demo License API — getquantumedgeai.com/api/demo.php
// Called by QuantumForex_TITAN_DEMO.mq5 via MT5 WebRequest
// POST params: fp (fingerprint string)
// Returns JSON: {"status":"active","hours_left":18} or {"status":"expired","hours_left":0}
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Robots-Tag: noindex');

// --- Fail open if config not uploaded yet ---
if (!file_exists(__DIR__ . '/config.php')) {
    echo json_encode(['status' => 'active', 'hours_left' => 24, 'message' => 'Server not configured yet']);
    exit;
}
require_once __DIR__ . '/config.php';

// --- Validate fingerprint ---
$fp = isset($_POST['fp']) ? trim($_POST['fp']) : '';
$fp = preg_replace('/[^a-zA-Z0-9_\-]/', '', $fp);

if (strlen($fp) < 3 || strlen($fp) > 64) {
    echo json_encode(['status' => 'active', 'hours_left' => 24, 'message' => 'Invalid request']);
    exit;
}

// --- Capture IP ---
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
    : $_SERVER['REMOTE_ADDR'];

// --- Connect to MariaDB ---
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );

    // Auto-create table on first ever call — zero manual DB setup needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS demo_trials (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fingerprint VARCHAR(64)  NOT NULL UNIQUE,
        ip          VARCHAR(45)  NOT NULL DEFAULT '',
        first_seen  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_check  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        run_count   INT UNSIGNED NOT NULL DEFAULT 1,
        INDEX idx_fp   (fingerprint),
        INDEX idx_seen (first_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $now      = time();
    $trialSec = $TRIAL_HOURS * 3600;

    // Look up existing trial
    $stmt = $pdo->prepare("SELECT first_seen, run_count FROM demo_trials WHERE fingerprint = ?");
    $stmt->execute([$fp]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // First time — create record
        $ins = $pdo->prepare("INSERT INTO demo_trials (fingerprint, ip, first_seen, last_check, run_count) VALUES (?, ?, NOW(), NOW(), 1)");
        $ins->execute([$fp, $ip]);

        echo json_encode([
            'status'     => 'active',
            'hours_left' => (int)$TRIAL_HOURS,
            'message'    => 'Trial started. ' . $TRIAL_HOURS . ' hours remaining.'
        ]);

    } else {
        // Existing trial — check expiry
        $firstSeen = strtotime($row['first_seen']);
        $elapsed   = $now - $firstSeen;
        $hoursLeft = (int)ceil(($trialSec - $elapsed) / 3600);

        // Update last_check + run_count
        $upd = $pdo->prepare("UPDATE demo_trials SET last_check = NOW(), run_count = run_count + 1 WHERE fingerprint = ?");
        $upd->execute([$fp]);

        if ($elapsed >= $trialSec) {
            echo json_encode([
                'status'     => 'expired',
                'hours_left' => 0,
                'message'    => 'Trial expired. Get TITAN at getquantumedgeai.com'
            ]);
        } else {
            echo json_encode([
                'status'     => 'active',
                'hours_left' => max(1, $hoursLeft),
                'message'    => 'Trial active. ' . max(1, $hoursLeft) . ' hours remaining.'
            ]);
        }
    }

} catch (PDOException $e) {
    // DB unreachable — fail open so legitimate users are not blocked
    echo json_encode([
        'status'     => 'active',
        'hours_left' => 24,
        'message'    => 'License check OK (fallback)'
    ]);
}
