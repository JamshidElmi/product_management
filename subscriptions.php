<?php
require_once 'config.php';
requireLogin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side validation
    $errors = [];
    
    // Validate required fields based on action
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        if (empty(trim($_POST['name'] ?? ''))) {
            $errors[] = 'Subscription name is required';
        }
        if (!isset($_POST['days']) || !is_numeric($_POST['days']) || $_POST['days'] <= 0) {
            $errors[] = 'Valid number of days is required';
        }
        if (!isset($_POST['discount_percentage']) || !is_numeric($_POST['discount_percentage']) || $_POST['discount_percentage'] < 0 || $_POST['discount_percentage'] > 100) {
            $errors[] = 'Valid discount percentage (0-100) is required';
        }
    }
    
    // If validation fails, set error and redirect
    if (!empty($errors)) {
        $_SESSION['error'] = 'Validation failed: ' . implode(', ', $errors);
        header("Location: subscriptions.php");
        exit();
    }

    switch ($_POST['action']) {
        case 'add':
            $sql = "INSERT INTO subscription_types (name, days, discount_percentage) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sid", $_POST['name'], $_POST['days'], $_POST['discount_percentage']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Subscription type added successfully.";
                    // Log admin action
                    logAdminAction('subscription_create', "Created subscription: {$_POST['name']} ({$_POST['days']} days)");
            } else {
                $_SESSION['error'] = "Error adding subscription type.";
            }
            break;
 
        case 'edit':
            $sql = "UPDATE subscription_types SET name = ?, days = ?, discount_percentage = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sidi", $_POST['name'], $_POST['days'], $_POST['discount_percentage'], $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Subscription type updated successfully.";
                    // Log admin action
                    logAdminAction('subscription_update', "Updated subscription: {$_POST['name']} (ID: {$_POST['id']})");
            } else {
                $_SESSION['error'] = "Error updating subscription type.";
            }
            break;

        case 'delete':
            $sql = "DELETE FROM subscription_types WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Subscription type deleted successfully.";
                    // Log admin action
                    logAdminAction('subscription_delete', "Deleted subscription (ID: {$_POST['id']})");
            } else {
                $_SESSION['error'] = "Error deleting subscription type.";
            }
            break;
    }
    header("Location: subscriptions.php");
    exit();
}

// Get all subscription types
$subscriptions = $conn->query("SELECT * FROM subscription_types ORDER BY days");

ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Subscription Types</h1>
    <button type="button" onclick="document.getElementById('addSubscriptionModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add New Subscription
    </button>
</div>

<!-- Subscriptions Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Days</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Discount</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php while ($subscription = $subscriptions->fetch_assoc()): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($subscription['name']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo $subscription['days']; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo number_format($subscription['discount_percentage'], 1); ?>%</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        <button onclick="editSubscription(<?php echo htmlspecialchars(json_encode($subscription)); ?>)" class="text-primary hover:text-primary/80 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="deleteSubscription(<?php echo $subscription['id']; ?>)" class="text-red-600 hover:text-red-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Subscription Modal -->
<div id="addSubscriptionModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Add New Subscription</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Days</label>
                    <input type="number" name="days" id="days" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="discount_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Percentage</label>
                    <input type="number" step="0.01" name="discount_percentage" id="discount_percentage" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addSubscriptionModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Add Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal -->
<div id="editSubscriptionModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Edit Subscription</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="edit_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Days</label>
                    <input type="number" name="days" id="edit_days" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_discount_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Percentage</label>
                    <input type="number" step="0.01" name="discount_percentage" id="edit_discount_percentage" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('editSubscriptionModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Update Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Delete Subscription</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Are you sure you want to delete this subscription type?</p>
            <form method="POST" class="mt-4">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSubscription(subscription) {
    document.getElementById('edit_id').value = subscription.id;
    document.getElementById('edit_name').value = subscription.name;
    document.getElementById('edit_days').value = subscription.days;
    document.getElementById('edit_discount_percentage').value = subscription.discount_percentage;
    
    // Show the modal
    document.getElementById('editSubscriptionModal').classList.remove('hidden');
}

function deleteSubscription(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}

// Enhanced form validation and submission handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Subscriptions page: Setting up form validation');
    
    // Add form validation and submission handlers
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach((form, index) => {
        console.log('Setting up form handler for form', index);
        
        form.addEventListener('submit', function(e) {
            console.log('Form submission intercepted for validation');
            
            // Skip validation for delete forms
            if (this.querySelector('input[name="action"][value="delete"]')) {
                console.log('Delete form - skipping validation');
                return true;
            }
            
            // Validate add/edit forms
            const action = this.querySelector('input[name="action"]')?.value;
            if (action === 'add' || action === 'edit') {
                console.log('Validating', action, 'form');
                
                const errors = [];
                
                // Validate subscription name
                const name = this.querySelector('input[name="name"]')?.value?.trim();
                if (!name) {
                    errors.push('Subscription name is required');
                }
                
                // Validate days
                const days = this.querySelector('input[name="days"]')?.value;
                if (!days || isNaN(parseInt(days)) || parseInt(days) <= 0) {
                    errors.push('Valid number of days is required');
                }
                
                // Validate discount percentage
                const discount = this.querySelector('input[name="discount_percentage"]')?.value;
                if (!discount && discount !== '0') {
                    errors.push('Discount percentage is required');
                } else if (isNaN(parseFloat(discount)) || parseFloat(discount) < 0 || parseFloat(discount) > 100) {
                    errors.push('Discount percentage must be between 0 and 100');
                }
                
                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n\n• ' + errors.join('\n• '));
                    console.log('Form validation failed:', errors);
                    return false;
                }
                
                console.log('Form validation passed, submitting...');
            }
            
            return true;
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>