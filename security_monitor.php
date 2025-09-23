<?php
require_once 'config.php';
requireLogin();

// Only allow admin users (you may want to add a role system)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get filter parameters
$filter_type = $_GET['filter'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_type !== 'all') {
    $where_conditions[] = "event_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Count total records
$countQuery = "SELECT COUNT(*) as total FROM security_log $where_clause";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($param_types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get security logs
$query = "SELECT * FROM security_log $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Log access to security monitor
logAdminAction('view_security_logs', 'Accessed security monitoring dashboard');

ob_start();
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Security Monitor</h1>
    <div class="flex items-center space-x-4">
        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Type</label>
                <select name="filter" class="border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Events</option>
                    <option value="failed_login" <?php echo $filter_type === 'failed_login' ? 'selected' : ''; ?>>Failed Logins</option>
                    <option value="successful_login" <?php echo $filter_type === 'successful_login' ? 'selected' : ''; ?>>Successful Logins</option>
                    <option value="login_blocked" <?php echo $filter_type === 'login_blocked' ? 'selected' : ''; ?>>Login Blocked</option>
                    <option value="csrf_violation" <?php echo $filter_type === 'csrf_violation' ? 'selected' : ''; ?>>CSRF Violations</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                       class="border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                       class="border border-gray-300 rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Filter
                </button>
                <a href="security_monitor.php" class="ml-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Clear
                </a>
            </div>
        </form>
    </div>

<!-- Security Stats -->
<div class="mt-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-stretch">
        <?php
        $stats = [
            'failed_login' => ['title' => 'Failed Logins (24h)', 'color' => 'red'],
            'successful_login' => ['title' => 'Successful Logins (24h)', 'color' => 'green'],
            'login_blocked' => ['title' => 'Blocked IPs (24h)', 'color' => 'orange'],
            'csrf_violation' => ['title' => 'CSRF Violations (24h)', 'color' => 'purple']
        ];
        
        foreach ($stats as $type => $config):
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM security_log WHERE event_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $countStmt->bind_param('s', $type);
            $countStmt->execute();
            $count = $countStmt->get_result()->fetch_assoc()['count'];
        ?>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow flex flex-col justify-between">
            <div class="flex items-center">
                <div class="p-2 bg-<?php echo $config['color']; ?>-100 dark:bg-<?php echo $config['color']; ?>-900 rounded-lg">
                    <svg class="w-6 h-6 text-<?php echo $config['color']; ?>-600 dark:text-<?php echo $config['color']; ?>-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400"><?php echo $config['title']; ?></p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?php echo $count; ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<!-- Security Logs Table -->
<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mt-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Event Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User Agent</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php
                                    switch($log['event_type']) {
                                        case 'failed_login': echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; break;
                                        case 'successful_login': echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'; break;
                                        case 'login_blocked': echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'; break;
                                        case 'csrf_violation': echo 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'; break;
                                        default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
                                    }
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['event_type'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($log['message']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php echo htmlspecialchars($log['user_agent']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="text-lg">No security events found</div>
                                <div class="text-sm mt-1">Security events will appear here when they occur</div>
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
                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        Previous
                    </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        Next
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Showing <span class="font-medium"><?php echo min($offset + 1, $totalRecords); ?></span> to 
                        <span class="font-medium"><?php echo min($offset + $limit, $totalRecords); ?></span> of 
                        <span class="font-medium"><?php echo $totalRecords; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php
                        $baseUrl = "?filter=" . urlencode($filter_type) . "&date_from=" . urlencode($date_from) . "&date_to=" . urlencode($date_to);
                        
                        for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600 dark:bg-blue-900 dark:text-blue-200"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>