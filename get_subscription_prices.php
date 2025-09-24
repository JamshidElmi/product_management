<?php
require_once 'config.php';

// Prevent AJAX response caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

if (isset($_GET['package_id'])) {
    $package_id = intval($_GET['package_id']);

    $sql = "SELECT subscription_type_id, discount_percentage FROM package_subscription_prices WHERE package_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $prices = array();
    while ($row = $result->fetch_assoc()) {
        $prices[] = array(
            'subscription_type_id' => $row['subscription_type_id'],
            'discount_percentage' => $row['discount_percentage']
        );
    }

    header('Content-Type: application/json');
    echo json_encode($prices);
} else {
    echo "Invalid package ID.";
}
?>
