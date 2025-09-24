<?php
require_once 'config.php';

// Require login
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Check if user has permission to view orders
    if (!hasRole('admin') && !hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit();
    }
    
    // Ensure status column exists
    $checkStatusColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
    if ($checkStatusColumn->num_rows === 0) {
        // Add status column if it doesn't exist
        $conn->query("ALTER TABLE orders ADD COLUMN status ENUM('pending', 'processing', 'completed', 'canceled') DEFAULT 'pending'");
    }
    
    // Get counts for pending and processing orders
    $query = "SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_count,
                COUNT(CASE WHEN status IN ('pending', 'processing') THEN 1 END) as total_active_count
              FROM orders";
    
    $result = $conn->query($query);
    
    if ($result) {
        $counts = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'pending' => (int)$counts['pending_count'],
            'processing' => (int)$counts['processing_count'],
            'total_active' => (int)$counts['total_active_count']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch order counts']);
    }
    
} catch (Exception $e) {
    error_log("Order status count error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching order counts']);
}
?>