<?php
// Form Submission Detective - captures and logs all form submissions
require_once 'config.php';
requireLogin();

// If this is a POST request, log everything and show what happened
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_entry = "[FORM DETECTIVE " . date('Y-m-d H:i:s') . "] ";
    $log_entry .= "POST to " . $_SERVER['REQUEST_URI'] . "\n";
    $log_entry .= "POST Data: " . print_r($_POST, true) . "\n";
    $log_entry .= "FILES Data: " . print_r($_FILES, true) . "\n";
    $log_entry .= "Session: " . print_r($_SESSION, true) . "\n";
    
    // Log to file
    file_put_contents(__DIR__ . '/logs/form_detective.log', $log_entry, FILE_APPEND | LOCK_EX);
}

echo "<h1>🕵️ Form Submission Detective</h1>";
echo "<p>This tool helps us understand what's happening when you submit forms.</p>";

// Show recent form submissions
$detective_log = __DIR__ . '/logs/form_detective.log';
if (file_exists($detective_log)) {
    echo "<h2>Recent Form Submissions:</h2>";
    $logs = file($detective_log);
    $recent = array_slice($logs, -50);
    echo "<div style='background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 11px; max-height: 400px; overflow-y: scroll; border: 1px solid #dee2e6;'>";
    foreach ($recent as $log) {
        echo htmlspecialchars($log) . "<br>";
    }
    echo "</div>";
} else {
    echo "<p>No form submissions detected yet.</p>";
}

echo "<h2>🧪 Let's Test Real Form Behavior</h2>";
echo "<p><strong>Step 1:</strong> Try these actions and watch for logs above:</p>";
echo "<ul>";
echo "<li><a href='packages.php' target='_blank'>Add a new package (opens in new tab)</a></li>";
echo "<li><a href='packages.php' target='_blank'>Edit an existing package (opens in new tab)</a></li>";
echo "<li><a href='products.php' target='_blank'>Add a new product (opens in new tab)</a></li>";
echo "</ul>";

echo "<p><strong>Step 2:</strong> <a href='?refresh=1'>Refresh this page</a> to see if any form data was captured</p>";

echo "<h2>💡 Debugging Questions</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 4px;'>";
echo "<p><strong>When you submit a form:</strong></p>";
echo "<ol>";
echo "<li><strong>Do you see any JavaScript errors in browser console?</strong> (Press F12 → Console tab)</li>";
echo "<li><strong>What happens after clicking submit?</strong>";
echo "<ul>";
echo "<li>Page refreshes immediately?</li>";
echo "<li>Form stays on same page with no changes?</li>";
echo "<li>Redirects to another page?</li>";
echo "<li>Shows loading spinner then nothing?</li>";
echo "</ul></li>";
echo "<li><strong>Are there required fields that might be empty?</strong></li>";
echo "<li><strong>Do you see any red validation messages?</strong></li>";
echo "</ol>";
echo "</div>";

// JavaScript form interceptor
echo "<h2>🚀 JavaScript Form Interceptor</h2>";
echo "<p>This code will catch any JavaScript that might be preventing form submission:</p>";

echo "<script>";
echo "// Intercept all form submissions";
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "    const forms = document.querySelectorAll('form');";
echo "    forms.forEach(form => {";
echo "        form.addEventListener('submit', function(e) {";
echo "            console.log('Form submission intercepted:', this);";
echo "            console.log('Form action:', this.action);";
echo "            console.log('Form method:', this.method);";
echo "            console.log('Form data:', new FormData(this));";
echo "            // Don't prevent submission, just log it";
echo "        });";
echo "    });";
echo "});";

echo "// Log any JavaScript errors";
echo "window.onerror = function(msg, url, line, col, error) {";
echo "    console.error('JavaScript Error:', msg, 'at', url, 'line', line);";
echo "    alert('JavaScript Error: ' + msg);";
echo "    return false;";
echo "};";
echo "</script>";

// Manual form test
echo "<h2>📝 Manual Form Test</h2>";
echo "<p>Let's test with a simple form that mimics your package form:</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_test'])) {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; color: #155724; margin: 10px 0;'>";
    echo "<h3>✅ Manual Form Submission Successful!</h3>";
    echo "<p><strong>Received Data:</strong></p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "</div>";
}

$csrf_token = generateCSRFToken();
echo "<form method='POST' enctype='multipart/form-data' style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin: 10px 0;'>";
echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
echo "<input type='hidden' name='manual_test' value='1'>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Package Name:</label><br>";
echo "<input type='text' name='name' required style='width: 300px; padding: 5px;' value='Test Package " . date('H:i') . "'>";
echo "</div>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Description:</label><br>";
echo "<textarea name='description' required style='width: 300px; height: 60px; padding: 5px;'>Test description</textarea>";
echo "</div>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Price:</label><br>";
echo "<input type='number' name='price' step='0.01' required style='padding: 5px;' value='99.99'>";
echo "</div>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Image (optional):</label><br>";
echo "<input type='file' name='image' accept='image/*' style='padding: 5px;'>";
echo "</div>";

echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Test Submit</button>";
echo "</form>";

echo "<p><a href='?clear_log=1' style='color: #dc3545;'>Clear Detective Log</a></p>";

if (isset($_GET['clear_log'])) {
    @unlink($detective_log);
    echo "<p style='color: green;'>Detective log cleared.</p>";
}

echo "<h2>🔄 Auto-Refresh</h2>";
echo "<p><em>This page will auto-refresh every 10 seconds to show new form submissions...</em></p>";
echo "<meta http-equiv='refresh' content='10'>";
?>