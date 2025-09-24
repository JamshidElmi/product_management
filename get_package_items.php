<?php
require_once 'config.php';

// Prevent AJAX response caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

if (isset($_GET['package_id'])) {
    $package_id = intval($_GET['package_id']);
    
    $sql = "SELECT product_id, quantity FROM package_items WHERE package_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = array();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($items);
} else {
    echo "Invalid package ID.";
}
?>