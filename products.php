<?php
require_once 'config.php';
requireLogin();

// Prevent browser caching to ensure fresh data display
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side validation
    $errors = [];
    
    // Validate required fields based on action
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        if (empty(trim($_POST['name'] ?? ''))) {
            $errors[] = 'Product name is required';
        }
        if (empty(trim($_POST['size'] ?? ''))) {
            $errors[] = 'Size is required';
        }
        if (!isset($_POST['msrp_price']) || !is_numeric($_POST['msrp_price']) || $_POST['msrp_price'] <= 0) {
            $errors[] = 'Valid MSRP price is required';
        }
        if (!isset($_POST['web_price']) || !is_numeric($_POST['web_price']) || $_POST['web_price'] <= 0) {
            $errors[] = 'Valid web price is required';
        }
    }
    
    // If validation fails, set error and redirect
    if (!empty($errors)) {
        $_SESSION['error'] = 'Validation failed: ' . implode(', ', $errors);
        header("Location: products.php");
        exit();
    }

    switch ($_POST['action']) {
        case 'add':
            // Generate item number based on product name
            $name_prefix = '';
            $name_lower = strtolower($_POST['name']);
            if (strpos($name_lower, 'lubricity') !== false) {
                $name_prefix = 'LUB';
            } elseif (strpos($name_lower, 'metaoil') !== false) {
                $name_prefix = 'MET';
            } else {
                $name_prefix = 'PRD';
            }
            
            // Get next number for this prefix
            $sql = "SELECT MAX(CAST(SUBSTRING(item_number, 5) AS UNSIGNED)) as max_num FROM products WHERE item_number LIKE ?";
            $stmt = $conn->prepare($sql);
            $like_pattern = $name_prefix . '-%';
            $stmt->bind_param("s", $like_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_num = ($row['max_num'] ?? 0) + 1;
            $item_number = $name_prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            $sql = "INSERT INTO products (item_number, name, flavor, size, msrp_price, web_price) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssdd", $item_number, $_POST['name'], $_POST['flavor'], $_POST['size'], $_POST['msrp_price'], $_POST['web_price']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product added successfully.";
                    // Log admin action
                    logAdminAction('product_create', "Created product: {$_POST['name']} (Item: $item_number)");
            } else {
                $_SESSION['error'] = "Error adding product.";
            }
            break;

        case 'edit':
            $sql = "UPDATE products SET name = ?, flavor = ?, size = ?, msrp_price = ?, web_price = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssddi", $_POST['name'], $_POST['flavor'], $_POST['size'], $_POST['msrp_price'], $_POST['web_price'], $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product updated successfully.";
                    // Log admin action
                    logAdminAction('product_update', "Updated product: {$_POST['name']} (ID: {$_POST['id']})");
            } else {
                $_SESSION['error'] = "Error updating product.";
            }
            break;

        case 'delete':
            $sql = "DELETE FROM products WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Product deleted successfully.";
                    // Log admin action
                    logAdminAction('product_delete', "Deleted product (ID: {$_POST['id']})");
            } else {
                $_SESSION['error'] = "Error deleting product.";
            }
            break;
    }
    header("Location: products.php");
    exit();
}

// Get all products
$products = $conn->query("SELECT * FROM products ORDER BY name, size");

// Get all subscription types
$subscription_types = $conn->query("SELECT * FROM subscription_types ORDER BY days");

ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Products</h1>
    <button type="button" onclick="document.getElementById('addProductModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add New Product
    </button>
</div>

<!-- Products Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item Number</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Flavor</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MSRP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Web Price</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php while ($product = $products->fetch_assoc()): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['item_number']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['name']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['flavor']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['size']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">$<?php echo number_format($product['msrp_price'], 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">$<?php echo number_format($product['web_price'], 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="text-primary hover:text-primary/80 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="text-red-600 hover:text-red-800">
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

<!-- Add Product Modal -->
<div id="addProductModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Add New Product</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                 
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="flavor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flavor</label>
                    <input type="text" name="flavor" id="flavor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Size</label>
                    <input type="text" name="size" id="size" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="msrp_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">MSRP Price</label>
                    <input type="number" step="0.01" name="msrp_price" id="msrp_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="web_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Web Price</label>
                    <input type="number" step="0.01" name="web_price" id="web_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addProductModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Add Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Edit Product</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" id="edit_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_flavor" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flavor</label>
                    <input type="text" name="flavor" id="edit_flavor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Size</label>
                    <input type="text" name="size" id="edit_size" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_msrp_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">MSRP Price</label>
                    <input type="number" step="0.01" name="msrp_price" id="edit_msrp_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div>
                    <label for="edit_web_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Web Price</label>
                    <input type="number" step="0.01" name="web_price" id="edit_web_price" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('editProductModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Update Product
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
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Delete Product</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Are you sure you want to delete this product?</p>
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
function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_flavor').value = product.flavor;
    document.getElementById('edit_size').value = product.size;
    document.getElementById('edit_msrp_price').value = product.msrp_price;
    document.getElementById('edit_web_price').value = product.web_price;
    
    // Show the modal
    document.getElementById('editProductModal').classList.remove('hidden');
}

function deleteProduct(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}

// Enhanced form validation and submission handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Products page: Setting up form validation');
    
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
                
                // Validate product name
                const name = this.querySelector('input[name="name"]')?.value?.trim();
                if (!name) {
                    errors.push('Product name is required');
                }
                
                // Validate size
                const size = this.querySelector('input[name="size"]')?.value?.trim();
                if (!size) {
                    errors.push('Size is required');
                }
                
                // Validate MSRP price
                const msrp = this.querySelector('input[name="msrp_price"]')?.value;
                if (!msrp || isNaN(parseFloat(msrp)) || parseFloat(msrp) <= 0) {
                    errors.push('Valid MSRP price is required');
                }
                
                // Validate web price
                const webPrice = this.querySelector('input[name="web_price"]')?.value;
                if (!webPrice || isNaN(parseFloat(webPrice)) || parseFloat(webPrice) <= 0) {
                    errors.push('Valid web price is required');
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