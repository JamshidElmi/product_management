<?php
require_once 'config.php';
requireLogin();

echo "<h2>Package Debug Info</h2>";

// Check packages query
$packages = $conn->query("
    SELECT p.*, GROUP_CONCAT(CONCAT(pi.quantity, 'x ', pr.name, ' (', pr.flavor, ' - ', pr.size, ')') SEPARATOR ', ') as items
    FROM packages p
    LEFT JOIN package_items pi ON p.id = pi.package_id
    LEFT JOIN products pr ON pi.product_id = pr.id
    GROUP BY p.id, p.b2b
    ORDER BY p.name
");

echo "<p>Packages query returned " . $packages->num_rows . " rows</p>";

if ($packages->num_rows > 0) {
    echo "<h3>Packages found:</h3>";
    while ($package = $packages->fetch_assoc()) {
        echo "<p>ID: " . $package['id'] . " - Name: " . $package['name'] . " - B2B: " . $package['b2b'] . "</p>";
    }
} else {
    echo "<p>No packages found!</p>";
}

// Check if there are any packages at all
$simple_count = $conn->query("SELECT COUNT(*) as count FROM packages");
$count_result = $simple_count->fetch_assoc();
echo "<p>Total packages in database: " . $count_result['count'] . "</p>";

// Check recent packages
$recent = $conn->query("SELECT * FROM packages ORDER BY id DESC LIMIT 5");
echo "<h3>Recent packages (last 5):</h3>";
while ($pkg = $recent->fetch_assoc()) {
    echo "<p>ID: " . $pkg['id'] . " - Name: " . $pkg['name'] . " - B2B: " . $pkg['b2b'] . "</p>";
}

// Check session messages
echo "<h3>Session Messages:</h3>";
echo "<p>Success: " . (isset($_SESSION['success']) ? $_SESSION['success'] : 'None') . "</p>";
echo "<p>Error: " . (isset($_SESSION['error']) ? $_SESSION['error'] : 'None') . "</p>";
?>