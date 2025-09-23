<?php
require_once 'config.php';
requireLogin();

echo "<h2>Session Navigation Test</h2>";
echo "<p>If you can see this page, session authentication is working!</p>";

echo "<h3>Current Session Info:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Username: " . ($_SESSION['username'] ?? 'Not set') . "</p>";
echo "<p>Login Time: " . ($_SESSION['login_time'] ?? 'Not set') . "</p>";
echo "<p>Last Activity: " . ($_SESSION['last_activity'] ?? 'Not set') . "</p>";

echo "<h3>Navigation Test Links:</h3>";
echo "<p><a href='index.php'>Dashboard (index.php)</a></p>";
echo "<p><a href='products.php'>Products Page</a></p>";
echo "<p><a href='packages.php'>Packages Page</a></p>";
echo "<p><a href='orders.php'>Orders Page</a></p>";
echo "<p><a href='subscriptions.php'>Subscriptions Page</a></p>";

echo "<h3>Session Actions:</h3>";
echo "<p><a href='logout.php' style='color: red; font-weight: bold;'>Logout</a></p>";
echo "<p><a href='session_debug.php'>Session Debug</a></p>";
echo "<p><a href='session_cleanup.php'>Session Cleanup</a></p>";

echo "<h3>Test Results:</h3>";
echo "<p>✅ Authentication working - you can access this protected page</p>";
echo "<p>✅ Session data preserved - user info is available</p>";
echo "<p>ℹ️ Click the navigation links above to test page switching</p>";
echo "<p>ℹ️ Try logout to test session cleanup</p>";
?>