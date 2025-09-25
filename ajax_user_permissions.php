<?php
require_once 'config.php';

// Require super admin access
requireRole('super_admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'get_permissions') {
        $user_id = (int)$_POST['user_id'];
        $username = $_POST['username'];
        
        // Get current user permissions
        $current_permissions = [];
        $perm_query = $conn->prepare("SELECT module, can_view, can_create, can_edit, can_delete FROM user_permissions WHERE user_id = ?");
        $perm_query->bind_param('i', $user_id);
        $perm_query->execute();
        $perm_result = $perm_query->get_result();
        
        while ($perm = $perm_result->fetch_assoc()) {
            $current_permissions[$perm['module']] = $perm;
        }
        $perm_query->close();
        
        // Get all system modules
        $modules_query = $conn->query("SELECT * FROM system_modules WHERE is_active = 1 ORDER BY sort_order");
        $system_modules = [];
        while ($module = $modules_query->fetch_assoc()) {
            $system_modules[] = $module;
        }
        
        ?>
        <style>
        /* Toggle Switch Styles */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background-color: #3b82f6;
        }

        .toggle-switch input:checked + .toggle-slider:before {
            -webkit-transform: translateX(20px);
            -ms-transform: translateX(20px);
            transform: translateX(20px);
        }

        /* Dark mode styles */
        .dark .toggle-slider {
            background-color: #4a5568;
        }

        .dark .toggle-switch input:checked + .toggle-slider {
            background-color: #3b82f6;
        }
        </style>
        
        <form method="post" action="users.php">
            <input type="hidden" name="action" value="update_permissions">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div class="mb-4">
                <h4 class="text-md font-medium text-gray-900 dark:text-white">Permissions for: <?php echo htmlspecialchars($username); ?></h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">Toggle the permissions this user should have for each module.</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Module</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">View</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Create</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Edit</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Delete</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">All</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($system_modules as $module): 
                            $current = $current_permissions[$module['module_key']] ?? null;
                            $module_key = $module['module_key'];
                        ?>
                        <tr>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($module['module_name']); ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[<?php echo $module_key; ?>][view]" value="1" 
                                           class="permission-checkbox view-checkbox" data-module="<?php echo $module_key; ?>"
                                           <?php echo ($current && $current['can_view']) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[<?php echo $module_key; ?>][create]" value="1" 
                                           class="permission-checkbox create-checkbox" data-module="<?php echo $module_key; ?>"
                                           <?php echo ($current && $current['can_create']) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[<?php echo $module_key; ?>][edit]" value="1" 
                                           class="permission-checkbox edit-checkbox" data-module="<?php echo $module_key; ?>"
                                           <?php echo ($current && $current['can_edit']) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[<?php echo $module_key; ?>][delete]" value="1" 
                                           class="permission-checkbox delete-checkbox" data-module="<?php echo $module_key; ?>"
                                           <?php echo ($current && $current['can_delete']) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <label class="toggle-switch">
                                    <input type="checkbox" class="all-checkbox" data-module="<?php echo $module_key; ?>"
                                           onchange="toggleAll('<?php echo $module_key; ?>', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closePermissionsModal()" 
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                    Save Permissions
                </button>
            </div>
        </form>
        
        <script>
        // Toggle all permissions for a module
        function toggleAll(module, checked) {
            const checkboxes = document.querySelectorAll(`input.permission-checkbox[data-module="${module}"]`);
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });
        }
        
        // Update "All" checkbox based on individual permissions
        function updateAllCheckbox(module) {
            const checkboxes = document.querySelectorAll(`input.permission-checkbox[data-module="${module}"]`);
            const allCheckbox = document.querySelector(`input.all-checkbox[data-module="${module}"]`);
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const someChecked = Array.from(checkboxes).some(cb => cb.checked);
            
            allCheckbox.checked = allChecked;
            allCheckbox.indeterminate = someChecked && !allChecked;
        }
        
        // Add event listeners to update "All" checkbox when individual permissions change
        document.querySelectorAll('input.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateAllCheckbox(this.dataset.module);
            });
            
            // Initialize the "All" checkbox state
            updateAllCheckbox(checkbox.dataset.module);
        });
        </script>
        <?php
    }
}
?>