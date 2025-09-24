<?php
// Quick Update Test - to identify why updates aren't working
require_once 'config.php';
requireLogin();

echo "<h1>Quick Database Update Test</h1>";
echo "<p>Let's test exactly what happens when you try to update data...</p>";

// Check recent logs first
$log_file = __DIR__ . '/logs/php-error.log';
if (file_exists($log_file)) {
    echo "<h2>Recent Log Entries (Last 20 lines):</h2>";
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -20);
    echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: scroll;'>";
    foreach ($recent_logs as $log) {
        if (strpos($log, 'PACKAGES DEBUG') !== false || strpos($log, 'ERROR') !== false || strpos($log, 'CSRF') !== false) {
            echo "<div style='color: #dc3545;'>" . htmlspecialchars($log) . "</div>";
        } else {
            echo "<div>" . htmlspecialchars($log) . "</div>";
        }
    }
    echo "</div>";
}

// Test CSRF functionality
$csrf_token = generateCSRFToken();
echo "<h2>CSRF Test Results:</h2>";
echo "<p>✅ CSRF Token Generated: " . substr($csrf_token, 0, 20) . "...</p>";
echo "<p>✅ Session Token: " . substr($_SESSION['csrf_token'] ?? 'MISSING', 0, 20) . "...</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffc107; border-radius: 4px; margin: 10px 0;'>";
    echo "<h3>📝 Form Submission Received!</h3>";
    
    $provided_token = $_POST['csrf_token'] ?? '';
    echo "<p><strong>Provided Token:</strong> " . substr($provided_token, 0, 20) . "...</p>";
    echo "<p><strong>Token Match:</strong> " . ($provided_token === $_SESSION['csrf_token'] ? '✅ YES' : '❌ NO') . "</p>";
    
    $csrf_valid = verifyCSRFToken($provided_token);
    echo "<p><strong>CSRF Validation:</strong> " . ($csrf_valid ? '✅ PASSED' : '❌ FAILED') . "</p>";
    
    if ($csrf_valid) {
        echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0;'>";
        echo "<h4>🎉 CSRF Protection Working! Now testing database update...</h4>";
        
        try {
            // Test a simple database update
            $test_data = $_POST['test_field'] ?? 'Default test data';
            
            // First, create a test record
            $insert_sql = "INSERT INTO packages (name, description, price, item_number, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $test_name = "TEST UPDATE " . date('H:i:s');
            $test_desc = "Test description: " . $test_data;
            $test_price = 99.99;
            $test_item = 'TEST-' . time();
            
            $stmt->bind_param("ssds", $test_name, $test_desc, $test_price, $test_item);
            
            if ($stmt->execute()) {
                $test_id = $conn->insert_id;
                echo "<p>✅ <strong>INSERT Test:</strong> SUCCESS (ID: $test_id)</p>";
                
                // Now test update
                $update_sql = "UPDATE packages SET description = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $updated_desc = "UPDATED: " . $test_data . " at " . date('H:i:s');
                $update_stmt->bind_param("si", $updated_desc, $test_id);
                
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    echo "<p>✅ <strong>UPDATE Test:</strong> SUCCESS (Affected rows: " . $update_stmt->affected_rows . ")</p>";
                    
                    // Clean up test record
                    $delete_sql = "DELETE FROM packages WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $test_id);
                    $delete_stmt->execute();
                    echo "<p>✅ <strong>DELETE Test:</strong> SUCCESS (Cleanup completed)</p>";
                    
                    echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
                    echo "<h4>🎯 Conclusion: Database operations are working perfectly!</h4>";
                    echo "<p>The issue might be:</p>";
                    echo "<ul>";
                    echo "<li>Specific form validation failing</li>";
                    echo "<li>JavaScript preventing form submission</li>";
                    echo "<li>Redirect happening before you see the result</li>";
                    echo "<li>Specific table or field causing issues</li>";
                    echo "</ul>";
                    echo "</div>";
                    
                } else {
                    echo "<p>❌ <strong>UPDATE Test:</strong> FAILED - " . $conn->error . "</p>";
                }
            } else {
                echo "<p>❌ <strong>INSERT Test:</strong> FAILED - " . $conn->error . "</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ <strong>Database Exception:</strong> " . $e->getMessage() . "</p>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0;'>";
        echo "<h4>❌ CSRF Protection Failed!</h4>";
        echo "<p>This could be why your updates aren't working. The form submissions are being blocked by CSRF protection.</p>";
        echo "</div>";
    }
    echo "</div>";
}

// Test form
echo "<h2>🧪 Test Update Operation</h2>";
echo "<form method='POST' style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px;'>";
echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
echo "<div style='margin: 10px 0;'>";
echo "<label>Test Data:</label><br>";
echo "<input type='text' name='test_field' value='Test update at " . date('H:i:s') . "' style='width: 300px; padding: 5px;'>";
echo "</div>";
echo "<button type='submit' style='background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px;'>Test Database Update</button>";
echo "</form>";

echo "<h2>🔍 Troubleshooting Questions</h2>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 4px;'>";
echo "<p><strong>When you try to update data in your app:</strong></p>";
echo "<ol>";
echo "<li>Do you get any error messages?</li>";
echo "<li>Does the page refresh but no changes are saved?</li>";
echo "<li>Are you redirected back to the form?</li>";
echo "<li>Which specific operations are failing? (Add new products/packages, edit existing ones, delete items?)</li>";
echo "</ol>";
echo "</div>";

echo "<h2>📋 Next Steps</h2>";
echo "<p><a href='packages.php' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Try Adding a Package</a></p>";
echo "<p><a href='products.php' style='background: #17a2b8; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Try Adding a Product</a></p>";
echo "<p><a href='database_operations_test.php' style='background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Full Database Test</a></p>";
?>