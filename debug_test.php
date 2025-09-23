<?php
// DEBUG: Test database connection and basic queries
require_once 'config.php';

echo "<h2>Database Connection Test</h2>";

// Test basic connection
if ($conn->connect_error) {
    echo "<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>Database connected successfully</p>";
}

// Test users table exists and has data
echo "<h3>Users Table Test</h3>";
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Users table exists. Row count: " . $row['count'] . "</p>";
        
        // Show users (without passwords)
        $users_result = $conn->query("SELECT id, username FROM users");
        if ($users_result && $users_result->num_rows > 0) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th></tr>";
            while ($user = $users_result->fetch_assoc()) {
                echo "<tr><td>" . $user['id'] . "</td><td>" . $user['username'] . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>Error querying users table: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
}

// Test session functionality
echo "<h3>Session Test</h3>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";
echo "<p>Session Contents: " . print_r($_SESSION, true) . "</p>";

// Test server environment
echo "<h3>Server Environment</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server IP: " . $_SERVER['SERVER_ADDR'] ?? 'unknown' . "</p>";
echo "<p>Remote IP: " . $_SERVER['REMOTE_ADDR'] ?? 'unknown' . "</p>";
echo "<p>HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No') . "</p>";

echo "<h3>Error Log Path</h3>";
echo "<p>Error log setting: " . ini_get('error_log') . "</p>";
echo "<p>Log errors enabled: " . (ini_get('log_errors') ? 'Yes' : 'No') . "</p>";
echo "<p>Display errors: " . (ini_get('display_errors') ? 'Yes' : 'No') . "</p>";

echo "<h3>Navigation</h3>";
echo "<p><a href='index.php'>Go to Admin Panel</a></p>";
echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='view_logs.php' target='_blank'>View Error Logs</a></p>";

?>