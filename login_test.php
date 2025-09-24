<?php
// Simple login test - use this to test basic login functionality on server
require_once 'config_test.php'; // Use simplified config for testing

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("[LOGIN TEST] Attempting login for: $username");
    
    if (!empty($username) && !empty($password)) {
        try {
            $sql = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Success
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['login_time'] = time();
                        
                        error_log("[LOGIN TEST] Login successful for user: " . $user['id']);
                        $success = "Login successful! Session created.";
                        
                        // Show success message and provide navigation
                        echo "<div style='background: green; color: white; padding: 10px; margin: 10px 0;'>";
                        echo "✅ LOGIN SUCCESSFUL! User: " . htmlspecialchars($username);
                        echo "</div>";
                        echo "<p><a href='index.php' style='background: blue; color: white; padding: 8px 16px; text-decoration: none;'>Go to Dashboard</a></p>";
                        echo "<p><a href='navigation_test.php' style='background: green; color: white; padding: 8px 16px; text-decoration: none;'>Test Navigation</a></p>";
                        
                    } else {
                        $error = "Invalid password";
                        error_log("[LOGIN TEST] Invalid password for user: $username");
                    }
                } else {
                    $error = "User not found";
                    error_log("[LOGIN TEST] User not found: $username");
                }
                $stmt->close();
            } else {
                $error = "Database query failed: " . $conn->error;
                error_log("[LOGIN TEST] SQL prepare failed: " . $conn->error);
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            error_log("[LOGIN TEST] Exception: " . $e->getMessage());
        }
    } else {
        $error = "Please enter username and password";
    }
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    echo "<div style='background: lightgreen; padding: 10px; margin: 10px 0;'>";
    echo "ℹ️ Already logged in as: " . htmlspecialchars($_SESSION['username'] ?? 'Unknown');
    echo " (ID: " . $_SESSION['user_id'] . ")";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #e8f5e8; color: #2e7d32; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #e3f2fd; color: #1565c0; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .nav-links { margin: 20px 0; }
        .nav-links a { display: inline-block; margin: 5px 10px 5px 0; padding: 8px 12px; background: #f0f0f0; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Simple Login Test</h1>
    <div class="info">
        <p><strong>Purpose:</strong> This is a simplified login test page to verify basic authentication works on your server.</p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Session Status:</strong> <?php echo session_status(); ?> (2 = active)</p>
    </div>

    <?php if ($error): ?>
        <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">Test Login</button>
    </form>

    <div class="nav-links">
        <h3>Diagnostic Tools:</h3>
        <a href="server_diagnostic.php">🔍 Server Diagnostic</a>
        <a href="debug_test.php">🗄️ Database Test</a>
        <a href="session_debug.php">🔧 Session Debug</a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <br><br>
        <h3>Protected Pages (if logged in):</h3>
        <a href="index.php">📊 Dashboard</a>
        <a href="navigation_test.php">🧪 Navigation Test</a>
        <a href="logout.php" style="color: red;">🚪 Logout</a>
        <?php endif; ?>
    </div>

    <div class="info">
        <h3>Troubleshooting Steps:</h3>
        <ol>
            <li>First run <strong>Server Diagnostic</strong> to check environment</li>
            <li>Check <strong>Database Test</strong> to verify connection</li>
            <li>Test login with your credentials using this form</li>
            <li>If login works, try accessing the dashboard</li>
            <li>Check session behavior with <strong>Session Debug</strong></li>
        </ol>
    </div>
</body>
</html>