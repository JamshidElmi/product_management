<?php
// Simplified server configuration for testing - use this temporarily if main config fails
// Copy your database credentials here and test

echo "<!-- Server Test Config -->\n";

// Start with basic session without complex domain logic
if (session_status() === PHP_SESSION_NONE) {
    // Basic session settings that work on most servers
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);  // Set to 0 for initial testing, change to 1 if HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');  // Leave empty for host-only cookies
    
    session_name('PRODUCT_MGMT_SESSION');
    session_start();
    
    error_log("[SERVER TEST] Session started: " . session_id());
}

// Basic error reporting for testing
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Database configuration - UPDATE THESE WITH YOUR ACTUAL SERVER CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'u5thlbnw7t4i_manaGer_useR_produCt');  // Update if different on server
define('DB_PASS', '1&H^2xpWyqCAJvm$');                   // Update if different on server
define('DB_NAME', 'u5thlbnw7t4i_product_manager');       // Update if different on server

// Security settings (simplified for testing)
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('SESSION_TIMEOUT', 3600);
define('ENABLE_IP_VALIDATION', false);  // Disable for testing
define('ENABLE_CSRF_PROTECTION', false); // Disable for testing

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("[SERVER TEST] DB Connection failed: " . $conn->connect_error);
        die("Database connection failed: " . $conn->connect_error);
    }
    error_log("[SERVER TEST] Database connected successfully");
} catch (Exception $e) {
    error_log("[SERVER TEST] DB Exception: " . $e->getMessage());
    die("Database exception: " . $e->getMessage());
}

// Simplified authentication function
function isLoggedIn() {
    error_log("[SERVER TEST] Checking login status");
    if (!isset($_SESSION['user_id'])) {
        error_log("[SERVER TEST] No user_id in session");
        return false;
    }
    
    // Check session timeout (simplified)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        error_log("[SERVER TEST] Session timeout");
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    error_log("[SERVER TEST] User is logged in: " . $_SESSION['user_id']);
    return true;
}

// Simplified require login
function requireLogin() {
    if (!isLoggedIn()) {
        error_log("[SERVER TEST] Redirecting to login");
        header("Location: login.php");
        exit();
    }
}

// Simplified login attempt tracking (create table if needed)
function checkLoginAttempts($ip_address) {
    return true; // Allow all attempts for testing
}

function recordFailedLogin($ip_address, $username = '') {
    // Simplified - just log
    error_log("[SERVER TEST] Failed login: $username from $ip_address");
}

function clearLoginAttempts($ip_address) {
    // Simplified
    error_log("[SERVER TEST] Clearing login attempts for $ip_address");
}

// Disabled functions for testing
function generateCSRFToken() { return 'test_token'; }
function verifyCSRFToken($token) { return true; }
function logSecurityEvent($type, $message, $user_id = null) { 
    error_log("[SERVER TEST SECURITY] $type: $message"); 
}
function logAdminAction($action, $details = '', $affected_id = null) { 
    error_log("[SERVER TEST ADMIN] $action: $details"); 
}

error_log("[SERVER TEST] Config loaded successfully");
?>