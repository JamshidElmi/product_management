<?php
// Simple log viewer for debugging
$log_file = '/home/u5thlbnw7t4i/logs/yfsuite_lubricityinnovations_com.php.error.log';

echo "<h2>Error Log Viewer</h2>";
echo "<p>Log file: $log_file</p>";

if (file_exists($log_file)) {
    echo "<p>File exists. Size: " . filesize($log_file) . " bytes</p>";
    
    // Get last 50 lines
    $lines = file($log_file);
    if ($lines) {
        $total_lines = count($lines);
        echo "<p>Total lines: $total_lines</p>";
        
        // Show last 50 lines
        $start = max(0, $total_lines - 50);
        echo "<h3>Last 50 lines:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 500px;'>";
        for ($i = $start; $i < $total_lines; $i++) {
            echo htmlspecialchars($lines[$i]);
        }
        echo "</pre>";
        
        // Show only debug lines
        echo "<h3>Debug Messages Only:</h3>";
        echo "<pre style='background: #e6f3ff; padding: 10px; overflow: auto; max-height: 500px;'>";
        foreach ($lines as $line) {
            if (strpos($line, 'DEBUG') !== false) {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>Could not read log file</p>";
    }
} else {
    echo "<p style='color: red;'>Log file does not exist</p>";
}

// Also check if logs directory exists in current folder
$local_log = __DIR__ . '/logs/php-error.log';
echo "<h3>Local Log Check</h3>";
echo "<p>Local log path: $local_log</p>";
if (file_exists($local_log)) {
    $local_lines = file($local_log);
    if ($local_lines) {
        echo "<h4>Local Debug Messages:</h4>";
        echo "<pre style='background: #ffe6e6; padding: 10px; overflow: auto; max-height: 300px;'>";
        foreach ($local_lines as $line) {
            if (strpos($line, 'DEBUG') !== false) {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    }
} else {
    echo "<p>Local log file does not exist</p>";
}

// Test writing to log
error_log("[LOG VIEWER TEST] Test message from log viewer at " . date('Y-m-d H:i:s'));
echo "<p>Test message written to error log</p>";

echo "<p><a href='debug_test.php'>Back to Debug Test</a></p>";
?>