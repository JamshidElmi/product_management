<?php
session_start();

echo "<h2>Message Cleanup & Debug</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>Current Session Messages:</h3>";
echo "<p>Success: " . (isset($_SESSION['success']) ? $_SESSION['success'] : 'None') . "</p>";
echo "<p>Error: " . (isset($_SESSION['error']) ? $_SESSION['error'] : 'None') . "</p>";

// Clear all messages
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
    echo "<p style='color: green;'>✓ Cleared success message</p>";
}

if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
    echo "<p style='color: green;'>✓ Cleared error message</p>";
}

echo "<h3>Actions:</h3>";
echo "<p><a href='packages.php'>Go to Packages</a></p>";
echo "<p><a href='products.php'>Go to Products</a></p>";
echo "<p><a href='javascript:location.reload();'>Refresh this page</a></p>";

echo "<h3>Session Debug:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>