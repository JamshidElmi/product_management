<?php
require_once 'config.php';

// Get filter parameters
$package = isset($_GET['package']) ? $_GET['package'] : array();
$product = isset($_GET['product']) ? $_GET['product'] : array();
$b2b = isset($_GET['b2b']) ? $_GET['b2b'] : '';
$package = is_array($package) ? $package : array($package);
$product = is_array($product) ? $product : array($product);

// Sanitize inputs
$sanitized_packages = array_map(function($pkg) use ($conn) {
    return $conn->real_escape_string($pkg);
}, $package);

$sanitized_products = array_map(function($prod) use ($conn) {
    return $conn->real_escape_string($prod);
}, $product);

// Build query
$query = "SELECT 
    pk.id as package_id,
    pk.item_number as package_item_number,
    pk.name as package_name,
    pk.discount_percentage as package_discount,
    pk.description as package_description,
    pk.image as package_image,
    pk.b2b as package_b2b,
    p.name as product_name,
    SUM(p.web_price) as total_package_price,
    SUM(pi.quantity) as total_items_in_package,
    GROUP_CONCAT(CONCAT(p.name, ' - ', p.flavor, ' - ', p.size, ' - $', p.web_price) SEPARATOR '<br><br>') as product_details
FROM packages pk
LEFT JOIN package_items pi ON pk.id = pi.package_id
LEFT JOIN products p ON pi.product_id = p.id
WHERE 1=1";

if (!empty($sanitized_packages) && !in_array('', $sanitized_packages)) {
    $package_list = "'" . implode("','", $sanitized_packages) . "'";
    $query .= " AND pk.name IN ($package_list)";
}

if (!empty($sanitized_products) && !in_array('', $sanitized_products)) {
    $product_list = "'" . implode("','", $sanitized_products) . "'";
    $query .= " AND p.name IN ($product_list)";
}

if ($b2b === '1') {
    $query .= " AND pk.b2b = 1";
} elseif ($b2b === '0') {
    $query .= " AND pk.b2b = 0";
} elseif (!isset($_GET['b2b'])) {
    // Default to B2C packages when b2b parameter is not in query string
    $query .= " AND pk.b2b = 0";
    $b2b = '0'; // Set the variable for the toggle state
}

$query .= " GROUP BY pk.id ORDER BY pk.name";

$result = $conn->query($query);

// Get subscription prices for packages
$package_subscription_prices = [];
$sql = "SELECT package_id, subscription_type_id, discount_percentage FROM package_subscription_prices";
$package_subscription_prices_result = $conn->query($sql);
if ($package_subscription_prices_result) {
    while ($row = $package_subscription_prices_result->fetch_assoc()) {
        $package_subscription_prices[$row['package_id']][$row['subscription_type_id']] = $row['discount_percentage'];
    }
}

// Get unique values for filters
$packages = $conn->query("SELECT DISTINCT name FROM packages ORDER BY name");
$subscription_types = $conn->query("SELECT * FROM subscription_types ORDER BY days");
$products = $conn->query("SELECT DISTINCT name FROM products ORDER BY name");

