<?php
require_once 'config.php';

// Require super admin access for user management
requireRole('super_admin');

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $role = $_POST['role'];
                
                // Validate inputs
                if (empty($username) || empty($email) || empty($password)) {
                    $_SESSION['error'] = 'All fields are required.';
                } elseif (strlen($password) < 6) {
                    $_SESSION['error'] = 'Password must be at least 6 characters long.';
                } else {
                    $user_id = createUser($username, $email, $password, $role);
                    if ($user_id) {
                        $_SESSION['success'] = 'User created successfully.';
                        logAdminAction('create_user', "Created user: $username (ID: $user_id)", $user_id);
                    } else {
                        $_SESSION['error'] = 'Failed to create user. Username or email may already exist.';
                    }
                }
                header('Location: users.php');
                exit;
                
            case 'update_user_status':
                $user_id = (int)$_POST['user_id'];
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND id != ?");
                $current_user = getCurrentUser();
                $stmt->bind_param('sii', $status, $user_id, $current_user['id']); // Can't change own status
                
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $_SESSION['success'] = 'User status updated successfully.';
                    logAdminAction('update_user_status', "Changed user ID $user_id status to $status", $user_id);
                } else {
                    $_SESSION['error'] = 'Failed to update user status.';
                }
                $stmt->close();
                header('Location: users.php');
                exit;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                $current_user = getCurrentUser();
                
                if ($user_id == $current_user['id']) {
                    $_SESSION['error'] = 'You cannot delete your own account.';
                } else {
                    $conn->begin_transaction();
                    try {
                        // Delete user permissions first
                        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Delete user
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $_SESSION['success'] = 'User deleted successfully.';
                        logAdminAction('delete_user', "Deleted user ID: $user_id", $user_id);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = 'Failed to delete user.';
                    }
                }
                header('Location: users.php');
                exit;
                
            case 'update_permissions':
                $user_id = (int)$_POST['user_id'];
                $modules = $_POST['modules'] ?? [];
                
                // Clear existing permissions
                $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Insert new permissions
                $success_count = 0;
                foreach ($modules as $module => $permissions) {
                    $result = setUserPermissions($user_id, $module, $permissions);
                    if ($result) $success_count++;
                }
                
                if ($success_count > 0) {
                    $_SESSION['success'] = 'User permissions updated successfully.';
                    logAdminAction('update_user_permissions', "Updated permissions for user ID: $user_id", $user_id);
                } else {
                    $_SESSION['error'] = 'Failed to update permissions.';
                }
                header('Location: users.php');
                exit;
        }
    }
}

// Fetch all users
$users_query = "SELECT u.*, creator.username as created_by_username 
                FROM users u 
                LEFT JOIN users creator ON u.created_by = creator.id 
                ORDER BY u.created_at DESC";
$users_result = $conn->query($users_query);

// Fetch system modules
$modules_query = "SELECT * FROM system_modules WHERE is_active = 1 ORDER BY sort_order";
$modules_result = $conn->query($modules_query);
$system_modules = [];
while ($module = $modules_result->fetch_assoc()) {
    $system_modules[] = $module;
}

