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
    ini_set('session.use_strict_mode', 1);
    // Use 'Lax' for better compatibility - 'None' is too restrictive for regular login flows
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_path', '/');
    // Compute cookie domain dynamically from the request host (strip port if present).
    // Leave blank for local to allow browser to accept cookies on localhost.
        $cookie_domain = '';
        if (defined('SESSION_COOKIE_FORCE_HOST_ONLY') && SESSION_COOKIE_FORCE_HOST_ONLY === true) {
            // Host-only: leave cookie domain blank so browser treats cookie as host-only
            $cookie_domain = '';
            error_log("[SESSION DEBUG] SESSION_COOKIE_FORCE_HOST_ONLY is enabled — using host-only cookie (no Domain attribute)");
        } else {
            // For production servers, use host-only cookies for better compatibility
            // Setting domain explicitly can cause cross-subdomain issues
            $cookie_domain = '';
            error_log("[SESSION DEBUG] Using host-only cookie for better server compatibility");
        }
    ini_set('session.cookie_domain', $cookie_domain);
    error_log("[SESSION DEBUG] Computed cookie settings - domain: " . ini_get('session.cookie_domain') . ", secure: " . ($is_https ? '1' : '0') . ", samesite: " . ini_get('session.cookie_samesite'));
    
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

// ===== LOAD ENVIRONMENT VARIABLES =====
/**
 * Simple .env file loader
 * Loads environment variables from .env file if it exists
 */
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Set as environment variable and define constant
            $_ENV[$key] = $value;
            putenv("$key=$value");
            
            // Define constants for backward compatibility
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Load environment variables
try {
    loadEnv();
} catch (Exception $e) {
    // Fallback to hardcoded values if .env file is missing (for backward compatibility)
    error_log("Warning: Could not load .env file - " . $e->getMessage());
    
    // Fallback database configuration
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_USER')) define('DB_USER', 'u5thlbnw7t4i_manaGer_useR_produCt');
    if (!defined('DB_PASS')) define('DB_PASS', '1&H^2xpWyqCAJvm$');
    if (!defined('DB_NAME')) define('DB_NAME', 'u5thlbnw7t4i_product_manager');
    
    // Fallback reCAPTCHA Configuration
    if (!defined('RECAPTCHA_SITE_KEY')) define('RECAPTCHA_SITE_KEY', '6LfSuNIrAAAAAL_yqGHflka0opcbMSTwJWxV6dFg');
    if (!defined('RECAPTCHA_SECRET_KEY')) define('RECAPTCHA_SECRET_KEY', '6LfSuNIrAAAAAHRxpHKoOsSstPqAdOvyqQjLNzef');
    
    // Fallback Security Configuration
    if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
    if (!defined('LOCKOUT_TIME')) define('LOCKOUT_TIME', 900);
    if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
    if (!defined('ENABLE_IP_VALIDATION')) define('ENABLE_IP_VALIDATION', true);
    if (!defined('SESSION_COOKIE_FORCE_HOST_ONLY')) define('SESSION_COOKIE_FORCE_HOST_ONLY', true);
}

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

// Function to check if user is logged in
function isLoggedIn() {
    error_log("[AUTH DEBUG] isLoggedIn() called. Session ID: " . session_id());
    error_log("[AUTH DEBUG] Session contents: " . print_r($_SESSION, true));
    
    if (!isset($_SESSION['user_id'])) {
        error_log("[AUTH DEBUG] No user_id in session");
        return false;
    }
    
    error_log("[AUTH DEBUG] User ID found: " . $_SESSION['user_id']);
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        error_log("[AUTH DEBUG] Session timeout - last activity: " . $_SESSION['last_activity'] . ", current time: " . time() . ", timeout: " . SESSION_TIMEOUT);
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    error_log("[AUTH DEBUG] Updated last_activity to: " . $_SESSION['last_activity']);
    
    // Validate IP address if enabled
    if (ENABLE_IP_VALIDATION && isset($_SESSION['ip_address'])) {
        $current_ip = $_SERVER['REMOTE_ADDR'];
        $session_ip = $_SESSION['ip_address'];
        error_log("[AUTH DEBUG] IP validation - Session IP: $session_ip, Current IP: $current_ip");
        
        // Skip IP validation if remote IP equals server IP (proxy/load balancer scenario)
        $server_ip = $_SERVER['SERVER_ADDR'] ?? '';
        if ($current_ip === $server_ip) {
            error_log("[AUTH DEBUG] Remote IP equals server IP ($current_ip), skipping IP validation (proxy environment)");
        } elseif ($session_ip !== $current_ip) {
            error_log("[AUTH DEBUG] IP mismatch, destroying session");
            session_destroy();
            return false;
        }
    }
    
    error_log("[AUTH DEBUG] isLoggedIn() returning true");
    return true;
}

