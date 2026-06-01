<?php
// ============================================================
// TITAN License Server — Config Loader
// Real credentials live in ~/private/titan_secrets.php
// That folder is OUTSIDE public_html — GitHub deploy never touches it
// This file is safe to commit — contains NO real credentials
// ============================================================

$secretsFile = dirname(dirname(dirname(__FILE__))) . '/private/titan_secrets.php';

if (file_exists($secretsFile)) {
    require_once $secretsFile;
} else {
    // Secrets file not yet uploaded — APIs will fail gracefully until uploaded
    $DB_HOST            = '';
    $DB_NAME            = '';
    $DB_USER            = '';
    $DB_PASS            = '';
    $ADMIN_PASSWORD     = '';
    $TRIAL_HOURS        = 24;
    $FS_LICENSE_SECRET  = '';
}
