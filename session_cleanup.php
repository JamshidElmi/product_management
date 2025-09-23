<?php
// Include config.php to ensure consistent session configuration
require_once 'config.php';

echo "<h2>Session Cleanup Tool</h2>";

echo "<p>Current Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Current Session Data: <pre>" . print_r($_SESSION, true) . "</pre></p>";

if (isset($_GET['cleanup'])) {
    echo "<h3>Performing Session Cleanup...</h3>";
    
    // Unset all session variables
    $_SESSION = array();
    echo "<p>✓ Session variables cleared</p>";
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        echo "<p>✓ Clearing session cookie: " . session_name() . "</p>";
        
        // Clear with multiple domain variations to be sure
        setcookie(session_name(), '', time() - 42000, '/', 'yfsuite.lubricityinnovations.com', true, true);
        setcookie(session_name(), '', time() - 42000, '/', '.yfsuite.lubricityinnovations.com', true, true);
        setcookie(session_name(), '', time() - 42000, '/', '', true, true);
        setcookie(session_name(), '', time() - 42000, '/', $_SERVER['HTTP_HOST'], true, true);
    }
    
    // Destroy the session
    session_destroy();
    echo "<p>✓ Session destroyed</p>";
    
    echo "<p style='color: green; font-weight: bold;'>Session cleanup complete!</p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    echo "<p><a href='session_cleanup.php'>Check Session Status</a></p>";
    
} else {
    echo "<h3>Session Cleanup Options</h3>";
    echo "<p><strong>Warning:</strong> This will destroy your current session and log you out.</p>";
    echo "<p><a href='session_cleanup.php?cleanup=1' style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>CLEANUP ALL SESSIONS</a></p>";
    echo "<p><a href='session_debug.php'>Session Debug</a></p>";
    echo "<p><a href='login.php'>Login Page</a></p>";
}

echo "<h3>Session Cookie Information</h3>";
echo "<p>Cookie Name: " . session_name() . "</p>";
echo "<p>Cookie Domain: " . ini_get('session.cookie_domain') . "</p>";
echo "<p>Cookie Path: " . ini_get('session.cookie_path') . "</p>";
echo "<p>Cookie Secure: " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "</p>";
echo "<p>Cookie HTTP Only: " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "</p>";

echo "<h3>Server Information</h3>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "</p>";
echo "<p>HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No') . "</p>";
?>