ob_start();
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Price Sheet</h1>
    <div class="flex items-center space-x-4">
        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
        <button id="theme-toggle" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700">
            <svg class="w-5 h-5 text-gray-800 dark:text-white hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
            </svg>
            <svg class="w-5 h-5 text-gray-800 dark:text-white block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
            </svg>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
    <form method="GET" id="filter-form" class="space-y-6">
        <!-- Packages Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Packages</label>
            <div class="flex flex-wrap gap-3">
            <label class="relative inline-flex">
                <input type="checkbox" 
                name="package[]" 
                value="" 
                <?php echo empty($package) ? 'checked' : ''; ?>
                class="hidden peer filter-checkbox"
                onclick="uncheckOthers(this, 'package')">
                <div class="flex items-center w-auto px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer 
                    peer-checked:border-primary peer-checked:bg-primary/5 dark:peer-checked:bg-primary/20
                    hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                <span class="filter-label text-sm font-medium text-gray-900 dark:text-white mr-2">All Packages</span>
                <svg class="w-5 h-5 text-primary hidden peer-checked:block flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                </svg>
                </div>
            </label>
            <?php 
            $packages->data_seek(0);
            while ($row = $packages->fetch_assoc()): ?>
            <label class="relative inline-flex">
                <input type="checkbox" 
                name="package[]" 
                value="<?php echo htmlspecialchars($row['name']); ?>" 
                <?php echo in_array($row['name'], $package) ? 'checked' : ''; ?>
                class="hidden peer filter-checkbox"
                onclick="uncheckOthers(this, 'package')">
                <div class="flex items-center w-auto px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer 
                    peer-checked:border-primary peer-checked:bg-primary/5 dark:peer-checked:bg-primary/20
                    hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                <span class="filter-label text-sm font-medium text-gray-900 dark:text-white mr-2"><?php echo htmlspecialchars($row['name']); ?></span>
                <svg class="w-5 h-5 text-primary hidden peer-checked:block flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                </svg>
                </div>
            </label>
            <?php endwhile; ?>
            </div>
        </div>

        <!-- Products Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Products</label>
            <div class="flex flex-wrap gap-3">
                <label class="relative inline-flex">
                    <input type="checkbox" 
                        name="product[]" 
                        value="" 
                        <?php echo empty($product) ? 'checked' : ''; ?>
                        class="hidden peer filter-checkbox"
                        onclick="uncheckOthers(this, 'product')">
                    <div class="flex items-center w-auto px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer 
                                peer-checked:border-primary peer-checked:bg-primary/5 dark:peer-checked:bg-primary/20
                                hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                        <span class="filter-label text-sm font-medium text-gray-900 dark:text-white mr-2">All Products</span>
                        <svg class="w-5 h-5 text-primary hidden peer-checked:block flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                        </svg>
                    </div>
                </label>
                <?php 
                while ($row = $products->fetch_assoc()): ?>
                <label class="relative inline-flex">
                    <input type="checkbox" 
                        name="product[]" 
                        value="<?php echo htmlspecialchars($row['name']); ?>" 
                        <?php echo in_array($row['name'], $product) ? 'checked' : ''; ?>
                        class="hidden peer filter-checkbox"
                        onclick="uncheckOthers(this, 'product')">
                    <div class="flex items-center w-auto px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer 
                                peer-checked:border-primary peer-checked:bg-primary/5 dark:peer-checked:bg-primary/20
                                hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                        <span class="filter-label text-sm font-medium text-gray-900 dark:text-white mr-2"><?php echo htmlspecialchars($row['name']); ?></span>
                        <svg class="w-5 h-5 text-primary hidden peer-checked:block flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                        </svg>
                    </div>
                </label>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- B2C/B2B Toggle in bottom right corner -->
        <div class="flex justify-end">
            <div class="flex items-center space-x-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">B2C</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="b2b" value="<?php echo $b2b === '1' ? '1' : '0'; ?>">
                    <input type="checkbox" name="b2b" value="1" <?php echo $b2b === '1' ? 'checked' : ''; ?> class="sr-only peer filter-checkbox" id="b2b-toggle">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">B2B</span>
            </div>
        </div>
    </form>
</div>

<script>
function uncheckOthers(clickedCheckbox, filterType) {
    document.querySelectorAll(`input[name="${filterType}[]"]`).forEach(checkbox => {
        if (checkbox !== clickedCheckbox) {
            checkbox.checked = false;
        }
    });
    // Trigger live filtering
    submitForm();
}

// Live filtering function
function submitForm() {
    document.getElementById('filter-form').submit();
}

// Add event listeners for live filtering
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to B2B toggle
    document.getElementById('b2b-toggle').addEventListener('change', function() {
        // Update hidden field value
        const hiddenInput = document.querySelector('input[name="b2b"][type="hidden"]');
        if (this.checked) {
            hiddenInput.value = '1';
        } else {
            hiddenInput.value = '0';
        }
        submitForm();
    });
    
    // Set initial hidden field value based on checkbox state or default to B2C
    const b2bToggle = document.getElementById('b2b-toggle');
    const hiddenInput = document.querySelector('input[name="b2b"][type="hidden"]');
    const urlParams = new URLSearchParams(window.location.search);
    
    // If no b2b parameter in URL, default to B2C (0)
    if (!urlParams.has('b2b')) {
        hiddenInput.value = '0';
        b2bToggle.checked = false;
    } else if (b2bToggle.checked) {
        hiddenInput.value = '1';
    } else {
        hiddenInput.value = '0';
    }
});
</script>

