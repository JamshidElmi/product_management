<?php
// Real-time error monitoring - check this while trying to update data
require_once 'config.php';
requireLogin();

$log_file = __DIR__ . '/logs/php-error.log';
$app_log_file = __DIR__ . '/logs/app_debug.log';

echo "<h1>Real-Time Error Monitor</h1>";
echo "<p>This page shows recent errors. Keep this open and try to perform an update in another tab.</p>";

// Auto-refresh
echo "<script>setTimeout(function(){ window.location.reload(); }, 5000);</script>";
echo "<p><em>Auto-refreshing every 5 seconds...</em> <a href='?norefresh=1'>Stop auto-refresh</a></p>";

if (!isset($_GET['norefresh'])) {
    echo "<meta http-equiv='refresh' content='5'>";
}

echo "<h2>Recent PHP Errors (Last 30 lines):</h2>";
if (file_exists($log_file)) {
    $logs = file($log_file);
    $recent_logs = array_slice($logs, -30);
    
    echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; font-family: monospace; font-size: 11px; max-height: 400px; overflow-y: scroll;'>";
    foreach ($recent_logs as $i => $log) {
        $style = '';
        if (strpos($log, '[PACKAGES DEBUG]') !== false) {
            $style = 'color: #007bff; font-weight: bold;';
        } elseif (strpos($log, 'ERROR') !== false || strpos($log, 'Fatal') !== false) {
            $style = 'color: #dc3545; background: #f8d7da; font-weight: bold;';
        } elseif (strpos($log, 'CSRF') !== false) {
            $style = 'color: #856404; background: #fff3cd;';
        } elseif (strpos($log, 'LOGIN') !== false) {
            $style = 'color: #155724; background: #d4edda;';
        }
        
        echo "<div style='$style margin: 2px 0; padding: 2px;'>" . htmlspecialchars($log) . "</div>";
    }
    echo "</div>";
} else {
    echo "<p>Log file not found: $log_file</p>";
}

echo "<h2>Recent App Debug Logs (Last 20 lines):</h2>";
if (file_exists($app_log_file)) {
    $app_logs = file($app_log_file);
    $recent_app_logs = array_slice($app_logs, -20);
    
    echo "<div style='background: #e9ecef; padding: 10px; border: 1px solid #adb5bd; font-family: monospace; font-size: 11px; max-height: 200px; overflow-y: scroll;'>";
    foreach ($recent_app_logs as $log) {
        echo "<div style='margin: 2px 0;'>" . htmlspecialchars($log) . "</div>";
    }
    echo "</div>";
} else {
    echo "<p>App log file not found: $app_log_file</p>";
}

// Current time for reference
echo "<h2>Current Server Time:</h2>";
echo "<p><strong>" . date('Y-m-d H:i:s T') . "</strong></p>";

// Instructions
echo "<h2>📋 How to Use This Monitor:</h2>";
echo "<ol>";
echo "<li>Keep this page open</li>";
echo "<li>In another tab, go to <a href='packages.php' target='_blank'>Packages</a> and try to add or edit a package</li>";
echo "<li>Watch for new error messages appearing here</li>";
echo "<li>Look for lines containing 'PACKAGES DEBUG', 'ERROR', or 'CSRF'</li>";
echo "</ol>";

echo "<h2>Quick Links:</h2>";
echo "<p><a href='packages.php' target='_blank'>Packages (New Tab)</a></p>";
echo "<p><a href='products.php' target='_blank'>Products (New Tab)</a></p>";
echo "<p><a href='quick_update_test.php'>Quick Update Test</a></p>";
echo "<p><a href='database_operations_test.php'>Database Operations Test</a></p>";

// Show current session info
echo "<h2>Current Session:</h2>";
echo "<p>User: " . ($_SESSION['username'] ?? 'Unknown') . " (ID: " . ($_SESSION['user_id'] ?? 'Unknown') . ")</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>CSRF Token: " . substr($_SESSION['csrf_token'] ?? 'None', 0, 20) . "...</p>";
?>