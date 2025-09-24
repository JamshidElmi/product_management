<?php
require_once 'config.php';

// Require login and admin privileges
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $current_user = getCurrentUser();
    
    // Check if user has permission to change passwords
    if (!hasRole('admin') && !hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit();
    }
    
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    
    // Validate input
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }
    
    if (empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'New password is required']);
        exit();
    }
    
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        exit();
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    
    // Check if user exists and get their current data
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $target_user = $result->fetch_assoc();
    
    // If user is changing their own password, verify current password
    if ($user_id == $current_user['id']) {
        if (empty($current_password)) {
            echo json_encode(['success' => false, 'message' => 'Current password is required']);
            exit();
        }
        
        if (!password_verify($current_password, $target_user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
    }
    
    // Only super admins can change other super admin passwords
    if ($user_id != $current_user['id']) {
        $target_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $target_stmt->bind_param("i", $user_id);
        $target_stmt->execute();
        $target_role_result = $target_stmt->get_result();
        $target_role = $target_role_result->fetch_assoc()['role'];
        
        if ($target_role === 'super_admin' && $current_user['role'] !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admins can change super admin passwords']);
            exit();
        }
    }
    
    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the password
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        // Log the password change
        logAdminAction('password_change', "Password changed for user: {$target_user['username']} (ID: {$user_id})", $user_id);
        logSecurityEvent('password_changed', "Password changed for user ID: {$user_id}", $user_id);
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password']);
    }
    
} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while changing the password']);
}
?>