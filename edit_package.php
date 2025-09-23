<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $discount_percentage = $_POST['discount_percentage'];

    $sql = "UPDATE packages SET name = ?, description = ?, discount_percentage = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdi", $name, $description, $discount_percentage, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Package updated successfully.";

        // Update package items
        $sql = "DELETE FROM package_items WHERE package_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $sql = "INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['products'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    $stmt->bind_param("iii", $id, $product_id, $quantity);
                    $stmt->execute();
                }
            }
        }

        // Update subscription prices
        $sql = "DELETE FROM package_subscription_prices WHERE package_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if (isset($_POST['subscription_prices']) && is_array($_POST['subscription_prices'])) {
            $sql = "INSERT INTO package_subscription_prices (package_id, subscription_type_id, discount_percentage) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            foreach ($_POST['subscription_prices'] as $subscription_type_id => $discount_percentage) {
                $stmt->bind_param("iid", $id, $subscription_type_id, $discount_percentage);
                $stmt->execute();
            }
        }

    } else {
        $_SESSION['error'] = "Error updating package.";
    }

    header("Location: packages.php");
    exit();
}
?>
