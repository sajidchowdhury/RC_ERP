<?php
// config/config.php

// Dynamic Base URL (works with any folder name)
if (php_sapi_name() === 'cli' && empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
}
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_path = dirname($_SERVER['SCRIPT_NAME']);

define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/');
define('APP_NAME', 'Remote Center ERP');

// Clean base URL for user-facing links and assets (strips trailing /public if present)
$clean_base = preg_replace('#/public/?$#', '', rtrim(BASE_URL, '/')) . '/';
define('PUBLIC_URL', $clean_base);   // e.g. http://localhost/remote-center-erp/

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'osudlagb_remotecenter');

// Optional local overrides (FCM keys, etc.) — copy from config/local.php.example
$localConfig = __DIR__ . '/local.php';
if (is_readable($localConfig)) {
    require $localConfig;
}

if (!defined('FCM_SERVER_KEY')) {
    $fcmFromEnv = getenv('FCM_SERVER_KEY');
    define('FCM_SERVER_KEY', $fcmFromEnv !== false ? (string)$fcmFromEnv : '');
}

if (!defined('FCM_VAPID_KEY')) {
    $vapidFromEnv = getenv('FCM_VAPID_KEY');
    define('FCM_VAPID_KEY', $vapidFromEnv !== false ? (string)$vapidFromEnv : '');
}

$appEnv = getenv('APP_ENV');
if ($appEnv === false || $appEnv === '') {
    $appEnv = 'development';
}
define('APP_ENV', $appEnv);
define('APP_DEBUG', APP_ENV !== 'production');

if (!defined('SALES_STALE_DRAFT_DAYS')) {
    $staleDays = getenv('SALES_STALE_DRAFT_DAYS');
    define('SALES_STALE_DRAFT_DAYS', $staleDays !== false && $staleDays !== '' ? max(1, (int)$staleDays) : 14);
}

if (!defined('GL_RECONCILIATION_TOLERANCE')) {
    $reconTol = getenv('GL_RECONCILIATION_TOLERANCE');
    define('GL_RECONCILIATION_TOLERANCE', $reconTol !== false && $reconTol !== '' ? max(0.0001, (float)$reconTol) : 0.02);
}

if (!defined('RECON_ALERT_EMAIL')) {
    $reconEmail = getenv('RECON_ALERT_EMAIL');
    define('RECON_ALERT_EMAIL', $reconEmail !== false ? (string)$reconEmail : '');
}

if (!defined('SALES_DB_DRAFT_CARTS')) {
    $dbCarts = getenv('SALES_DB_DRAFT_CARTS');
    define('SALES_DB_DRAFT_CARTS', $dbCarts === '1' || $dbCarts === 'true');
}

if (!defined('APP_LOG_LEVEL')) {
    $logLevel = getenv('APP_LOG_LEVEL');
    define('APP_LOG_LEVEL', $logLevel !== false && $logLevel !== '' ? (string)$logLevel : '');
}

if (!defined('API_SUPPORTED_VERSIONS')) {
    $apiVersions = getenv('API_SUPPORTED_VERSIONS');
    define('API_SUPPORTED_VERSIONS', $apiVersions !== false ? (string)$apiVersions : '1');
}

error_reporting(E_ALL);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
?>