<!-- Price Table -->
<style>
    .package-row {
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }
    .product-row {
        padding-top: 0.125rem;
        padding-bottom: 0.125rem;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .dark .product-row {
        color: #9ca3af;
    }
    thead {
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(max-content, 1fr));
        gap: 0.75rem;
    }
    .filter-label {
        white-space: nowrap;
        display: inline-block;
    }
</style>
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden ">
    <div class="overflow-x-auto">
        <table class="min-w-full  ">
            <thead class="bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-300 ">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Image&nbsp;&nbsp;&nbsp;</th>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Package</th>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Products</th>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Discount</th>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Quantity</th>
                    <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider">Package Price</th>
                    <?php while ($sub_type = $subscription_types->fetch_assoc()): ?>
                        <th scope="col" class="px-6 py-3 text-left uppercase tracking-wider"><?php echo htmlspecialchars($sub_type['name']); ?></th>
                    <?php endwhile; ?>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 dark:divide-gray-700 divide-gray-100">
                <tr>
                <?php
                $result->data_seek(0);
                while ($row = $result->fetch_assoc()):
                    $package_id = htmlspecialchars($row['package_id']);
                    $package_price = htmlspecialchars($row['total_package_price']);
                    $package_discount = htmlspecialchars($row['package_discount']);
                    $package_price_after_discount = $row['total_package_price'] * (1 - ($row['package_discount'] / 100));
                    ?>
                    <tr class="package-row">
                         <td class="px-6 py-3 whitespace-nowrap text-xs dark:text-white">
                            <?php 
                            $image_src = !empty($row['package_image']) ? 'imgs/' . $row['package_image'] : 'imgs/default.jpg';
                            ?>
                            <img src="<?php echo htmlspecialchars($image_src); ?>" alt="Package Image" class="w-12 h-12 object-cover rounded border aspect-square">
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-xs dark:text-white">
                            <b><?php 
                            $product_array = explode('<br>', $row['product_details']);
                            $first_product = explode(' - ', $product_array[0]);
                            $product_name = $first_product[0];
                            echo htmlspecialchars($product_name . ' - ' . $row['package_name']); 
                            // Total items info
                            // echo '<br><small>Items: ' . $row['total_items_in_package'] . ' | Item#: ' . $row['package_item_number'] . '</small>';
                            ?></b>
                        </td>
                       
                        <td class="px-6 py-3 whitespace-nowrap text-xs dark:text-white">
                            <ul>
                                <?php
                                    $product_details = explode('<br>', $row['product_details']);
                                    foreach ($product_details as $product) {
                                        $parts = explode(' - ', $product);
                                        $name = isset($parts[0]) ? $parts[0] : '';
                                        $flavor = isset($parts[1]) ? $parts[1] : '';
                                        $size = isset($parts[2]) ? $parts[2] : '';
                                        $price = isset($parts[3]) ? $parts[3] : '';

                                        $color_class = '';
                                        if (strpos($name, 'Lubricity Xtra') !== false) {
                                            $color_class = 'bg-blue-100 text-blue-800 border rounded-sm border-blue-400 dark:bg-gray-700 dark:text-blue-400';
                                        } elseif (strpos($name, 'MetaQil') !== false) {
                                            $color_class = 'bg-green-100 text-green-800 border rounded-sm border-green-400 dark:bg-gray-700 dark:text-green-400';
                                        } elseif (strpos($name, 'Lubricity') !== false) {
                                            $color_class = 'bg-purple-100 text-purple-800 border rounded-sm border-purple-400 dark:bg-gray-700 dark:text-purple-400';
                                        }
                                        ?>
                                        <li>
                                            <span class="<?php echo $color_class; ?> text-xs  me-2 px-2.5 py-0.5 rounded-sm"><?php echo htmlspecialchars($name . ' - ' . $flavor); ?></span>
                                            <?php echo htmlspecialchars($size . ' - ' . $price); ?>
                                        </li>
                                        <?php 
                                    }
                                ?>
                            </ul>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-xs  dark:text-white">
                            <?php echo number_format($row['package_discount'], 1); ?>%
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1 w-fit">
                                <button type="button" 
                                        class="decrease-btn inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200  transition-colors dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 border-0 appearance-none"
                                        data-package-id="<?php echo $package_id; ?>"
                                        data-package-price="<?php echo $package_price; ?>"
                                        style="-webkit-appearance: none;">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" />
                                    </svg>
                                </button>
                                
                                <?php
                                // Special handling for PKG- items: set quantity in multiples of case size
                                // Check if this is a PKG- item and set items per case
                                $is_pkg_item = strpos($row['package_item_number'], 'PKG-') === 0;
                                if ($is_pkg_item) {
                                    // For PKG- items, use the actual quantity from package_items table
                                    $items_per_case = intval($row['total_items_in_package']);
                                    $default_qty = $items_per_case;
                                } else {
                                    // For non-PKG items, use default behavior
                                    $items_per_case = 1;
                                    $default_qty = 1;
                                }
                                // Ensure minimum values
                                $items_per_case = max(1, $items_per_case);
                                $default_qty = max(1, $default_qty);
                                ?>
                                <input type="number" 
                                    id="quantity_<?php echo $package_id; ?>" 
                                    value="<?php echo $default_qty; ?>" 
                                    min="<?php echo $is_pkg_item ? $items_per_case : 1; ?>"
                                    step="<?php echo $items_per_case; ?>"
                                    class="w-14 h-8 mx-1 text-center bg-white dark:bg-gray-800 border-0 rounded-md  text-sm font-medium text-gray-700 dark:text-gray-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none"
                                    style="-webkit-appearance: none; -moz-appearance: textfield;"
                                    data-items-per-case="<?php echo $items_per_case; ?>"
                                    data-is-pkg-item="<?php echo $is_pkg_item ? 'true' : 'false'; ?>"
                                    onchange="updatePrice(<?php echo $package_id; ?>, <?php echo $package_price; ?>)">
                                
                                <button type="button"
                                        class="increase-btn inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200  transition-colors dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 border-0 appearance-none"
                                        data-package-id="<?php echo $package_id; ?>"
                                        data-package-price="<?php echo $package_price; ?>"
                                        style="-webkit-appearance: none;">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white package-price" id="package_price_<?php echo $package_id; ?>">
                            <span class="text-red-500 line-through">$<?php echo number_format($package_price, 2); ?></span>
                            <span class="mx-1 font-semibold">$<?php echo number_format(($package_price * (1 - ($package_discount / 100))), 2); ?></span>
                            <span class="text-gray-500 text-xs">Save $<?php echo number_format(($package_price - ($package_price * (1 - ($package_discount / 100)))), 2); ?></span>
                        </td>
                        <?php
                        $subscription_types->data_seek(0);
                        while ($sub_type = $subscription_types->fetch_assoc()):
                            $subscription_discount = isset($package_subscription_prices[$row['package_id']][$sub_type['id']]) ? $package_subscription_prices[$row['package_id']][$sub_type['id']] : 0;
                            $subscription_price = $package_price_after_discount * (1 - ($subscription_discount / 100));
                            $is_dimmed = ($subscription_discount == 0);
                            $dimmed_class = $is_dimmed ? 'opacity-20 pointer-events-none' : '';
                            ?>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white subscription-price <?php echo $dimmed_class; ?>" 
                                id="subscription_price_<?php echo $package_id; ?>_<?php echo $sub_type['id']; ?>"
                                data-sub-discount="<?php echo $subscription_discount; ?>"
                                data-is-dimmed="<?php echo $is_dimmed ? 'true' : 'false'; ?>">
                                <span class="text-red-500 line-through">$<?php echo number_format($package_price, 2); ?></span>
                                <span class="mx-1 font-semibold">$<?php echo number_format(($package_price * (1 - ($subscription_discount / 100))), 2); ?></span>
                                <span class="text-gray-500 text-xs">Save $<?php echo number_format(($package_price - ($package_price * (1 - ($subscription_discount / 100)))), 2); ?></span>
                            </td>
                        <?php endwhile; ?>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- TODO: 30-Day Subscription	60-Day Subscription are not working with QTY -->
