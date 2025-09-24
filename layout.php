<?php
require_once 'config.php';
// requireLogin();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'Product Management System'; ?></title>
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="imgs/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="imgs/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="imgs/favicons/favicon-16x16.png">
    <link rel="manifest" href="imgs/favicons/site.webmanifest">
    <link rel="mask-icon" href="imgs/favicons/safari-pinned-tab.svg" color="#3B82F6">
    <link rel="shortcut icon" href="imgs/favicons/favicon.ico">
    <meta name="msapplication-TileColor" content="<?php echo defined('APP_THEME_COLOR') ? APP_THEME_COLOR : '#3B82F6'; ?>">
    <meta name="msapplication-config" content="imgs/favicons/browserconfig.xml">
    <meta name="theme-color" content="<?php echo defined('APP_THEME_COLOR') ? APP_THEME_COLOR : '#3B82F6'; ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Ionicons CDN -->
    <script type="module" src="https://unpkg.com/ionicons@7.2.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.2.2/dist/ionicons/ionicons.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#6B7280',
                    }
                }
            }
        }
    </script>
    <style>
        input, select, textarea {
            
            background:rgb(241, 241, 241);
           
        }
    </style>
</head>
    <body class="h-full bg-gray-100 dark:bg-gray-900 <?php echo !isset($hide_sidebar) ? 'pl-64' : ''; ?>">
    <?php if (!isset($hide_header)): ?>
        <!-- Your header content -->
    <?php endif; ?>

    <?php if (!isset($hide_sidebar)): ?>
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 shadow-lg">
                <div class="flex h-16 items-center justify-between px-4 border-b dark:border-gray-700">
                    <img class="h-8 w-auto" src="imgs/yfs.png" alt="YFS Logo">
                </div>
                <nav class="mt-5 px-2">
                    <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
                    <a href="index.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'index.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="home-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'index.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Dashboard
                    </a>
                    <a href="products.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'products.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="cube-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'products.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                            <path d="m74 42a2 2 0 0 1 2 1.85v28.15a6 6 0 0 1 -5.78 6h-40.22a6 6 0 0 1 -6-5.78v-28.22a2 2 0 0 1 1.85-2zm-15.5 8.34-.12.1-11.45 12.41-5.2-5a1.51 1.51 0 0 0 -2-.1l-.11.1-2.14 1.92a1.2 1.2 0 0 0 -.1 1.81l.1.11 7.33 6.94a3.07 3.07 0 0 0 2.14.89 2.81 2.81 0 0 0 2.13-.89l5.92-6.29.43-.44.42-.45.55-.58.21-.22.42-.44 5.62-5.93a1.54 1.54 0 0 0 .08-1.82l-.08-.1-2.14-1.92a1.51 1.51 0 0 0 -2.01-.1zm15.5-28.34a6 6 0 0 1 6 6v6a2 2 0 0 1 -2 2h-56a2 2 0 0 1 -2-2v-6a6 6 0 0 1 6-6z"></path>
                        </svg>
                        Products
                    </a>
                    <a href="packages.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'packages.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="cube" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'packages.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Packages
                    </a>
                    <a href="subscriptions.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'subscriptions.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="repeat-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'subscriptions.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Subscriptions
                    </a>
                    <a href="price_sheet.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'price_sheet.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="pricetag-outline" class="w-6 h-6 mr-2 align-middle <?php echo ($current_page == 'price_sheet.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Price Sheet
                    </a>
                    <a href="orders.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'orders.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="receipt-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'orders.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Orders
                    </a>
                    <a href="security_monitor.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'security_monitor.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="shield-checkmark-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'security_monitor.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        Security Monitor
                    </a>
                    
                    <?php if (hasRole('super_admin')): ?>
                    <a href="users.php" class="mt-1 group flex items-center px-2 py-2 text-base font-medium rounded-md <?php echo ($current_page == 'users.php') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white'; ?>">
                        <ion-icon name="people-outline" class="w-5 h-5 mr-3 align-middle <?php echo ($current_page == 'users.php') ? 'text-white' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-400 dark:group-hover:text-gray-300'; ?>" aria-hidden="true"></ion-icon>
                        User Management
                    </a>
                    <?php endif; ?>

                    <!-- Order Status Notification -->
                    <?php if (hasRole('admin') || hasRole('super_admin')): ?>
                    <div class="mt-4 px-2">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                            <h4 class="text-xs font-medium text-gray-900 dark:text-white mb-2 flex items-center">
                                <ion-icon name="notifications-outline" class="w-4 h-4 mr-2 align-middle" aria-hidden="true"></ion-icon>
                                Order Status
                            </h4>
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-orange-600 dark:text-orange-400 flex items-center">
                                        <div class="w-2 h-2 bg-orange-500 rounded-full mr-2"></div>
                                        Pending
                                    </span>
                                    <span id="pendingCount" class="font-medium text-gray-900 dark:text-white">0</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-blue-600 dark:text-blue-400 flex items-center">
                                        <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                        Processing
                                    </span>
                                    <span id="processingCount" class="font-medium text-gray-900 dark:text-white">0</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-500">
                                <a href="orders.php" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center">
                                    View All Orders
                                    <ion-icon name="arrow-forward" class="w-3 h-3 ml-1 align-middle" aria-hidden="true"></ion-icon>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Logout Link - Always at bottom of navigation -->
                    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <a href="logout.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-red-600 hover:bg-red-100 hover:text-red-900 dark:text-red-400 dark:hover:bg-red-900 dark:hover:text-red-200">
                            <ion-icon name="log-out-outline" class="w-5 h-5 mr-3 text-red-600 group-hover:text-red-900 dark:text-red-400 dark:group-hover:text-red-200 align-middle" aria-hidden="true"></ion-icon>
                            Logout
                        </a>
                    </div>
                </nav>
                <div class="absolute bottom-0 left-0 w-full p-4 border-t dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">YFSuite Panel</h1>
                        <?php if (empty($force_light)): ?>
                        <button id="theme-toggle" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700">
                            <ion-icon name="sunny-outline" class="w-5 h-5 text-gray-800 dark:text-white hidden dark:block align-middle" aria-hidden="true"></ion-icon>
                            <ion-icon name="moon-outline" class="w-5 h-5 text-gray-800 dark:text-white block dark:hidden align-middle" aria-hidden="true"></ion-icon>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main content -->
        <main class="min-h-screen">
            <div class="py-6">
                <div class="mx-auto <?php echo in_array(basename($_SERVER['PHP_SELF']), ['price_sheet.php', 'packages.php', 'products.php', 'subscriptions.php', 'orders.php', 'security_monitor.php', 'index.php']) ? '' : 'max-w-7xl'; ?> px-4 sm:px-6 md:px-8">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="mb-4 rounded-md bg-green-50 p-4 dark:bg-green-900">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <ion-icon name="checkmark-circle-outline" class="h-5 w-5 text-green-400 align-middle" aria-hidden="true"></ion-icon>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mb-4 rounded-md bg-red-50 p-4 dark:bg-red-900">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <ion-icon name="close-circle-outline" class="h-5 w-5 text-red-400 align-middle" aria-hidden="true"></ion-icon>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php echo $content ?? ''; ?>
                </div>
            </div>
        </main>

        <script>
        // Theme toggle functionality
        const html = document.documentElement;
        <?php if (empty($force_light)): ?>
        const themeToggle = document.getElementById('theme-toggle');
        // Check for saved theme preference
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
            });
        }
        <?php else: ?>
        // Per-page force_light is set — ensure this page stays light and do not read/write global preference
        html.classList.remove('dark');
        <?php endif; ?>

        <?php if (!isset($hide_sidebar) && (hasRole('admin') || hasRole('super_admin'))): ?>
        // Order Status Notification System (Sidebar)
        function fetchOrderCounts() {
            fetch('ajax_order_counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateOrderCounts(data.pending, data.processing);
                    }
                })
                .catch(error => {
                    console.error('Error fetching order counts:', error);
                });
        }

        function updateOrderCounts(pending, processing) {
            const pendingElement = document.getElementById('pendingCount');
            const processingElement = document.getElementById('processingCount');
            
            if (pendingElement) pendingElement.textContent = pending;
            if (processingElement) processingElement.textContent = processing;
        }

        // Initialize and set up periodic checking
        document.addEventListener('DOMContentLoaded', function() {
            fetchOrderCounts();
            // Check for updates every 30 seconds
            setInterval(fetchOrderCounts, 30000);
        });
        <?php endif; ?>
        </script>
</body>
</html>