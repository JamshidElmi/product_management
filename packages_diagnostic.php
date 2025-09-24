<?php
// Simple packages diagnostic - check this on your server
require_once 'config.php';

echo "<h1>Packages Diagnostic</h1>";
echo "<p>Server: " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

// Test database connection
try {
    $test = $conn->query("SELECT 1");
    echo "<p style='color: green;'>✓ Database connection OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Count total packages
$count_result = $conn->query("SELECT COUNT(*) as count FROM packages");
$count = $count_result->fetch_assoc()['count'];
echo "<p>Total packages in database: <strong>$count</strong></p>";

// Show recent packages
echo "<h2>Recent Packages (last 10):</h2>";
$recent = $conn->query("SELECT id, name, b2b, created_at FROM packages ORDER BY id DESC LIMIT 10");
if ($recent->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>B2B</th><th>Created</th></tr>";
    while ($pkg = $recent->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $pkg['id'] . "</td>";
        echo "<td>" . htmlspecialchars($pkg['name']) . "</td>";
        echo "<td>" . $pkg['b2b'] . "</td>";
        echo "<td>" . ($pkg['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No packages found!</p>";
}

// Test the main packages query
echo "<h2>Main Packages Query Test:</h2>";
$main_query = "
    SELECT p.*, GROUP_CONCAT(CONCAT(pi.quantity, 'x ', pr.name, ' (', pr.flavor, ' - ', pr.size, ')') SEPARATOR ', ') as items
    FROM packages p
    LEFT JOIN package_items pi ON p.id = pi.package_id
    LEFT JOIN products pr ON pi.product_id = pr.id
    GROUP BY p.id, p.b2b
    ORDER BY p.name
";

$packages = $conn->query($main_query);
if ($packages) {
    echo "<p>Query executed successfully. Rows returned: <strong>" . $packages->num_rows . "</strong></p>";
    
    if ($packages->num_rows > 0) {
        echo "<h3>First 5 results:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>B2B</th><th>Items</th></tr>";
        $count = 0;
        while ($pkg = $packages->fetch_assoc() && $count < 5) {
            echo "<tr>";
            echo "<td>" . $pkg['id'] . "</td>";
            echo "<td>" . htmlspecialchars($pkg['name']) . "</td>";
            echo "<td>" . $pkg['b2b'] . "</td>";
            echo "<td>" . htmlspecialchars($pkg['items'] ?? 'No items') . "</td>";
            echo "</tr>";
            $count++;
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
}
?>