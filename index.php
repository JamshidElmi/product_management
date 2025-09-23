<?php

// Add this at the top of both files to debug
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// session_start();
// var_dump($_SESSION);


session_start();

// Check if user is NOT logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
requireLogin();

// Get counts
$product_count = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$package_count = $conn->query("SELECT COUNT(*) as count FROM packages")->fetch_assoc()['count'];
$subscription_count = $conn->query("SELECT COUNT(*) as count FROM subscription_types")->fetch_assoc()['count'];

// Get order counts
$pending_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending' OR status IS NULL")->fetch_assoc()['count'];
$processing_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'processing'")->fetch_assoc()['count'];
$new_orders_24h = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['count'];

// Get security counts (24h)
$failed_logins_24h = 0;
$blocked_ips_24h = 0;

// Check if login_attempts table exists and get failed logins
$loginAttemptsCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
if ($loginAttemptsCheck && $loginAttemptsCheck->num_rows > 0) {
    // First check what columns exist in login_attempts table
    $columnsResult = $conn->query("SHOW COLUMNS FROM login_attempts");
    $columns = [];
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    // Find the timestamp column that actually exists
    $timeColumn = null;
    if (in_array('created_at', $columns)) {
        $timeColumn = 'created_at';
    } elseif (in_array('attempt_time', $columns)) {
        $timeColumn = 'attempt_time';
    } elseif (in_array('timestamp', $columns)) {
        $timeColumn = 'timestamp';
    }
    
    // Check for success column
    $successColumn = '';
    if (in_array('success', $columns)) {
        $successColumn = ' AND success = 0';
    } elseif (in_array('failed', $columns)) {
        $successColumn = ' AND failed = 1';
    }
    
    // Only proceed if we have a timestamp column
    if ($timeColumn) {
        $query = "SELECT COUNT(*) as count FROM login_attempts WHERE $timeColumn >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" . $successColumn;
        $result = $conn->query($query);
        if ($result) {
            $failed_logins_24h = $result->fetch_assoc()['count'];
        }
    }
}

// Check if security_log table exists and get blocked IPs
$securityLogCheck = $conn->query("SHOW TABLES LIKE 'security_log'");
if ($securityLogCheck && $securityLogCheck->num_rows > 0) {
    // Check what columns exist in security_log table
    $columnsResult = $conn->query("SHOW COLUMNS FROM security_log");
    $securityColumns = [];
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $securityColumns[] = $col['Field'];
        }
    }
    
    // Find the timestamp column that actually exists
    $timeColumn = null;
    if (in_array('created_at', $securityColumns)) {
        $timeColumn = 'created_at';
    } elseif (in_array('timestamp', $securityColumns)) {
        $timeColumn = 'timestamp';
    } elseif (in_array('event_time', $securityColumns)) {
        $timeColumn = 'event_time';
    }
    
    // Only proceed if we have both required columns and a timestamp column
    if ($timeColumn && in_array('event_type', $securityColumns) && in_array('ip_address', $securityColumns)) {
        $query = "SELECT COUNT(DISTINCT ip_address) as count FROM security_log WHERE $timeColumn >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND event_type LIKE '%block%'";
        $result = $conn->query($query);
        if ($result) {
            $blocked_ips_24h = $result->fetch_assoc()['count'];
        }
    }
}

