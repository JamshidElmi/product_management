<?php
require_once 'config.php';

echo "<h2>Session Debug Information</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Session Status: " . session_status() . " (1=disabled, 2=active, 0=none)</p>";

echo "<h3>Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Cookie Settings:</h3>";
echo "<p>session.cookie_httponly: " . ini_get('session.cookie_httponly') . "</p>";
echo "<p>session.cookie_secure: " . ini_get('session.cookie_secure') . "</p>";
echo "<p>session.cookie_samesite: " . ini_get('session.cookie_samesite') . "</p>";
echo "<p>session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "</p>";
echo "<p>session.cookie_path: " . ini_get('session.cookie_path') . "</p>";
echo "<p>session.cookie_domain: " . ini_get('session.cookie_domain') . "</p>";

echo "<h3>Server Information:</h3>";
echo "<p>HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No') . "</p>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "</p>";
echo "<p>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</p>";

echo "<h3>Authentication Status:</h3>";
$isLoggedIn = isLoggedIn();
echo "<p>isLoggedIn(): " . ($isLoggedIn ? 'TRUE' : 'FALSE') . "</p>";

if ($isLoggedIn) {
    echo "<p style='color: green;'>✓ User is authenticated</p>";
} else {
    echo "<p style='color: red;'>✗ User is NOT authenticated</p>";
}

echo "<h3>Actions:</h3>";
echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='index.php'>Go to Admin Panel</a></p>";
echo "<p><a href='navigation_test.php' style='color: blue; font-weight: bold;'>Navigation Test</a></p>";
echo "<p><a href='csrf_test.php' style='color: purple; font-weight: bold;'>CSRF Token Test</a></p>";
echo "<p><a href='logout.php' style='color: red; font-weight: bold;'>Logout (Test)</a></p>";
echo "<p><a href='session_cleanup.php' style='color: orange; font-weight: bold;'>Session Cleanup Tool</a></p>";
echo "<p><a href='debug_test.php'>Debug Test</a></p>";
echo "<p><a href='view_logs.php' target='_blank'>View Logs</a></p>";

// Test session write
if (isset($_GET['test_session'])) {
    $_SESSION['test_time'] = time();
    $_SESSION['test_value'] = 'Session write test at ' . date('Y-m-d H:i:s');
    echo "<p style='color: blue;'>✓ Test session data written</p>";
    echo "<script>setTimeout(() => window.location.href = 'session_debug.php', 1000);</script>";
}

echo "<p><a href='session_debug.php?test_session=1'>Test Session Write</a></p>";
?>