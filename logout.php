<?php
require_once 'config.php';

// DEBUG: Log logout attempt
error_log("[LOGOUT DEBUG] Logout initiated. Session ID: " . session_id());
if (isset($_SESSION['user_id'])) {
    error_log("[LOGOUT DEBUG] User ID: " . $_SESSION['user_id'] . " logging out");
} else {
    error_log("[LOGOUT DEBUG] No user_id in session during logout");
}

// Log session contents before destruction
error_log("[LOGOUT DEBUG] Session before destroy: " . print_r($_SESSION, true));

// Destroy the session
session_destroy();

error_log("[LOGOUT DEBUG] Session destroyed, redirecting to login.php");

// Redirect to login page
header("Location: login.php");
exit();
?> 