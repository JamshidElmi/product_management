<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $discount_percentage = $_POST['discount_percentage'];

    $sql = "INSERT INTO packages (name, description, discount_percentage) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssd", $name, $description, $discount_percentage);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Package added successfully.";
        $package_id = $conn->insert_id;

        // Add package items
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $sql = "INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['products'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    $stmt->bind_param("iii", $package_id, $product_id, $quantity);
                    $stmt->execute();
                }
            }
        }

        // Add subscription prices if provided
        if (isset($_POST['subscription_prices']) && is_array($_POST['subscription_prices'])) {
            $sql = "INSERT INTO package_subscription_prices (package_id, subscription_type_id, discount_percentage) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['subscription_prices'] as $subscription_type_id => $discount_percentage) {
                $stmt->bind_param("iid", $package_id, $subscription_type_id, $discount_percentage);
                $stmt->execute();
            }
        }

    } else {
        $_SESSION['error'] = "Error adding package.";
    }

    header("Location: packages.php");
    exit();
}
?>