// Function to redirect if not logged in
function requireLogin() {
    $loggedIn = isLoggedIn();
    error_log("[AUTH DEBUG] requireLogin() called. isLoggedIn result: " . ($loggedIn ? 'true' : 'false'));
    if (!$loggedIn) {
        error_log("[AUTH DEBUG] requireLogin() redirecting to login.php");
        header("Location: login.php");
        exit();
    }
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

// Function to check login attempts and lockout
function checkLoginAttempts($ip_address) {
    global $conn;
    // Ensure the table exists to avoid SELECT errors
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255),
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
    if (!$stmt) {
        // If prepare fails, assume no lockout and allow attempt (avoid fatal)
        return true;
    }
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $attempts = $row['attempts'];
        $last_attempt = strtotime($row['last_attempt']);
        
        // Check if lockout period has expired
        if (time() - $last_attempt > LOCKOUT_TIME) {
            // Reset attempts
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
            return true;
        }
        
        // Check if max attempts exceeded
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            return false;
        }
    }
    
    return true;
}

// Function to record failed login attempt
function recordFailedLogin($ip_address, $username = '') {
    global $conn;
    
    // Create table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255),
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_ip (ip_address)
    )");
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username, attempts) VALUES (?, ?, 1) 
                           ON DUPLICATE KEY UPDATE attempts = attempts + 1, username = ?, last_attempt = CURRENT_TIMESTAMP");
    $stmt->bind_param("sss", $ip_address, $username, $username);
    $stmt->execute();
    
    // Log security event
    logSecurityEvent('failed_login', "Failed login attempt from IP: $ip_address, Username: $username");
}

// Function to clear login attempts on successful login
function clearLoginAttempts($ip_address) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ip_address);
    $stmt->execute();
}

// Function to log security events
function logSecurityEvent($event_type, $message, $user_id = null) {
    global $conn;
    
    // Create security_log table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS security_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_type (event_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Ensure required columns exist (backwards-compatible additions)
    $requiredColumns = [
        'message' => 'TEXT',
        'user_id' => 'INT',
        'ip_address' => 'VARCHAR(45)',
        'user_agent' => 'TEXT'
    ];

    $columnsRes = $conn->query("SHOW COLUMNS FROM security_log");
    $existing = [];
    if ($columnsRes) {
        while ($col = $columnsRes->fetch_assoc()) {
            $existing[$col['Field']] = true;
        }
    }

    foreach ($requiredColumns as $col => $type) {
        if (!isset($existing[$col])) {
            // Add the missing column
            $sql = "ALTER TABLE security_log ADD COLUMN $col $type";
            // For user_id we allow NULL, for ip_address set NULL default, message/user_agent as TEXT
            if ($col === 'user_id') $sql .= " NULL";
            if ($col === 'ip_address') $sql .= " NULL";
            $conn->query($sql);
        }
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $conn->prepare("INSERT INTO security_log (event_type, message, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssiss", $event_type, $message, $user_id, $ip_address, $user_agent);
        $stmt->execute();
    } else {
        // If prepare fails, attempt a safe INSERT with minimal fields to avoid fatal
        $safeStmt = $conn->prepare("INSERT INTO security_log (event_type) VALUES (?)");
        if ($safeStmt) {
            $safeStmt->bind_param("s", $event_type);
            $safeStmt->execute();
        }
    }
}

// Function to log admin actions
function logAdminAction($action, $details = '', $affected_id = null) {
    if (!isLoggedIn()) return;
    
    global $conn;
    
    // Create admin_log table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS admin_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        affected_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )");
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO admin_log (user_id, action, details, affected_id, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $user_id, $action, $details, $affected_id, $ip_address);
    $stmt->execute();
}

// ===================== NEW USER MANAGEMENT SYSTEM =====================

// Function to get current user data
function getCurrentUser() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT id, username, email, role, status, last_login FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