ob_start();
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">User Management</h1>
        <button onclick="openCreateUserModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <ion-icon name="person-add-outline" class="w-4 h-4 mr-2 align-middle" aria-hidden="true"></ion-icon>
            Add User
        </button>
    </div>

    <!-- Users List -->
    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">All Users</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Manage system users and their permissions.</p>
        </div>
        
        <div class="border-t border-gray-200 dark:border-gray-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Login</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php 
                                    echo $user['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 
                                        ($user['role'] === 'admin' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                         'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="update_user_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" 
                                            class="text-xs px-2 py-1 rounded border-0 focus:ring-2 focus:ring-blue-500 <?php
                                        echo $user['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                            ($user['status'] === 'inactive' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                             'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'); 
                                    ?>" <?php echo getCurrentUser()['id'] == $user['id'] ? 'disabled' : ''; ?>>
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo $user['created_by_username'] ?? 'System'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <button onclick="openPermissionsModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')" 
                                        class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
                                    <ion-icon name="shield-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Permissions
                                </button>
                                
                                <button onclick="openChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')" 
                                        class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    <ion-icon name="key-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Password
                                </button>
                                
                                <?php if (getCurrentUser()['id'] != $user['id']): ?>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')" 
                                        class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors">
                                    <ion-icon name="trash-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Create New User</h3>
            <form method="post">
                <input type="hidden" name="action" value="create_user">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text" name="username" required 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
                        <select name="role" required 
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeCreateUserModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-4/5 max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Manage User Permissions</h3>
            <div id="permissionsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Password</h3>
            <div id="changePasswordAlert" class="hidden mb-4"></div>
            
            <form id="changePasswordForm">
                <input type="hidden" id="changePasswordUserId" name="user_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                        <input type="text" id="changePasswordUsername" readonly 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-100 dark:bg-gray-600 dark:text-white">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                        <input type="password" id="newPassword" name="new_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Enter new password">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Confirm new password">
                    </div>

                    <div id="currentPasswordSection">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                        <input type="password" id="currentPassword" name="current_password" 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                               placeholder="Enter your current password">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Required when changing your own password</p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeChangePasswordModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Create User Modal Functions
function openCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
}

// Delete User Function
function deleteUser(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Permissions Modal Functions
function openPermissionsModal(userId, username) {
    document.getElementById('permissionsModal').classList.remove('hidden');
    loadUserPermissions(userId, username);
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').classList.add('hidden');
}

function loadUserPermissions(userId, username) {
    fetch('ajax_user_permissions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_permissions&user_id=${userId}&username=${encodeURIComponent(username)}`
    })
    .then(response => response.text())
    .then(html => {
        document.getElementById('permissionsContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading permissions:', error);
        document.getElementById('permissionsContent').innerHTML = '<p class="text-red-500">Error loading permissions.</p>';
    });
}

// Change Password Modal Functions
function openChangePasswordModal(userId, username) {
    document.getElementById('changePasswordModal').classList.remove('hidden');
    document.getElementById('changePasswordUserId').value = userId;
    document.getElementById('changePasswordUsername').value = username;
    
    // Get current user ID to determine if current password is required
    const currentUserId = <?php echo getCurrentUser()['id']; ?>;
    const currentPasswordSection = document.getElementById('currentPasswordSection');
    const currentPasswordInput = document.getElementById('currentPassword');
    
    if (userId == currentUserId) {
        // User is changing their own password - require current password
        currentPasswordSection.style.display = 'block';
        currentPasswordInput.required = true;
    } else {
        // Admin is changing another user's password - don't require current password
        currentPasswordSection.style.display = 'none';
        currentPasswordInput.required = false;
        currentPasswordInput.value = '';
    }
    
    // Clear form
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    document.getElementById('changePasswordAlert').classList.add('hidden');
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('hidden');
}

// Handle change password form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');
            
            // Validate passwords match
            if (newPassword !== confirmPassword) {
                showChangePasswordAlert('Passwords do not match', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Changing...';
            submitBtn.disabled = true;
            
            fetch('ajax_change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showChangePasswordAlert('Password changed successfully!', 'success');
                    setTimeout(() => {
                        closeChangePasswordModal();
                    }, 1500);
                } else {
                    showChangePasswordAlert(data.message || 'Error changing password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showChangePasswordAlert('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

function showChangePasswordAlert(message, type) {
    const alert = document.getElementById('changePasswordAlert');
    alert.className = `mb-4 p-3 rounded ${type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'}`;
    alert.textContent = message;
    alert.classList.remove('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const createModal = document.getElementById('createUserModal');
    const permModal = document.getElementById('permissionsModal');
    const changePasswordModal = document.getElementById('changePasswordModal');
    
    if (event.target === createModal) {
        closeCreateUserModal();
    }
    if (event.target === permModal) {
        closePermissionsModal();
    }
    if (event.target === changePasswordModal) {
        closeChangePasswordModal();
    }
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>