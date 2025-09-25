<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    // Log successful logout for security
    logSecurityEvent('logout', "User " . $_SESSION['username'] . " logged out", $_SESSION['user_id']);
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie with multiple domain variations
if (ini_get("session.use_cookies")) {
    // Clear cookie with multiple domain variations to ensure it's deleted
    setcookie(session_name(), '', time() - 42000, '/', 'yfsuite.lubricityinnovations.com', true, true);
    setcookie(session_name(), '', time() - 42000, '/', '.yfsuite.lubricityinnovations.com', true, true);
    setcookie(session_name(), '', time() - 42000, '/', '', true, true);
    setcookie(session_name(), '', time() - 42000, '/', $_SERVER['HTTP_HOST'], true, true);
}

// Destroy the session
session_destroy();

// Redirect to login page with cache-busting headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: login.php");
exit();
?> 