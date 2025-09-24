<?php
// Quick session config test - run this after making changes
require_once 'config.php';

echo "<h1>Session Configuration Test</h1>";
echo "<p>Testing the updated session settings...</p>";

echo "<h2>Current Session Cookie Settings:</h2>";
$cookie_params = session_get_cookie_params();
echo "<p><strong>Cookie Domain:</strong> " . ($cookie_params['domain'] ?: 'Host-only (good!)') . "</p>";
echo "<p><strong>Cookie Secure:</strong> " . ($cookie_params['secure'] ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Cookie HTTPOnly:</strong> " . ($cookie_params['httponly'] ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Cookie SameSite:</strong> " . ($cookie_params['samesite'] ?? 'Not set') . "</p>";
echo "<p><strong>Cookie Path:</strong> " . $cookie_params['path'] . "</p>";

echo "<h2>Session Info:</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . " (2 = active)</p>";

// Test session write
$_SESSION['config_test'] = time();
echo "<p><strong>Session Write Test:</strong> SUCCESS (wrote timestamp: " . $_SESSION['config_test'] . ")</p>";

echo "<h2>Expected Settings After Fix:</h2>";
echo "<div style='background: #e8f5e8; padding: 10px; border-left: 4px solid green; margin: 10px 0;'>";
echo "<p>✅ <strong>Cookie Domain:</strong> Should be empty (host-only)</p>";
echo "<p>✅ <strong>Cookie SameSite:</strong> Should be 'Lax'</p>";
echo "<p>✅ <strong>Cookie Secure:</strong> Should be 'YES' (you have HTTPS)</p>";
echo "</div>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li><strong>Upload the updated config.php to your server</strong></li>";
echo "<li><strong>Clear your browser cookies</strong> for yfsuite.lubricityinnovations.com</li>";
echo "<li><strong>Try logging in</strong> with your admin credentials</li>";
echo "<li><strong>Run session_debug.php</strong> to verify session persistence</li>";
echo "</ol>";

echo "<h2>Quick Test Links:</h2>";
echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Try Login</a></p>";
echo "<p><a href='session_debug.php' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Session Debug</a></p>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'><strong>✅ Already logged in as user: " . $_SESSION['user_id'] . "</strong></p>";
    echo "<p><a href='index.php' style='background: #ffc107; color: black; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Go to Dashboard</a></p>";
}
?>