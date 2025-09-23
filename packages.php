<?php
require_once 'config.php';
requireLogin();

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();

// Function to handle image upload
function handleImageUpload($file, $current_image = null) {
    $upload_dir = 'imgs/';
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_image; // Keep current image if no new file
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code: ' . $file['error']);
    }
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed.');
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'package_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    // Delete old image if exists and not default
    if ($current_image && $current_image !== 'default.jpg' && file_exists($upload_dir . $current_image)) {
        unlink($upload_dir . $current_image);
    }
    
    return $filename;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        logSecurityEvent('csrf_token_invalid', $_SERVER['REMOTE_ADDR'], 'Invalid CSRF token in packages.php');
        $_SESSION['error'] = "Security error: Invalid token. Please try again.";
        header("Location: packages.php");
        exit();
    }
    
    switch ($_POST['action']) {
        case 'add':
            // Generate item number based on package name
            $name_prefix = '';
            $name_lower = strtolower($_POST['name']);
            if (strpos($name_lower, 'single') !== false) {
                $name_prefix = 'SIN';
            } elseif (strpos($name_lower, 'twin') !== false) {
                $name_prefix = 'TWI';
            } elseif (strpos($name_lower, 'combo') !== false) {
                $name_prefix = 'COM';
            } elseif (strpos($name_lower, 'flavor') !== false) {
                $name_prefix = 'FLA';
            } else {
                $name_prefix = 'PKG';
            }
            
            // Get next number for this prefix
            $sql = "SELECT MAX(CAST(SUBSTRING(item_number, 5) AS UNSIGNED)) as max_num FROM packages WHERE item_number LIKE ?";
            $stmt = $conn->prepare($sql);
            $like_pattern = $name_prefix . '-%';
            $stmt->bind_param("s", $like_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_num = ($row['max_num'] ?? 0) + 1;
            $item_number = $name_prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
            
            $b2b = isset($_POST['b2b']) && $_POST['b2b'] === '1' ? 1 : 0;
            
            // Handle image upload
            $image_filename = null;
            try {
                $image_filename = handleImageUpload($_FILES['image'] ?? null);
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header("Location: packages.php");
                exit();
            }
            
            $sql = "INSERT INTO packages (item_number, name, description, discount_percentage, b2b, image) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdis", $item_number, $_POST['name'], $_POST['description'], $_POST['discount_percentage'], $b2b, $image_filename);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Package added successfully.";
                $package_id = $conn->insert_id;
                
                    // Log admin action
                    logAdminAction('package_create', "Created package: {$_POST['name']} (ID: $package_id, Item: $item_number)");
                 
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
                    foreach ($_POST['subscription_prices'] as $subscription_type_id => $data) {
                        if (!empty($data['discount'])) {
                            $stmt->bind_param("iid", $package_id, $subscription_type_id, $data['discount']);
                            $stmt->execute();
                        }
                    }
                }
            } else {
                $_SESSION['error'] = "Error adding package.";
            }
            break;

        case 'edit':
            $b2b = isset($_POST['b2b']) && $_POST['b2b'] === '1' ? 1 : 0;
            
            // Get current image
            $sql = "SELECT image FROM packages WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_POST['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_package = $result->fetch_assoc();
            $current_image = $current_package['image'];
            
            // Handle image upload
            $image_filename = $current_image;
            try {
                $image_filename = handleImageUpload($_FILES['image'] ?? null, $current_image);
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header("Location: packages.php");
                exit();
            }
            
            $sql = "UPDATE packages SET name = ?, description = ?, discount_percentage = ?, b2b = ?, image = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdisi", $_POST['name'], $_POST['description'], $_POST['discount_percentage'], $b2b, $image_filename, $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Package updated successfully.";
                
                    // Log admin action
                    logAdminAction('package_update', "Updated package: {$_POST['name']} (ID: {$_POST['id']})");
                
                // Update package items
                $sql = "DELETE FROM package_items WHERE package_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $_POST['id']);
                $stmt->execute();
                
                if (isset($_POST['products']) && is_array($_POST['products'])) {
                    $sql = "INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    foreach ($_POST['products'] as $product_id => $quantity) {
                        if ($quantity > 0) {
                            $stmt->bind_param("iii", $_POST['id'], $product_id, $quantity);
                            $stmt->execute();
                        }
                    }
                }
                
                // Update subscription prices
                if (isset($_POST['subscription_prices']) && is_array($_POST['subscription_prices'])) {
                    foreach ($_POST['subscription_prices'] as $subscription_type_id => $data) {
                        if (!empty($data['discount'])) {
                            $sql = "INSERT INTO package_subscription_prices (package_id, subscription_type_id, discount_percentage) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE discount_percentage = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iidd", $_POST['id'], $subscription_type_id, $data['discount'], $data['discount']);
                            $stmt->execute();
                        }
                    }
                }
            } else {
                $_SESSION['error'] = "Error updating package.";
            }
            break;

        case 'delete':
                // Get package name before deletion for logging
                $sql = "SELECT name FROM packages WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $_POST['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $package = $result->fetch_assoc();
                $package_name = $package['name'] ?? 'Unknown';
            
            $sql = "DELETE FROM packages WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_POST['id']);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Package deleted successfully.";
                
                    // Log admin action
                    logAdminAction('package_delete', "Deleted package: $package_name (ID: {$_POST['id']})");
            } else {
                $_SESSION['error'] = "Error deleting package.";
            }
            break;
    }
    header("Location: packages.php");
    exit();
}

