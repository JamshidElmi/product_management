<?php
require_once 'config.php';
requireLogin();

echo "<h2>CSRF Token Debug</h2>";

// Generate token
$csrf_token = generateCSRFToken();

echo "<h3>Current Session Info:</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Generated CSRF Token: " . $csrf_token . "</p>";
echo "<p>Session CSRF Token: " . ($_SESSION['csrf_token'] ?? 'Not set') . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Request Received:</h3>";
    echo "<p>Provided Token: " . ($_POST['csrf_token'] ?? 'Not provided') . "</p>";
    echo "<p>Token Valid: " . (verifyCSRFToken($_POST['csrf_token'] ?? '') ? 'TRUE' : 'FALSE') . "</p>";
    echo "<p>Session Contents: <pre>" . print_r($_SESSION, true) . "</pre></p>";
}

echo "<h3>Test Form:</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
echo "<input type='text' name='test_field' placeholder='Test input' required>";
echo "<button type='submit'>Test CSRF Token</button>";
echo "</form>";

echo "<p><a href='packages.php'>Back to Packages</a></p>";
echo "<p><a href='session_debug.php'>Session Debug</a></p>";
echo "<p><a href='view_logs.php' target='_blank'>View Logs</a></p>";
?>