// Get recent products
$recent_products = $conn->query("
    SELECT * FROM products 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Get recent packages
$recent_packages = $conn->query("
    SELECT p.*, GROUP_CONCAT(CONCAT(pi.quantity, 'x ', pr.name, ' (', pr.size, ')') SEPARATOR ', ') as items
    FROM packages p
    LEFT JOIN package_items pi ON p.id = pi.package_id
    LEFT JOIN products pr ON pi.product_id = pr.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");

// Get recent security events
$recent_security = null;
$securityLogCheck = $conn->query("SHOW TABLES LIKE 'security_log'");
if ($securityLogCheck && $securityLogCheck->num_rows > 0) {
    // Check what columns exist in security_log table (reuse from above if already checked)
    if (!isset($securityColumns)) {
        $columnsResult = $conn->query("SHOW COLUMNS FROM security_log");
        $securityColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $securityColumns[] = $col['Field'];
            }
        }
    }
    
    // Find the timestamp column that actually exists
    $timeColumn = null;
    if (in_array('created_at', $securityColumns)) {
        $timeColumn = 'created_at';
    } elseif (in_array('timestamp', $securityColumns)) {
        $timeColumn = 'timestamp';
    } elseif (in_array('event_time', $securityColumns)) {
        $timeColumn = 'event_time';
    }
    
    // Build SELECT clause with available columns, only if we have a timestamp
    if ($timeColumn) {
        $selectColumns = [];
        if (in_array('event_type', $securityColumns)) $selectColumns[] = 'event_type';
        if (in_array('message', $securityColumns)) $selectColumns[] = 'message';
        if (in_array('ip_address', $securityColumns)) $selectColumns[] = 'ip_address';
        if (in_array('user_agent', $securityColumns)) $selectColumns[] = 'user_agent';
        $selectColumns[] = "$timeColumn as created_at"; // Always include timestamp with alias
        
        if (!empty($selectColumns)) {
            $selectClause = implode(', ', $selectColumns);
            $recent_security = $conn->query("
                SELECT $selectClause
                FROM security_log 
                ORDER BY $timeColumn DESC 
                LIMIT 10
            ");
        }
    }
}

ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
    <a href="price_sheet.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
        View Price Sheet
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                <ion-icon name="cube-outline" class="w-6 h-6 text-blue-600 dark:text-blue-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Products</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $product_count; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                <ion-icon name="cube" class="w-6 h-6 text-green-600 dark:text-green-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Packages</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $package_count; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                <ion-icon name="repeat-outline" class="w-6 h-6 text-purple-600 dark:text-purple-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Subscription Types</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $subscription_count; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                <ion-icon name="hourglass-outline" class="w-6 h-6 text-yellow-600 dark:text-yellow-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Orders</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $pending_orders; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Second Row of Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900">
                <ion-icon name="sync-outline" class="w-6 h-6 text-indigo-600 dark:text-indigo-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Processing Orders</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $processing_orders; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                <ion-icon name="flash-outline" class="w-6 h-6 text-red-600 dark:text-red-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">New Orders (24h)</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $new_orders_24h; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                <ion-icon name="alert-circle-outline" class="w-6 h-6 text-orange-600 dark:text-orange-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Failed Logins (24h)</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $failed_logins_24h; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-600">
                <ion-icon name="ban-outline" class="w-6 h-6 text-gray-600 dark:text-gray-300 align-middle" aria-hidden="true"></ion-icon>
            </div>
            <div class="ml-4">
                <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Blocked IPs (24h)</h2>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $blocked_ips_24h; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Tables - Two Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Recent Products -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent Products</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">MSRP</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($product = $recent_products->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($product['size']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">$<?php echo number_format($product['msrp_price'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Packages -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent Packages</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Discount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Items</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($package = $recent_packages->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($package['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"><?php echo number_format($package['discount_percentage'], 1); ?>%</td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($package['items']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Security Monitor Records -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Recent Security Events</h2>
            <a href="security_monitor.php" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">View All →</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <?php if ($recent_security && $recent_security->num_rows > 0): ?>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event Type</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User Agent</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($event = $recent_security->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                <?php 
                                // $event_type = strtolower($event['event_type']);
                                // if (strpos($event_type, 'login') !== false) {
                                //     echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2';
                                // } elseif (strpos($event_type, 'block') !== false || strpos($event_type, 'failed_login') !== false) {
                                //     echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 px-2';
                                // } else {
                                //     echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2';
                                // }
                                switch($event['event_type']) {
                                        case 'failed_login': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; break;
                                        case 'successful_login': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; break;
                                        case 'login_blocked': echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'; break;
                                        case 'csrf_violation': echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'; break;
                                        default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                    }
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-xs truncate">
                            <?php echo htmlspecialchars($event['message'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($event['ip_address'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                            <?php echo htmlspecialchars($event['user_agent'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo $event['created_at'] ? date('M j, H:i', strtotime($event['created_at'])) : 'N/A'; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                <ion-icon name="shield-outline" class="mx-auto h-12 w-12 text-gray-400 align-middle" aria-hidden="true"></ion-icon>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No security events</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Security monitoring data will appear here when available.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'layout.php';
?>