<script>
$(document).ready(function() {
    $('.increase-btn, .decrease-btn').on('click', function() {
        const $btn = $(this);
        const $input = $btn.siblings('input[type="number"]');
        const currentQty = parseInt($input.val()) || 1;
        const packageId = $btn.data('package-id');
        const packagePrice = $btn.data('package-price');
        
        // Get items per case from the input data attribute
        const itemsPerCase = parseInt($input.data('items-per-case')) || 1;
        const isPkgItem = $input.data('is-pkg-item') === 'true';
        const minQty = isPkgItem ? itemsPerCase : 1;
        
        if ($btn.hasClass('increase-btn')) {
            $input.val(currentQty + itemsPerCase);
        } else if (currentQty > minQty) {
            const newQty = currentQty - itemsPerCase;
            $input.val(newQty >= minQty ? newQty : minQty);
        }
        
        updatePrices(packageId, packagePrice);
    });

    $('input[type="number"]').on('change', function() {
        const $input = $(this);
        const packageId = $input.attr('id').split('_')[1];
        const packagePrice = $input.siblings('.increase-btn').data('package-price');
        let qty = parseInt($input.val()) || 1;
        
        // Get items per case info
        const itemsPerCase = parseInt($input.data('items-per-case')) || 1;
        const isPkgItem = $input.data('is-pkg-item') === 'true';
        const minQty = isPkgItem ? itemsPerCase : 1;
        
        // For PKG items, ensure quantity is a multiple of items per case
        if (isPkgItem && qty % itemsPerCase !== 0) {
            // Round to nearest multiple of itemsPerCase
            qty = Math.round(qty / itemsPerCase) * itemsPerCase;
        }
        
        // Ensure minimum quantity
        if (qty < minQty) qty = minQty;
        
        $input.val(qty);
        updatePrices(packageId, packagePrice);
    });

    function updatePrices(packageId, packagePrice) {
        const qty = parseInt($('#quantity_' + packageId).val()) || 1;
        const packageDiscount = parseFloat($(`#package_price_${packageId}`).closest('tr').find('td:nth-child(4)').text()) || 0;

        // Calculate prices
        const totalBasePrice = packagePrice * qty;
        const totalDiscountedPrice = packagePrice * (1 - (packageDiscount / 100)) * qty;
        const totalSavings = totalBasePrice - totalDiscountedPrice;

        // Update package price with all components
        const priceHtml = `
            <span class="text-red-500 line-through">$${totalBasePrice.toFixed(2)}</span>
            <span class="mx-1 font-semibold">$${totalDiscountedPrice.toFixed(2)}</span>
            <span class="text-gray-500 text-xs">Save $${totalSavings.toFixed(2)}</span>
        `;
        $('#package_price_' + packageId).html(priceHtml);

        // Update subscription prices
        <?php
        $subscription_types->data_seek(0);
        while ($sub_type = $subscription_types->fetch_assoc()):
        ?>
            try {
                let cellId = 'subscription_price_' + packageId + '_<?php echo $sub_type['id']; ?>';
                let $cell = $('#' + cellId);
                let discount = parseFloat($cell.attr('data-sub-discount')) || 0;
                
                let originalPrice = packagePrice * qty;
                let discountedPrice = packagePrice * (1 - (discount / 100)) * qty;
                let savings = originalPrice - discountedPrice;

                let priceHtml = `
                    <span class="text-red-500 line-through">$${originalPrice.toFixed(2)}</span>
                    <span class="mx-1 font-semibold">$${discountedPrice.toFixed(2)}</span>
                    <span class="text-gray-500 text-xs">Save $${savings.toFixed(2)}</span>
                `;
                $cell.html(priceHtml);
            } catch (error) {
                // Error processing subscription
            }
        <?php endwhile; ?>
    }

    // Initialize prices
    <?php
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()):
        $package_id = htmlspecialchars($row['package_id']);
        $package_price = htmlspecialchars($row['total_package_price']);
    ?>
    updatePrices(<?php echo $package_id; ?>, <?php echo $package_price; ?>);
    <?php endwhile; ?>
});
</script>

<?php
$content = ob_get_clean();
$hide_sidebar = true;
?>
<?php
require_once 'layout.php';
?>