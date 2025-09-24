<?php
// Database Operations Test - to identify why updates/changes aren't working
require_once 'config.php';
requireLogin();

echo "<h1>Database Operations Diagnostic</h1>";
echo "<p>Testing database write operations to identify the issue...</p>";

$errors = [];
$successes = [];

// Test 1: Basic Database Write
echo "<h2>Test 1: Basic Database Write</h2>";
try {
    // Try to create a simple test table and insert data
    $test_sql = "CREATE TABLE IF NOT EXISTS db_test (id INT AUTO_INCREMENT PRIMARY KEY, test_data VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";
    if ($conn->query($test_sql)) {
        $successes[] = "Test table creation: SUCCESS";
        
        // Try to insert test data
        $insert_sql = "INSERT INTO db_test (test_data) VALUES ('Test from " . date('Y-m-d H:i:s') . "')";
        if ($conn->query($insert_sql)) {
            $successes[] = "Test data insertion: SUCCESS (ID: " . $conn->insert_id . ")";
        } else {
            $errors[] = "Test data insertion FAILED: " . $conn->error;
        }
    } else {
        $errors[] = "Test table creation FAILED: " . $conn->error;
    }
} catch (Exception $e) {
    $errors[] = "Database test exception: " . $e->getMessage();
}

// Test 2: CSRF Token Test
echo "<h2>Test 2: CSRF Protection Test</h2>";
$csrf_token = generateCSRFToken();
echo "<p>Generated CSRF Token: " . $csrf_token . "</p>";
echo "<p>Session CSRF Token: " . ($_SESSION['csrf_token'] ?? 'Not set') . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Form Submission Received:</h3>";
    echo "<p>POST Data: <pre>" . print_r($_POST, true) . "</pre></p>";
    
    $provided_token = $_POST['csrf_token'] ?? '';
    $csrf_valid = verifyCSRFToken($provided_token);
    
    echo "<p>CSRF Token Provided: " . ($provided_token ? 'YES' : 'NO') . "</p>";
    echo "<p>CSRF Token Valid: " . ($csrf_valid ? 'TRUE' : 'FALSE') . "</p>";
    
    if (!$csrf_valid) {
        $errors[] = "CSRF Token validation FAILED - this could be blocking your updates!";
    } else {
        $successes[] = "CSRF Token validation: SUCCESS";
        
        // Test 3: Simulate a real database update with CSRF protection
        if (isset($_POST['test_update'])) {
            try {
                $test_value = "Updated at " . date('Y-m-d H:i:s');
                $update_sql = "UPDATE db_test SET test_data = ? WHERE id = (SELECT id FROM (SELECT MAX(id) as id FROM db_test) as tmp)";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("s", $test_value);
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $successes[] = "Database UPDATE with CSRF protection: SUCCESS";
                } else {
                    $errors[] = "Database UPDATE failed: " . $conn->error . " (Affected rows: " . $stmt->affected_rows . ")";
                }
            } catch (Exception $e) {
                $errors[] = "UPDATE exception: " . $e->getMessage();
            }
        }
    }
}

// Test 3: Check existing tables and permissions
echo "<h2>Test 3: Existing Tables Check</h2>";
try {
    $tables = ['products', 'packages', 'orders', 'users', 'subscription_types'];
    foreach ($tables as $table) {
        $count_sql = "SELECT COUNT(*) as count FROM $table";
        $result = $conn->query($count_sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $successes[] = "Table '$table' accessible: " . $row['count'] . " records";
        } else {
            $errors[] = "Table '$table' access failed: " . $conn->error;
        }
    }
} catch (Exception $e) {
    $errors[] = "Tables check exception: " . $e->getMessage();
}

// Test 4: Check user permissions
echo "<h2>Test 4: Database User Permissions</h2>";
try {
    // Check what privileges the database user has
    $privileges_sql = "SHOW GRANTS";
    $result = $conn->query($privileges_sql);
    if ($result) {
        echo "<p><strong>Database User Privileges:</strong></p><ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    } else {
        $errors[] = "Could not check database privileges: " . $conn->error;
    }
} catch (Exception $e) {
    $errors[] = "Privileges check exception: " . $e->getMessage();
}

// Display Results
echo "<h2>Test Results Summary</h2>";

if (!empty($successes)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
    echo "<h3>✅ Successes:</h3><ul>";
    foreach ($successes as $success) {
        echo "<li>$success</li>";
    }
    echo "</ul></div>";
}

if (!empty($errors)) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
    echo "<h3>❌ Errors Found:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul></div>";
}

// Test Form
echo "<h2>Interactive Tests</h2>";
echo "<form method='POST' style='background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
echo "<p><strong>Test CSRF + Database Update:</strong></p>";
echo "<input type='text' name='test_data' placeholder='Enter test data' required style='padding: 5px; margin: 5px;'>";
echo "<input type='hidden' name='test_update' value='1'>";
echo "<button type='submit' style='padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 3px;'>Test Update Operation</button>";
echo "</form>";

// Cleanup option
echo "<h2>Cleanup</h2>";
echo "<p><a href='?cleanup=1' style='color: #dc3545;'>Clean up test table</a></p>";

if (isset($_GET['cleanup'])) {
    $conn->query("DROP TABLE IF EXISTS db_test");
    echo "<p style='color: green;'>Test table cleaned up.</p>";
}

// Navigation
echo "<h2>Navigation</h2>";
echo "<p><a href='index.php'>Back to Dashboard</a></p>";
echo "<p><a href='packages.php'>Try Packages Page</a></p>";
echo "<p><a href='products.php'>Try Products Page</a></p>";
echo "<p><a href='session_debug.php'>Session Debug</a></p>";
?>