<?php
require_once 'config.php';
requireLogin();
requirePermission('orders', 'view');

// Get filter parameters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build WHERE clause for filters
$whereConditions = [];
$queryParams = [];

if (!empty($date_from)) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $queryParams[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $queryParams[] = $date_to;
}

if (!empty($status_filter)) {
    $whereConditions[] = "o.status = ?";
    $queryParams[] = $status_filter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Calculate pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total orders with filters
$countQuery = "SELECT COUNT(*) as total FROM orders o LEFT JOIN customers c ON o.customer_id = c.id $whereClause";
if (!empty($queryParams)) {
    $countStmt = $conn->prepare($countQuery);
    if (count($queryParams) == 1) {
        $countStmt->bind_param('s', $queryParams[0]);
    } elseif (count($queryParams) == 2) {
        $countStmt->bind_param('ss', $queryParams[0], $queryParams[1]);
    } elseif (count($queryParams) == 3) {
        $countStmt->bind_param('sss', $queryParams[0], $queryParams[1], $queryParams[2]);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalOrders = $countResult->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $countResult = $conn->query($countQuery);
    $totalOrders = $countResult->fetch_assoc()['total'];
}
$totalPages = ceil($totalOrders / $limit);

// Fetch orders with pagination and filters
$query = "SELECT o.id, o.order_reference, o.status, o.total_amount, o.subtotal, o.shipping_cost, o.created_at,
                 c.customer_name, c.email, c.phone, c.business_name,
                 COUNT(oi.id) as item_count
          FROM orders o
          LEFT JOIN customers c ON o.customer_id = c.id
          LEFT JOIN order_items oi ON o.id = oi.order_id
          $whereClause
          GROUP BY o.id, o.order_reference, o.status, o.total_amount, o.subtotal, o.shipping_cost, o.created_at,
                   c.customer_name, c.email, c.phone, c.business_name
          ORDER BY o.created_at DESC
          LIMIT $limit OFFSET $offset";

if (!empty($queryParams)) {
    $stmt = $conn->prepare($query);
    if (count($queryParams) == 1) {
        $stmt->bind_param('s', $queryParams[0]);
    } elseif (count($queryParams) == 2) {
        $stmt->bind_param('ss', $queryParams[0], $queryParams[1]);
    } elseif (count($queryParams) == 3) {
        $stmt->bind_param('sss', $queryParams[0], $queryParams[1], $queryParams[2]);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Function to build pagination URLs with filter parameters
function buildPaginationUrl($pageNum) {
    $params = [];
    if (!empty($_GET['date_from'])) $params['date_from'] = $_GET['date_from'];
    if (!empty($_GET['date_to'])) $params['date_to'] = $_GET['date_to'];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

// Handle status updates and deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_status' && isset($_POST['order_id'], $_POST['status'])) {
        requirePermission('orders', 'edit');
        $order_id = (int)$_POST['order_id'];
        $status = $_POST['status'];
        
        $allowed_statuses = ['pending', 'processing', 'completed', 'canceled'];
        if (in_array($status, $allowed_statuses)) {
            // Check if status column exists, if not add it
            $checkStatusColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
            if ($checkStatusColumn->num_rows == 0) {
                $conn->query("ALTER TABLE orders ADD COLUMN status ENUM('pending','processing','completed','canceled') NOT NULL DEFAULT 'pending' AFTER order_reference");
            }
            
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $order_id);
            if ($stmt->execute()) {
                $stmt->close();
                logAdminAction('update_order_status', "Updated order $order_id status to $status", $order_id);
                $_SESSION['success'] = 'Order status has been updated successfully.';
                header('Location: orders.php');
                exit;
            } else {
                $stmt->close();
                $_SESSION['error'] = 'Failed to update order status.';
                header('Location: orders.php');
                exit;
            }
        }
    } elseif ($_POST['action'] === 'delete_order' && isset($_POST['order_id'])) {
        requirePermission('orders', 'delete');
        $order_id = (int)$_POST['order_id'];
        
        $conn->begin_transaction();
        try {
            // Delete order items first (foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $stmt->close();
            
            // Delete the order
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            logAdminAction('delete_order', "Deleted order $order_id", $order_id);
            $_SESSION['success'] = 'Order has been deleted successfully.';
            header('Location: orders.php');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to delete order. Please try again.';
            header('Location: orders.php');
            exit;
        }
    }
}

ob_start();
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Orders Management</h1>
        <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Showing <?php echo min($offset + 1, $totalOrders); ?>-<?php echo min($offset + $limit, $totalOrders); ?> of <?php echo $totalOrders; ?> orders
            </div>
            
            <!-- Admin Controls -->
            <div class="flex items-center space-x-3">
                <?php if (hasPermission('orders', 'create')): ?>
                <a href="purchase_order_request.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <ion-icon name="add-circle-outline" class="w-4 h-4 align-middle" aria-hidden="true"></ion-icon>
                    <span>Add New Order</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <!-- Preserve current page -->
            <input type="hidden" name="page" value="1">
            
            <!-- Date Range Filter -->
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                       class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Status Filter -->
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="canceled" <?php echo $status_filter === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <ion-icon name="filter-outline" class="w-4 h-4 mr-2 align-middle" aria-hidden="true"></ion-icon>
                    Apply Filters
                </button>
                <a href="orders.php" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                    <ion-icon name="close-circle-outline" class="w-4 h-4 mr-2 align-middle" aria-hidden="true"></ion-icon>
                    Clear Filters
                </a>
            </div>
            
            <!-- Quick Date Filters -->
            <div class="flex flex-col">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quick Filters</label>
                <div class="flex gap-2">
                    <button type="button" onclick="setDateRange('today')" class="px-3 py-1 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs rounded hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors">Today</button>
                    <button type="button" onclick="setDateRange('week')" class="px-3 py-1 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs rounded hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors">This Week</button>
                    <button type="button" onclick="setDateRange('month')" class="px-3 py-1 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs rounded hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors">This Month</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded dark:bg-green-900 dark:text-green-200">
            Order status updated successfully!
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded dark:bg-green-900 dark:text-green-200">
            Order deleted successfully!
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded dark:bg-red-900 dark:text-red-200">
            Error updating order status. Please try again.
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['delete_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded dark:bg-red-900 dark:text-red-200">
            Error deleting order. Please try again.
        </div>
    <?php endif; ?>

    <!-- Orders Table -->
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Order Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subtotal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Shipping</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($order = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($order['order_reference']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($order['email'] ?? ''); ?></div>
                                <?php if ($order['business_name']): ?>
                                    <div class="text-xs text-gray-400 dark:text-gray-500"><?php echo htmlspecialchars($order['business_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border-0 focus:ring-2 focus:ring-blue-500 <?php
                                        $current_status = $order['status'] ?: 'pending'; // Default to pending if empty
                                        echo 'status-' . $current_status;
                                    ?>">
                                        <option value="pending" <?php echo $current_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $current_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo $current_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="canceled" <?php echo $current_status === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                $<?php echo number_format($order['subtotal'] ?? 0, 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                $<?php echo number_format($order['shipping_cost'] ?? 0, 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                $<?php echo number_format($order['total_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <?php if (hasPermission('orders', 'edit')): ?>
                                <a href="purchase_order_request.php?edit=<?php echo $order['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    <ion-icon name="create-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Edit
                                </a>
                                <?php endif; ?>
                                
                                <a href="print_order.php?id=<?php echo $order['id']; ?>" target="_blank"
                                   class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
                                    <ion-icon name="print-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Print
                                </a>
                                
                                <?php if (hasPermission('orders', 'delete')): ?>
                                <button onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_reference'], ENT_QUOTES); ?>')" 
                                        class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors">
                                    <ion-icon name="trash-outline" class="w-3 h-3 mr-1 align-middle" aria-hidden="true"></ion-icon>
                                    Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="text-lg">No orders found</div>
                                <div class="text-sm mt-1">Orders will appear here once customers submit purchase requests</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                    <a href="<?php echo buildPaginationUrl($page - 1); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo buildPaginationUrl($page + 1); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        Next
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-medium"><?php echo min($offset + 1, $totalOrders); ?></span> to 
                        <span class="font-medium"><?php echo min($offset + $limit, $totalOrders); ?></span> of 
                        <span class="font-medium"><?php echo $totalOrders; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildPaginationUrl($page - 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                <span class="sr-only">Previous</span>
                                <ion-icon name="chevron-back-outline" class="h-5 w-5 align-middle" aria-hidden="true"></ion-icon>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1): ?>
                            <a href="<?php echo buildPaginationUrl(1); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">1</a>
                            <?php if ($start > 2): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-200 dark:border-blue-600"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300">...</span>
                            <?php endif; ?>
                            <a href="<?php echo buildPaginationUrl($totalPages); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildPaginationUrl($page + 1); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                <span class="sr-only">Next</span>
                                <ion-icon name="chevron-forward-outline" class="h-5 w-5 align-middle" aria-hidden="true"></ion-icon>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden delete form -->
<form id="delete-form" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_order">
    <input type="hidden" name="order_id" id="delete-order-id">
</form>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                <ion-icon name="trash-outline" class="h-6 w-6 text-red-600 dark:text-red-400 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mt-4">Delete Order</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete order "<span id="modal-order-ref" class="font-semibold text-gray-900 dark:text-white"></span>"?
                </p>
                <div class="mt-3 text-left">
                    <p class="text-sm text-red-600 dark:text-red-400 font-medium">This action cannot be undone and will permanently remove:</p>
                    <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
                        <li>Order details</li>
                        <li>All order items</li>
                        <li>Customer information for this order</li>
                    </ul>
                </div>
            </div>
            <div class="items-center px-4 py-3">
                <div class="flex space-x-3">
                    <button id="confirmDelete" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 transition-colors">
                        Delete Order
                    </button>
                    <button id="cancelDelete" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Status dropdown styling for dark mode */
.dark select {
    border: 1px solid #374151;
    background-color: #1f2937;
    color: #f9fafb;
}

/* Status-specific styling that works in both light and dark modes */
.status-pending {
    background-color: #fef3c7 !important;
    color: #303030ff !important;
}
.dark .status-pending {
    background-color: #362e29ff !important;
    color: #fbbf24 !important;
}

.status-processing {
    background-color: #dbeafe !important;
    color: #1e40af !important;
}
.dark .status-processing {
    background-color: #313747ff !important;
    color: #60a5fa !important;
}

.status-completed {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
}
.dark .status-completed {
    background-color: #23312dff !important;
    color: #34d399 !important;
}

.status-canceled {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
}
.dark .status-canceled {
    background-color: #3d2d2dff !important;
    color: #f87171 !important;
}
</style>

<script>
// Delete confirmation function with modal
let currentOrderId = null;

function confirmDelete(orderId, orderReference) {
    currentOrderId = orderId;
    document.getElementById('modal-order-ref').textContent = orderReference;
    document.getElementById('deleteModal').classList.remove('hidden');
}

// Modal event handlers
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDelete');
    const cancelBtn = document.getElementById('cancelDelete');
    
    // Confirm delete
    confirmBtn.addEventListener('click', function() {
        if (currentOrderId) {
            document.getElementById('delete-order-id').value = currentOrderId;
            document.getElementById('delete-form').submit();
        }
    });
    
    // Cancel delete
    cancelBtn.addEventListener('click', function() {
        modal.classList.add('hidden');
        currentOrderId = null;
    });
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            currentOrderId = null;
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            currentOrderId = null;
        }
    });
});

// Quick date filter functions
function setDateRange(period) {
    const today = new Date();
    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput = document.querySelector('input[name="date_to"]');
    
    let fromDate, toDate;
    
    switch(period) {
        case 'today':
            fromDate = toDate = today;
            break;
        case 'week':
            // Start of current week (Sunday)
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - today.getDay());
            toDate = today;
            break;
        case 'month':
            // Start of current month
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = today;
            break;
    }
    
    // Format dates as YYYY-MM-DD for HTML date inputs
    dateFromInput.value = fromDate.toISOString().split('T')[0];
    dateToInput.value = toDate.toISOString().split('T')[0];
    
    // Auto-submit the form
    dateFromInput.form.submit();
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>