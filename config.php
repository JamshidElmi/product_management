<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'u5thlbnw7t4i_manaGer_useR_produCt');
define('DB_PASS', '1&H^2xpWyqCAJvm$');
define('DB_NAME', 'u5thlbnw7t4i_product_manager');

// reCAPTCHA Configuration
// You need to get these from https://www.google.com/recaptcha/admin
define('RECAPTCHA_SITE_KEY', '6LfyqM4rAAAAAERxDaKvmNLKaVECtfCUzZArsAUJ'); // Test key - replace with your actual site key
define('RECAPTCHA_SECRET_KEY', '6LfyqM4rAAAAAK5YYXcsi9nyob6pfTbYHZV6nxIH'); // Test key - replace with your actual secret key

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('ENABLE_IP_VALIDATION', true);
define('ENABLE_CSRF_PROTECTION', true);

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enhanced session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Validate IP address if enabled
    if (ENABLE_IP_VALIDATION && isset($_SESSION['ip_address'])) {
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            session_destroy();
            return false;
        }
    }
    
    return true;
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to verify reCAPTCHA
function verifyRecaptcha($response) {
    if (empty($response)) {
        return false;
    }
    
    $data = array(
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    );
    
    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($verify);
    curl_close($verify);
    
    $result = json_decode($response, true);
    return isset($result['success']) && $result['success'] === true;
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

// Function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
?> 