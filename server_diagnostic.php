<?php
// Server Diagnostic Tool - to be run on the actual server
// This file helps identify server-specific issues

echo "<h1>Server Environment Diagnostic</h1>";
echo "<p>Current Time: " . date('Y-m-d H:i:s T') . "</p>";

// 1. PHP Environment
echo "<h2>1. PHP Environment</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";

// 2. SSL/HTTPS Detection
echo "<h2>2. SSL/HTTPS Detection</h2>";
echo "<p>HTTPS (direct): " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'YES' : 'NO') . "</p>";
echo "<p>SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'Unknown') . "</p>";
echo "<p>X-Forwarded-Proto: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'Not set') . "</p>";
echo "<p>X-Forwarded-SSL: " . ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'Not set') . "</p>";
echo "<p>CF-Visitor: " . ($_SERVER['HTTP_CF_VISITOR'] ?? 'Not set') . "</p>";

// Auto-detect HTTPS (same logic as config.php)
$is_https = false;
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
    $is_https = true;
}
$xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$xfs = strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
if ($xfp === 'https' || $xfs === 'on' || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false)) {
    $is_https = true;
}
echo "<p><strong>Detected HTTPS Status: " . ($is_https ? 'YES' : 'NO') . "</strong></p>";

// 3. Host and Domain Information
echo "<h2>3. Host and Domain Information</h2>";
echo "<p>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p>SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "</p>";
echo "<p>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</p>";
echo "<p>Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>";
echo "<p>Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'Unknown') . "</p>";

// Check if it's local
$host = $_SERVER['HTTP_HOST'] ?? '';
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local = preg_match('/^(localhost|127\.|::1)/', $host) || in_array($remote, ['127.0.0.1', '::1']);
echo "<p><strong>Detected as Local: " . ($is_local ? 'YES' : 'NO') . "</strong></p>";

// 4. Directory and File Permissions
echo "<h2>4. Directory and File Permissions</h2>";
$currentDir = __DIR__;
echo "<p>Current Directory: $currentDir</p>";
echo "<p>Directory Writable: " . (is_writable($currentDir) ? 'YES' : 'NO') . "</p>";

$logsDir = $currentDir . '/logs';
echo "<p>Logs Directory: $logsDir</p>";
echo "<p>Logs Directory Exists: " . (is_dir($logsDir) ? 'YES' : 'NO') . "</p>";
echo "<p>Logs Directory Writable: " . (is_writable($logsDir) ? 'YES' : 'NO') . "</p>";

// Test file write
$testFile = $logsDir . '/server_test.log';
$writeTest = @file_put_contents($testFile, "Server test: " . date('c') . "\n");
echo "<p>Log Write Test: " . ($writeTest !== false ? 'SUCCESS' : 'FAILED') . "</p>";
if ($writeTest !== false) {
    @unlink($testFile);
}

// 5. Session Configuration (before starting session)
echo "<h2>5. Session Configuration (Pre-Start)</h2>";
echo "<p>Session Status: " . session_status() . " (0=disabled, 1=none, 2=active)</p>";
echo "<p>Session Save Path: " . session_save_path() . "</p>";
echo "<p>Session Save Path Writable: " . (is_writable(session_save_path()) ? 'YES' : 'NO') . "</p>";