// Get all packages with their items
$packages = $conn->query("
    SELECT p.*, GROUP_CONCAT(CONCAT(pi.quantity, 'x ', pr.name, ' (', pr.flavor, ' - ', pr.size, ')') SEPARATOR ', ') as items
    FROM packages p
    LEFT JOIN package_items pi ON p.id = pi.package_id
    LEFT JOIN products pr ON pi.product_id = pr.id
    GROUP BY p.id, p.b2b
    ORDER BY p.name
");

// Get all products for the add/edit forms
$products = $conn->query("SELECT * FROM products ORDER BY name, size");

// Get all subscription types
$subscription_types = $conn->query("SELECT * FROM subscription_types ORDER BY days");

ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Packages</h1>
    <button type="button" onclick="document.getElementById('addPackageModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add New Package
    </button>
</div>

<!-- Packages Table -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Image&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Package Discount</th>
                    <?php
                    $subscription_types->data_seek(0);
                    while ($sub_type = $subscription_types->fetch_assoc()): ?>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo htmlspecialchars($sub_type['name']); ?> Discount</th>
                    <?php endwhile; ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php 
                $packages->data_seek(0);
                while ($package = $packages->fetch_assoc()): ?>
                <tr>
                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        <?php 
                        $image_src = !empty($package['image']) ? 'imgs/' . $package['image'] : 'imgs/default.jpg';
                        ?>
                        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="Package Image" style="width: 60px; height: 60px;" class="w-16 h-16 object-cover rounded-md border aspect-square">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($package['name']); ?></td>
                    
                   
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                        <?php 
                        if (!empty($package['items'])) {
                            $items = explode(', ', $package['items']);
                            echo '<div class="space-y-1">';
                            foreach ($items as $item) {
                                // Extract quantity from the item string (e.g., "2x Product Name (Flavor - Size)")
                                if (preg_match('/^(\d+)x\s+(.+)$/', $item, $matches)) {
                                    $quantity = $matches[1];
                                    $product_info = $matches[2];
                                    echo '<div class="flex items-center space-x-2 whitespace-nowrap">';
                                    echo '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-cyan-100 text-blue-800 dark:bg-cyan-900 dark:text-blue-300">' . $quantity . 'x</span>';
                                    echo '<span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">' . htmlspecialchars($product_info) . '</span>';
                                    echo '</div>';
                                } else {
                                    // Fallback for items that don't match the expected format
                                    echo '<div class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">' . htmlspecialchars($item) . '</div>';
                                }
                            }
                            echo '</div>';
                        } else {
                            echo '<span class="text-gray-400 italic">No items</span>';
                        }
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if ($package['b2b'] == 1): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                B2B
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                B2C
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($package['description']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo number_format($package['discount_percentage'], 1); ?>%</td>

                    <?php
                    $subscription_types->data_seek(0);
                    while ($sub_type = $subscription_types->fetch_assoc()): 
                        $subscription_discount = '';
                        $sql = "SELECT discount_percentage FROM package_subscription_prices WHERE package_id = ? AND subscription_type_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $package['id'], $sub_type['id']);
                        $stmt->execute();
                        $sub_result = $stmt->get_result();
                        if ($sub_row = $sub_result->fetch_assoc()) {
                            $subscription_discount = number_format($sub_row['discount_percentage'], 1) . '%';
                        }
                        ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo $subscription_discount; ?></td>
                    <?php endwhile; ?>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        <button onclick="editPackage(<?php echo htmlspecialchars(json_encode($package)); ?>)" class="text-primary hover:text-primary/80 mr-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="deletePackage(<?php echo $package['id']; ?>)" class="text-red-600 hover:text-red-800">
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

<!-- Add Package Modal -->
<div id="addPackageModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[800px] shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Package</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <!-- B2B/B2C Toggle in top right of form -->
                <div class="flex justify-end mb-4">
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-700 dark:text-gray-300">B2C</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="b2b" value="0">
                            <input type="checkbox" name="b2b" value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm text-gray-700 dark:text-gray-300">B2B</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                    </div>

                    <div>
                        <label for="discount_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Percentage</label>
                        <input type="number" step="0.01" name="discount_percentage" id="discount_percentage" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Package Image</label>
                        <input type="file" name="image" id="image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:bg-gray-700 dark:text-gray-400
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-medium
                        file:bg-primary file:text-white
                        hover:file:bg-primary/90">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG up to 5MB</p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" id="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1"></textarea>
                    </div>
                    
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <!-- Products Column -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Products</h4>
                        <div class="max-h-[400px] overflow-y-auto">
                            <?php 
                            $products->data_seek(0);
                            while ($product = $products->fetch_assoc()): 
                            ?>
                            <div class="flex items-center space-x-2 mb-2">
                                <label class="flex-1 text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($product['name'] . ' (' . $product['flavor'] . ' - ' . $product['size'] . ')'); ?>
                                </label>
                                <input type="number" name="products[<?php echo $product['id']; ?>]" value="0" min="0" class="w-20 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Subscription Prices Column -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Subscription Prices</h4>
                        <div class="space-y-3">
                            <?php 
                            $subscription_types->data_seek(0);
                            while ($subscription = $subscription_types->fetch_assoc()): 
                            ?>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($subscription['name']); ?></label>
                                <div>
                                    <input type="number" step="0.01" name="subscription_prices[<?php echo $subscription['id']; ?>][discount]" placeholder="Discount %" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="document.getElementById('addPackageModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Add Package
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Package Modal -->
<div id="editPackageModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-[800px] shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Package</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <!-- B2B/B2C Toggle in top right of form -->
                <div class="flex justify-end mb-4">
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-700 dark:text-gray-300">B2C</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="b2b" value="0">
                            <input type="checkbox" name="b2b" value="1" id="edit_b2b" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm text-gray-700 dark:text-gray-300">B2B</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" id="edit_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                    </div>

                    <div>
                        <label for="edit_discount_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Percentage</label>
                        <input type="number" step="0.01" name="discount_percentage" id="edit_discount_percentage" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="edit_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea name="description" id="edit_description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1"></textarea>
                </div>

                <div class="mb-4">
                    <label for="edit_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Package Image</label>
                    <div id="current-image-container" class="mb-2" style="display: none;">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Current image:</p>
                        <img id="current-image-preview" src="" alt="Current package image" class="w-20 h-20 object-cover rounded-md border">
                    </div>
                    <input type="file" name="image" id="edit_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:bg-gray-700 dark:text-gray-400 rounded-md
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-medium
                        file:bg-primary file:text-white
                        hover:file:bg-primary/90">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, JPEG up to 5MB. Leave empty to keep current image.</p>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <!-- Products Column -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Products</h4>
                        <div class="max-h-[400px] overflow-y-auto">
                            <?php 
                            $products->data_seek(0);
                            while ($product = $products->fetch_assoc()): 
                            ?>
                            <div class="flex items-center space-x-2 mb-2">
                                <label class="flex-1 text-sm text-gray-700 dark:text-gray-300">
                                    <?php echo htmlspecialchars($product['name'] . ' (' . $product['flavor'] . ' - ' . $product['size'] . ')'); ?>
                                </label>
                                <input type="number" name="products[<?php echo $product['id']; ?>]" value="0" min="0" class="w-20 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Subscription Prices Column -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Subscription Discounts</h4>
                        <div class="space-y-3">
                            <?php 
                            $subscription_types->data_seek(0);
                            while ($subscription = $subscription_types->fetch_assoc()): 
                            ?>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($subscription['name']); ?></label>
                                <input type="number" step="0.01" name="subscription_prices[<?php echo $subscription['id']; ?>][discount]" placeholder="Discount %" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white p-1">
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="document.getElementById('editPackageModal').classList.add('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Update Package
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
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Delete Package</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Are you sure you want to delete this package?</p>
            <form method="POST" class="mt-4">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
function editPackage(package) {
    document.getElementById('edit_id').value = package.id;
    document.getElementById('edit_name').value = package.name;
    document.getElementById('edit_description').value = package.description;
    document.getElementById('edit_discount_percentage').value = package.discount_percentage;
    
    // Set the B2B toggle switch
    const b2bCheckbox = document.getElementById('edit_b2b');
    if (b2bCheckbox) {
        b2bCheckbox.checked = package.b2b == 1;
    }
    
    // Handle current image display
    const currentImageContainer = document.getElementById('current-image-container');
    const currentImagePreview = document.getElementById('current-image-preview');
    if (package.image && package.image !== '') {
        currentImagePreview.src = 'imgs/' + package.image;
        currentImageContainer.style.display = 'block';
    } else {
        currentImageContainer.style.display = 'none';
    }
    
    // Show the modal
    document.getElementById('editPackageModal').classList.remove('hidden');
    
    // Fetch package items
    fetch(`get_package_items.php?package_id=${package.id}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(item => {
                const input = document.querySelector(`#editPackageModal input[name="products[${item.product_id}]"]`);
                if (input) {
                    input.value = item.quantity;
                }
            });
        });

    // Fetch subscription prices
    fetch(`get_subscription_prices.php?package_id=${package.id}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(item => {
                const discountInput = document.querySelector(`#editPackageModal input[name="subscription_prices[${item.subscription_type_id}][discount]"]`);
                
                if (discountInput) {
                    discountInput.value = item.discount_percentage;
                }
            });
        });
}

// Clear product quantities when edit modal is closed
document.querySelector('#editPackageModal button[onclick="document.getElementById(\'editPackageModal\').classList.add(\'hidden\')"]').addEventListener('click', function() {
    const productInputs = document.querySelectorAll('#editPackageModal input[name^="products["]');
    productInputs.forEach(input => {
        input.value = 0;
    });
});

function deletePackage(id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>