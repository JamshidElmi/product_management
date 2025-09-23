<?php
require_once 'config.php';

// DEBUG: Log logout attempt
error_log("[LOGOUT DEBUG] Logout initiated. Session ID: " . session_id());
error_log("[LOGOUT DEBUG] Session name: " . session_name());
if (isset($_SESSION['user_id'])) {
    error_log("[LOGOUT DEBUG] User ID: " . $_SESSION['user_id'] . " logging out");
    // Log successful logout for security
    logSecurityEvent('logout', "User " . $_SESSION['username'] . " logged out", $_SESSION['user_id']);
} else {
    error_log("[LOGOUT DEBUG] No user_id in session during logout");
}

// Log session contents before destruction
error_log("[LOGOUT DEBUG] Session before destroy: " . print_r($_SESSION, true));

// Unset all session variables
$_SESSION = array();

// Delete the session cookie with multiple domain variations
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    error_log("[LOGOUT DEBUG] Clearing session cookie with params: " . print_r($params, true));
    
    // Clear cookie with multiple domain variations to ensure it's deleted
    setcookie(session_name(), '', time() - 42000, '/', 'yfsuite.lubricityinnovations.com', true, true);
    setcookie(session_name(), '', time() - 42000, '/', '.yfsuite.lubricityinnovations.com', true, true);
    setcookie(session_name(), '', time() - 42000, '/', '', true, true);
    setcookie(session_name(), '', time() - 42000, '/', $_SERVER['HTTP_HOST'], true, true);
    
    error_log("[LOGOUT DEBUG] Session cookies cleared with multiple domain variations");
}

// Destroy the session
session_destroy();

error_log("[LOGOUT DEBUG] Session destroyed and cookie cleared, redirecting to login.php");

// Redirect to login page with cache-busting headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: login.php");
exit();
?> 