// Function to check if user has a specific role
function hasRole($required_role) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $roles = ['user' => 1, 'admin' => 2, 'super_admin' => 3];
    $user_level = $roles[$user['role']] ?? 0;
    $required_level = $roles[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Function to check module permissions
function hasPermission($module, $action = 'view') {
    global $conn;
    
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Super admin has all permissions
    if ($user['role'] === 'super_admin') return true;
    
    $column = '';
    switch($action) {
        case 'view': $column = 'can_view'; break;
        case 'create': $column = 'can_create'; break;
        case 'edit': $column = 'can_edit'; break;
        case 'delete': $column = 'can_delete'; break;
        default: return false;
    }
    
    $stmt = $conn->prepare("SELECT $column FROM user_permissions WHERE user_id = ? AND module = ?");
    $stmt->bind_param('is', $user['id'], $module);
    $stmt->execute();
    $result = $stmt->get_result();
    $permission = $result->fetch_assoc();
    $stmt->close();
    
    return $permission ? (bool)$permission[$column] : false;
}

// Function to require specific role
function requireRole($required_role) {
    if (!hasRole($required_role)) {
        $_SESSION['error'] = "Access denied. You need $required_role privileges to access this page.";
        header("Location: login.php");
        exit();
    }
}

// Function to require specific permission
function requirePermission($module, $action = 'view') {
    if (!hasPermission($module, $action)) {
        $_SESSION['error'] = "Access denied. You don't have permission to $action $module.";
        header("Location: index.php");
        exit();
    }
}

// Function to get user's modules and permissions
function getUserModules($user_id = null) {
    global $conn;
    
    if (!$user_id) {
        $user = getCurrentUser();
        if (!$user) return [];
        $user_id = $user['id'];
    }
    
    // Super admin gets all modules
    $user_data = getCurrentUser();
    if ($user_data && $user_data['role'] === 'super_admin') {
        $stmt = $conn->prepare("SELECT module_key as module, module_name, 1 as can_view, 1 as can_create, 1 as can_edit, 1 as can_delete FROM system_modules WHERE is_active = 1 ORDER BY sort_order");
        $stmt->execute();
        $result = $stmt->get_result();
        $modules = [];
        while ($row = $result->fetch_assoc()) {
            $modules[] = $row;
        }
        $stmt->close();
        return $modules;
    }
    
    // Regular users get only their assigned permissions
    $stmt = $conn->prepare("
        SELECT sm.module_key as module, sm.module_name, 
               COALESCE(up.can_view, 0) as can_view,
               COALESCE(up.can_create, 0) as can_create, 
               COALESCE(up.can_edit, 0) as can_edit,
               COALESCE(up.can_delete, 0) as can_delete
        FROM system_modules sm 
        LEFT JOIN user_permissions up ON sm.module_key = up.module AND up.user_id = ?
        WHERE sm.is_active = 1 
        ORDER BY sm.sort_order
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
    $stmt->close();
    
    return $modules;
}

// Function to authenticate user with new system
function authenticateUser($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && password_verify($password, $user['password'])) {
        // Update last login
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->bind_param('i', $user['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    return false;
}

// Function to create new user (super admin only)
function createUser($username, $email, $password, $role = 'user') {
    global $conn;
    
    if (!hasRole('super_admin')) {
        return false;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $created_by = getCurrentUser()['id'];
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssi', $username, $email, $hashedPassword, $role, $created_by);
    $success = $stmt->execute();
    $new_user_id = $conn->insert_id;
    $stmt->close();
    
    return $success ? $new_user_id : false;
}

// Function to set user permissions
function setUserPermissions($user_id, $module, $permissions) {
    global $conn;
    
    if (!hasRole('super_admin')) {
        return false;
    }
    
    // Convert permissions to integers and store in variables (required for bind_param by reference)
    $can_view = $permissions['view'] ? 1 : 0;
    $can_create = $permissions['create'] ? 1 : 0;
    $can_edit = $permissions['edit'] ? 1 : 0;
    $can_delete = $permissions['delete'] ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO user_permissions (user_id, module, can_view, can_create, can_edit, can_delete) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           can_view = VALUES(can_view),
                           can_create = VALUES(can_create), 
                           can_edit = VALUES(can_edit),
                           can_delete = VALUES(can_delete)");
    
    $stmt->bind_param('isiiii', 
        $user_id, 
        $module,
        $can_view,
        $can_create,
        $can_edit,
        $can_delete
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

?>