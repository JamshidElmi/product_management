<?php
// ===== SESSION CONFIGURATION FIRST =====
// This MUST be set before any other code that might start a session
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings. Adjust automatically for local (HTTP) vs production (HTTPS).
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_local = preg_match('/^(localhost|127\.|::1)/', $host) || in_array($remote, ['127.0.0.1', '::1']);

    // Detect if connection is secure. Also honor common proxy headers (X-Forwarded-Proto, X-Forwarded-Ssl)
    $is_https = false;
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
        $is_https = true;
    }
    // Some hosts terminate TLS at a load balancer or proxy and set these headers
    $xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $xfs = strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
    if ($xfp === 'https' || $xfs === 'on' || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false)) {
        $is_https = true;
    }

    ini_set('session.cookie_httponly', 1);
    // Only require secure cookies when running under HTTPS
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    // SIMPLIFIED SESSION CONFIG FOR DEBUGGING - No complex cookie settings
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_path', '/');
    // Use basic session settings without complex domain/secure configurations
    
    // Set consistent session name across ALL pages
    session_name('PRODUCT_MGMT_SESSION');
    
    session_start();
    
    error_log("[SESSION DEBUG] Session started. ID: " . session_id() . ", Name: " . session_name());
    error_log("[SESSION DEBUG] Domain: " . ini_get('session.cookie_domain') . ", Path: " . ini_get('session.cookie_path'));
    
    // Regenerate session ID periodically (but not on every request)
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
        error_log("[SESSION DEBUG] Set initial last_regeneration: " . $_SESSION['last_regeneration']);
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Every 30 minutes
        error_log("[SESSION DEBUG] Regenerating session ID (30 min passed)");
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// ===== DATABASE AND APP CONFIGURATION =====
define('DB_HOST', 'localhost');
define('DB_USER', 'u5thlbnw7t4i_manaGer_useR_produCt');
define('DB_PASS', '1&H^2xpWyqCAJvm$');
define('DB_NAME', 'u5thlbnw7t4i_product_manager');

// reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY', '6LfSuNIrAAAAAL_yqGHflka0opcbMSTwJWxV6dFg');
define('RECAPTCHA_SECRET_KEY', '6LfSuNIrAAAAAHRxpHKoOsSstPqAdOvyqQjLNzef');

// Security Configuration - DISABLED FOR DEBUGGING
define('MAX_LOGIN_ATTEMPTS', 999999); // Effectively disabled
define('LOCKOUT_TIME', 0); // No lockout
define('SESSION_TIMEOUT', 86400); // 24 hours - very long timeout
define('ENABLE_IP_VALIDATION', false); // Disable IP validation

// Force host-only cookies for server compatibility
// This ensures cookies work properly on production servers
define('SESSION_COOKIE_FORCE_HOST_ONLY', true);

// TEMP: enable verbose error reporting for debugging on local; in production we log to file or syslog
$is_local = $is_local ?? (preg_match('/^(localhost|127\.|::1)/', $_SERVER['HTTP_HOST'] ?? '') ? true : false);
if ($is_local) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // In production hide display errors and report errors to log only
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

ini_set('log_errors', '1');

// Prefer app-local logs folder; fall back to syslog when not writable
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$logFile = $logsDir . '/php-error.log';
if (is_dir($logsDir) && is_writable($logsDir)) {
    ini_set('error_log', $logFile);
} else {
    // If we cannot write to the project logs, fallback to syslog to ensure messages are recorded
    ini_set('error_log', 'syslog');
}

// Diagnostic: record where PHP is sending error logs and attempt a small write test
error_log("[LOGGING DEBUG] error_log target: " . ini_get('error_log'));
$testFile = $logsDir . '/.write_test';
$writeOk = @file_put_contents($testFile, "write-test " . date('c')) !== false;
if ($writeOk) {
    error_log("[LOGGING DEBUG] Successfully wrote test file to: $testFile");
    @unlink($testFile);
} else {
    error_log("[LOGGING DEBUG] Failed to write test file to: $testFile (logs dir maybe not writable)");
}

// Fallback app-level log (temp): append to app_debug.log so we can always capture debug messages
$appLog = __DIR__ . '/logs/app_debug.log';
@file_put_contents($appLog, "[APP LOGGING] " . date('c') . " - Started\n", FILE_APPEND);

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    error_log("DB connect failed: " . $conn->connect_error);
    die("Connection failed.");
}

// Function to check if user is logged in - SIMPLIFIED FOR DEBUGGING
function isLoggedIn() {
    // DISABLED ALL SECURITY CHECKS FOR DEBUGGING - Just check user_id exists
    return isset($_SESSION['user_id']);
}

// Function to redirect if not logged in - SIMPLIFIED FOR DEBUGGING
function requireLogin() {
    // DISABLED FOR DEBUGGING - Allow access without login
    return true;
    
    /* ORIGINAL CODE DISABLED
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    */
}

// Function to verify reCAPTCHA
function verifyRecaptcha($response) {
    error_log("[RECAPTCHA DEBUG] verifyRecaptcha called with response length: " . strlen($response ?? ''));
    
    if (empty($response)) {
        error_log("[RECAPTCHA DEBUG] empty response");
        return false;
    }
    
    $data = array(
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    );
    
    error_log("[RECAPTCHA DEBUG] Sending data to Google: " . print_r($data, true));
    
    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($verify);
    if ($response === false) {
        error_log("[RECAPTCHA DEBUG] curl_exec failed: " . curl_error($verify));
        curl_close($verify);
        return false;
    } else {
        error_log("[RECAPTCHA DEBUG] google response: " . substr($response, 0, 1000));
    }
    curl_close($verify);
    
    $result = json_decode($response, true);
    error_log("[RECAPTCHA DEBUG] Decoded result: " . print_r($result, true));
    
    $success = isset($result['success']) && $result['success'] === true;
    error_log("[RECAPTCHA DEBUG] Returning: " . ($success ? 'true' : 'false'));
    
    return $success;
}

// Function to check login attempts and lockout - DISABLED FOR DEBUGGING
function checkLoginAttempts($ip_address) {
    // DISABLED FOR DEBUGGING - Always allow login attempts
    return true;
}

// Function to record failed login attempt - DISABLED FOR DEBUGGING
function recordFailedLogin($ip_address, $username = '') {
    // DISABLED FOR DEBUGGING - Do nothing
    return;
}

// Function to clear login attempts on successful login
function clearLoginAttempts($ip_address) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
}

// Function to log security events - DISABLED FOR DEBUGGING
function logSecurityEvent($event_type, $message, $user_id = null) {
    // DISABLED FOR DEBUGGING - Do nothing, no security logging
    return;
}

// Function to log admin actions - DISABLED FOR DEBUGGING
function logAdminAction($action, $details = '', $affected_id = null) {
    // DISABLED FOR DEBUGGING - Do nothing, no admin logging
    return;
}
?>