// 6. Database Connection Test
echo "<h2>6. Database Connection Test</h2>";
try {
    // Use the same credentials as config.php
    $test_conn = new mysqli('localhost', 'u5thlbnw7t4i_manaGer_useR_produCt', '1&H^2xpWyqCAJvm$', 'u5thlbnw7t4i_product_manager');
    
    if ($test_conn->connect_error) {
        echo "<p style='color: red;'>❌ Database Connection FAILED: " . $test_conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Database Connection SUCCESS</p>";
        
        // Test users table
        $result = $test_conn->query("SELECT COUNT(*) as count FROM users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Users Table Accessible: YES (Count: " . $row['count'] . ")</p>";
        } else {
            echo "<p style='color: red;'>Users Table Access Failed: " . $test_conn->error . "</p>";
        }
        
        $test_conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database Test Exception: " . $e->getMessage() . "</p>";
}

// 7. Session Test (Start session using app logic)
echo "<h2>7. Session Test</h2>";

// Replicate the session configuration from config.php
if (session_status() === PHP_SESSION_NONE) {
    // Use the same session configuration logic as config.php
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', $is_https ? 'None' : 'Lax');
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_path', '/');
    
    // Compute cookie domain
    $cookie_domain = '';
    if (!$is_local && !empty($_SERVER['HTTP_HOST'])) {
        $hostOnly = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
        $cookie_domain = $hostOnly;
    }
    ini_set('session.cookie_domain', $cookie_domain);
    
    session_name('PRODUCT_MGMT_SESSION');
    $session_started = session_start();
    
    echo "<p>Session Start: " . ($session_started ? 'SUCCESS' : 'FAILED') . "</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>Session Name: " . session_name() . "</p>";
} else {
    echo "<p>Session Already Active</p>";
}

// 8. Session Cookie Settings
echo "<h2>8. Session Cookie Settings</h2>";
$cookie_params = session_get_cookie_params();
echo "<p>Cookie Lifetime: " . $cookie_params['lifetime'] . "</p>";
echo "<p>Cookie Path: " . $cookie_params['path'] . "</p>";
echo "<p>Cookie Domain: " . $cookie_params['domain'] . "</p>";
echo "<p>Cookie Secure: " . ($cookie_params['secure'] ? 'YES' : 'NO') . "</p>";
echo "<p>Cookie HTTPOnly: " . ($cookie_params['httponly'] ? 'YES' : 'NO') . "</p>";
echo "<p>Cookie SameSite: " . ($cookie_params['samesite'] ?? 'Not set') . "</p>";

// 9. Test Session Write
echo "<h2>9. Session Write Test</h2>";
$_SESSION['server_test_time'] = time();
$_SESSION['server_test_data'] = 'Server diagnostic test';
echo "<p>Session Write Test: SUCCESS</p>";
echo "<p>Test Data Written: " . $_SESSION['server_test_data'] . "</p>";

// 10. PHP Extensions
echo "<h2>10. PHP Extensions</h2>";
$required_extensions = ['mysqli', 'curl', 'json', 'session'];
foreach ($required_extensions as $ext) {
    echo "<p>$ext: " . (extension_loaded($ext) ? 'LOADED' : 'MISSING') . "</p>";
}

// 11. Error Reporting Settings
echo "<h2>11. Error Reporting Settings</h2>";
echo "<p>Display Errors: " . ini_get('display_errors') . "</p>";
echo "<p>Log Errors: " . ini_get('log_errors') . "</p>";
echo "<p>Error Log Target: " . ini_get('error_log') . "</p>";

// 12. Current Cookies
echo "<h2>12. Current Cookies</h2>";
if (!empty($_COOKIE)) {
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
} else {
    echo "<p>No cookies found</p>";
}

// 13. Recommendations
echo "<h2>13. Recommendations</h2>";
echo "<div style='background: #f0f0f0; padding: 10px; border-left: 4px solid #007cba;'>";

if (!$is_https && !$is_local) {
    echo "<p>⚠️ <strong>HTTPS Issue:</strong> Server not detected as HTTPS. This may cause session cookie issues.</p>";
}

if (!is_writable($logsDir)) {
    echo "<p>⚠️ <strong>Logs Directory:</strong> Not writable. Check directory permissions.</p>";
}

if (!is_writable(session_save_path())) {
    echo "<p>⚠️ <strong>Session Save Path:</strong> Not writable. Sessions may not persist.</p>";
}

echo "<p>💡 <strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Run this diagnostic on your server</li>";
echo "<li>Check for any RED error messages above</li>";
echo "<li>Verify database connection works</li>";
echo "<li>Ensure proper file permissions on logs/ directory</li>";
echo "<li>Configure HTTPS properly if needed</li>";
echo "</ol>";
echo "</div>";

// 14. Quick Login Test Link
echo "<h2>14. Quick Tests</h2>";
echo "<p><a href='debug_test.php' style='background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Database Test</a></p>";
echo "<p><a href='session_debug.php' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Session Debug</a></p>";
echo "<p><a href='login.php' style='background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Try Login</a></p>";

echo "<hr>";
echo "<p><em>Generated on: " . date('Y-m-d H:i:s T') . "</em></p>";
?>