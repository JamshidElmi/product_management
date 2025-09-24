<?php
// Form Submission Override - bypasses JavaScript validation issues
require_once 'config.php';
requireLogin();

echo "<h1>🚀 Form Submission Override</h1>";
echo "<p>This bypasses any JavaScript that might be blocking your forms.</p>";

// Check if we have a success message from previous operations
if (isset($_SESSION['success'])) {
    echo "<div style='background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;'>";
    echo "✅ <strong>Previous Success:</strong> " . $_SESSION['success'];
    unset($_SESSION['success']);
    echo "</div>";
}

if (isset($_SESSION['error'])) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;'>";
    echo "❌ <strong>Previous Error:</strong> " . $_SESSION['error'];
    unset($_SESSION['error']);
    echo "</div>";
}

// Process package submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $_SESSION['error'] = "Invalid CSRF token";
    } else {
        try {
            // Simple package addition (mimicking packages.php logic)
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $discount = floatval($_POST['discount_percentage'] ?? 0);
            
            if (empty($name)) {
                throw new Exception("Package name is required");
            }
            
            // Generate item number
            $name_lower = strtolower($name);
            $name_prefix = 'PKG';
            if (strpos($name_lower, 'single') !== false) $name_prefix = 'SIN';
            elseif (strpos($name_lower, 'twin') !== false) $name_prefix = 'TWI';
            elseif (strpos($name_lower, 'combo') !== false) $name_prefix = 'COM';
            elseif (strpos($name_lower, 'flavor') !== false) $name_prefix = 'FLA';
            
            // Get next number
            $sql = "SELECT MAX(CAST(SUBSTRING(item_number, 5) AS UNSIGNED)) as max_num FROM packages WHERE item_number LIKE ?";
            $stmt = $conn->prepare($sql);
            $like_pattern = $name_prefix . '-%';
            $stmt->bind_param("s", $like_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_num = ($row['max_num'] ?? 0) + 1;
            $item_number = $name_prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            // Insert package
            $sql = "INSERT INTO packages (name, description, discount_percentage, item_number, image, created_at) VALUES (?, ?, ?, ?, 'default.jpg', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssds", $name, $description, $discount, $item_number);
            
            if ($stmt->execute()) {
                $package_id = $conn->insert_id;
                $_SESSION['success'] = "Package '$name' added successfully (ID: $package_id, Item: $item_number)";
                
                // Log admin action
                error_log("[FORM OVERRIDE] Package added successfully: $name (ID: $package_id)");
            } else {
                throw new Exception("Database error: " . $conn->error);
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log("[FORM OVERRIDE] Error adding package: " . $e->getMessage());
        }
    }
    
    // Redirect to show message
    header("Location: form_override.php");
    exit();
}

$csrf_token = generateCSRFToken();
?>

<style>
.form-group { margin: 15px 0; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-group input, .form-group textarea { 
    width: 100%; 
    max-width: 400px; 
    padding: 8px; 
    border: 1px solid #ccc; 
    border-radius: 4px; 
}
.btn { 
    background: #007cba; 
    color: white; 
    padding: 10px 20px; 
    border: none; 
    border-radius: 4px; 
    cursor: pointer; 
}
.btn:hover { background: #005a87; }
</style>

<h2>📦 Add Package (No JavaScript)</h2>
<p>This form bypasses any JavaScript validation and submits directly to the server.</p>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="add_package" value="1">
    
    <div class="form-group">
        <label for="name">Package Name *</label>
        <input type="text" name="name" id="name" required placeholder="e.g., Single Month Package">
    </div>
    
    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="3" placeholder="Package description..."></textarea>
    </div>
    
    <div class="form-group">
        <label for="discount_percentage">Discount Percentage *</label>
        <input type="number" name="discount_percentage" id="discount_percentage" step="0.01" min="0" max="100" required placeholder="e.g., 15.00">
    </div>
    
    <button type="submit" class="btn">Add Package</button>
</form>

<h2>🔍 Diagnosis Results</h2>
<div style="background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0;">
    <p><strong>Based on Form Detective results:</strong></p>
    <ul>
        <li>✅ Server-side processing works perfectly</li>
        <li>✅ CSRF tokens work properly</li>
        <li>✅ Database operations successful</li>
        <li>❌ <strong>Original forms not reaching server</strong></li>
    </ul>
    
    <p><strong>This means:</strong></p>
    <ul>
        <li>JavaScript validation is preventing form submission</li>
        <li>Browser validation blocking required fields</li>
        <li>Modal forms not submitting properly</li>
        <li>File upload validation failing</li>
    </ul>
</div>

<h2>📋 Next Steps</h2>
<ol>
    <li><strong>Test this form above</strong> - it should work perfectly</li>
    <li><strong>Check browser console</strong> when using original forms (F12 → Console)</li>
    <li><strong>Try original forms with all required fields filled</strong></li>
    <li><strong>Look for JavaScript errors</strong> in the console</li>
</ol>

<h2>🔧 Temporary Workaround</h2>
<p>If the form above works, you can use this page to add packages until we fix the JavaScript issue in the main forms.</p>

<h2>Navigation</h2>
<p><a href="packages.php">Back to Packages</a> | <a href="form_detective.php">Form Detective</a> | <a href="index.php">Dashboard</a></p>

<script>
// Log any JavaScript errors
console.log('Form Override page loaded successfully');

window.onerror = function(msg, url, line, col, error) {
    console.error('JavaScript Error detected:', msg);
    alert('JavaScript Error: ' + msg);
    return false;
};
</script>