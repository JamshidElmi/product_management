<?php
// Public Purchase Order Request Form (no login required)
require_once 'config.php';

// Start output buffering to allow headers to be sent even after HTML output
ob_start();

// Helper function to mimic Excel's VLOOKUP function
function vlookup($lookup_value, $quantities, $rates, $approximate_match = true) {
    if ($approximate_match) {
        // Find the largest quantity that is <= lookup_value
        $best_match = null;
        foreach ($quantities as $qty) {
            if ($qty <= $lookup_value) {
                $best_match = $qty;
            } else {
                break;
            }
        }
        return $best_match ? $rates[$best_match] : 0;
    } else {
        // Exact match
        return isset($rates[$lookup_value]) ? $rates[$lookup_value] : 0;
    }
}

// Check if we're editing an existing order (admin only)
$editing = false;
$edit_order = null;
$edit_customer = null;
$edit_items = [];
$is_admin = isLoggedIn();

// Strict security check for editing - block any edit attempts if not logged in
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    if (!$is_admin) {
        // Silently redirect non-admin users to regular form
        ob_end_clean();
        header("Location: purchase_order_request.php");
        exit();
    }
    
    // Only proceed with edit if user is logged in
    $order_id = (int)$_GET['edit'];
    
    // Fetch order details
    $orderQuery = $conn->prepare("SELECT o.id as order_id, o.customer_id, o.order_reference, o.status, o.total_amount, o.subtotal, o.shipping_cost, o.created_at,
                                         c.id as customer_id_ref, c.customer_name, c.email, c.phone, c.addressee, c.business_name, c.address_line, c.city, c.state, c.zip, c.country 
                                  FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
    $orderQuery->bind_param('i', $order_id);
    $orderQuery->execute();
    $orderResult = $orderQuery->get_result();
    
    if ($orderResult->num_rows > 0) {
        $edit_order = $orderResult->fetch_assoc();
        $editing = true;
        
        // Fetch order items
        $itemsQuery = $conn->prepare("SELECT oi.*, p.name as product_name, pk.name as package_name 
                                     FROM order_items oi 
                                     LEFT JOIN products p ON oi.product_id = p.id 
                                     LEFT JOIN packages pk ON oi.package_id = pk.id 
                                     WHERE oi.order_id = ?");
        $itemsQuery->bind_param('i', $order_id);
        $itemsQuery->execute();
        $itemsResult = $itemsQuery->get_result();
        
        while ($item = $itemsResult->fetch_assoc()) {
            $edit_items[] = $item;
        }
        $itemsQuery->close();
    } else {
        // Order not found, redirect to orders page
        ob_end_clean();
        header("Location: orders.php");
        exit();
    }
    $orderQuery->close();
}

// Additional security: Check for any other admin-only parameters
$admin_params = ['updating_order_id', 'admin_action'];
foreach ($admin_params as $param) {
    if (isset($_GET[$param]) || isset($_POST[$param])) {
        if (!$is_admin) {
            ob_end_clean();
            header("Location: purchase_order_request.php");
            exit();
        }
    }
}

// Do NOT enforce requireLogin() for new orders;
?>
<style>
/* Enhanced field styling */
.po-form input, .po-form select { 
    height: 30px; 
    padding-left: 12px; 
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}
.po-form input, .po-form select, .po-form .select2-selection { font-size:0.95rem; }
.po-form .select2-selection { 
    height: 30px !important; 
    line-height: 44px; 
    padding-left: 12px; 
    background: white !important;
    border: 1px solid #d1d5db !important;
}
.dark .po-form input, .dark .po-form select { 
    background: #374151 !important; 
    color: #f3f4f6 !important; 
    border-color: #4b5563 !important; 
}
.dark .po-form .select2-selection { 
    background: #374151 !important; 
    border-color: #4b5563 !important; 
    color: #f3f4f6 !important; 
}
.po-form input::placeholder, .po-form select::placeholder { color:#9ca3af; }
.dark .po-form input::placeholder { color:#9ca3af; }
.select2-dropdown { z-index: 99999; }
.dark .select2-dropdown, .dark .select2-results__options { background:#374151; color:#f3f4f6; }
.dark .select2-results__option--highlighted { background:#2563eb; }
/* Remove table select2 left margin */
#items-table .select2-container { margin-left: 0 !important; }
#items-table .select2-selection { margin-left: 0 !important; }

/* Fix Select2 dark mode issues */
/* Selected text visibility */
.dark .select2-selection__rendered {
    color: #f3f4f6 !important;
}

/* Search box background and text */
.dark .select2-search__field {
    background: #374151 !important;
    color: #f3f4f6 !important;
    border-color: #4b5563 !important;
}

.dark .select2-search {
    background: #374151 !important;
}

/* Close icon centering and styling */
.dark .select2-selection__clear {
    color: #f3f4f6 !important;
    line-height: 28px !important;
    font-size: 18px !important;
}

.select2-selection__clear {
    line-height: 28px !important;
    font-size: 18px !important;
}

/* Fix dropdown option styling */
.dark .select2-results__option {
    background: #374151 !important;
    color: #f3f4f6 !important;
}

.dark .select2-results__option--highlighted {
    background: #2563eb !important;
    color: white !important;
}

/* Custom template styling for images */
.select2-option-with-image {
    display: flex !important;
    align-items: center !important;
    padding: 0 !important;
}

.select2-option-image {
    width: 40px !important;
    height: 40px !important;
    margin-right: 8px !important;
    border-radius: 4px !important;
    object-fit: cover !important;
    flex-shrink: 0 !important;
    border: 1px solid rgba(0, 0, 0, 0.2) !important;
}

.dark .select2-option-image {
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.select2-option-text {
    flex: 1 !important;
}

/* Fix line total color in dark mode */
.dark .line-total {
    color: #f3f4f6 !important;
}

/* Quantity input styling to match the price_sheet.php design */
.qty-container {
    display: flex;
    align-items: center;
    background: #f3f4f6;
    border-radius: 8px;
    padding: 4px;
    width: fit-content;
}

.dark .qty-container {
    background: #374151;
}

.qty-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s;
}

.qty-btn:hover {
    background: #e5e7eb;
    color: #374151;
}

.dark .qty-btn {
    color: #9ca3af;
}

.dark .qty-btn:hover {
    background: #4b5563;
    color: #f3f4f6;
}

.qty-input-field {
    width: 56px;
    height: 32px;
    border: none;
    background: #ffffff;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    outline: none;
    margin: 0 4px;
    border-radius: 6px;
    border: 0;
    focus:ring-2;
    focus:ring-primary;
}

.dark .qty-input-field {
    background: #374151;
    color: #f3f4f6;
}

.qty-input-field::-webkit-outer-spin-button,
.qty-input-field::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.qty-input-field[type=number] {
    -moz-appearance: textfield;
}
</style>

<!-- Force light theme for this page: remove 'dark' class, persist preference, hide theme toggle, and override dark CSS -->
<script>
    (function(){
        try {
            // If script executes before DOM, still attempt to modify documentElement
            if (document && document.documentElement) {
                document.documentElement.classList.remove('dark');
            }
        } catch(e) {
            // ignore
        }
            // Do NOT modify localStorage here — we only remove any dark class for this page
            // Hide theme toggle if present after DOM loads
            window.addEventListener('DOMContentLoaded', function(){
                var t = document.getElementById('theme-toggle');
                if (t) t.style.display = 'none';
            });
    })();
</script>

<style>
/* Page-level overrides to ensure light appearance even if global dark styles exist */
html.dark body, html.dark .po-form, .dark .po-form, html.dark .dark, .dark {
        background: transparent !important;
        color: inherit !important;
}
.po-form { background: #ffffff !important; color: #111827 !important; }
.po-form input, .po-form select, .po-form textarea { background: #ffffff !important; color: #111827 !important; border-color: #d1d5db !important; }
.po-form .select2-selection, .po-form .select2-results__option { background: #ffffff !important; color: #111827 !important; }
.po-form .dark { background: transparent !important; color: inherit !important; }
</style>

<?php

// Handle form submission
$errors = [];
$success = false;
$order_reference = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization
    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $addressee = trim($_POST['addressee'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $address_line = trim($_POST['address_line'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    $updating_order_id = isset($_POST['updating_order_id']) ? (int)$_POST['updating_order_id'] : null;

    // Extra security: Block any attempt to update orders if not admin
    if ($updating_order_id && !$is_admin) {
        ob_end_clean();
        header("Location: purchase_order_request.php");
        exit();
    }

    // Validate customer information fields
    if ($customer_name === '') {
        $errors[] = 'Customer name is required';
    } elseif (strlen($customer_name) < 2) {
        $errors[] = 'Customer name must be at least 2 characters long';
    } elseif (strlen($customer_name) > 100) {
        $errors[] = 'Customer name must be less than 100 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-\']+$/', $customer_name)) {
        $errors[] = 'Customer name contains invalid characters';
    }

    // Email validation (required)
    if ($email === '') {
        $errors[] = 'Email address is required';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        } elseif (strlen($email) > 100) {
            $errors[] = 'Email address must be less than 100 characters';
        }
    }

    // Phone validation (required)
    if ($phone === '') {
        $errors[] = 'Phone number is required';
    } else {
        // Remove common formatting characters
        $phone_clean = preg_replace('/[\s\-\(\)\.\+]/', '', $phone);
        if (!preg_match('/^[0-9]{10,15}$/', $phone_clean)) {
            $errors[] = 'Phone number must contain 10-15 digits only';
        } elseif (strlen($phone) > 20) {
            $errors[] = 'Phone number must be less than 20 characters';
        }
        // Use cleaned phone for storage
        $phone = $phone_clean;
    }

    // Addressee validation (required for shipping)
    if ($addressee === '') {
        $errors[] = 'Addressee (recipient name) is required for shipping';
    } elseif (strlen($addressee) < 2) {
        $errors[] = 'Addressee must be at least 2 characters long';
    } elseif (strlen($addressee) > 100) {
        $errors[] = 'Addressee must be less than 100 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-\']+$/', $addressee)) {
        $errors[] = 'Addressee contains invalid characters';
    }

    // Business name validation (optional)
    if (!empty($business_name)) {
        if (strlen($business_name) > 100) {
            $errors[] = 'Business name must be less than 100 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\.\-\'&,]+$/', $business_name)) {
            $errors[] = 'Business name contains invalid characters';
        }
    }

    // Address validation (required)
    if ($address_line === '') {
        $errors[] = 'Address is required';
    } elseif (strlen($address_line) < 5) {
        $errors[] = 'Address must be at least 5 characters long';
    } elseif (strlen($address_line) > 200) {
        $errors[] = 'Address must be less than 200 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9\s\.\-\#\/,]+$/', $address_line)) {
        $errors[] = 'Address contains invalid characters';
    }

    // City validation (required)
    if ($city === '') {
        $errors[] = 'City is required';
    } elseif (strlen($city) < 2) {
        $errors[] = 'City must be at least 2 characters long';
    } elseif (strlen($city) > 50) {
        $errors[] = 'City must be less than 50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-\']+$/', $city)) {
        $errors[] = 'City contains invalid characters';
    }

    // State validation (required)
    if ($state === '') {
        $errors[] = 'State/Province is required';
    } elseif (strlen($state) > 50) {
        $errors[] = 'State/Province must be less than 50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-]+$/', $state)) {
        $errors[] = 'State/Province contains invalid characters';
    }

    // ZIP code validation (required)
    if ($zip === '') {
        $errors[] = 'ZIP/Postal code is required';
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-]{3,10}$/', $zip)) {
        $errors[] = 'ZIP/Postal code must be 3-10 characters with letters, numbers, spaces, and hyphens only';
    }

    // Country validation (required)
    if ($country === '') {
        $errors[] = 'Country is required';
    } elseif (strlen($country) < 2) {
        $errors[] = 'Country must be at least 2 characters long';
    } elseif (strlen($country) > 50) {
        $errors[] = 'Country must be less than 50 characters';
    } elseif (!preg_match('/^[a-zA-Z\s\.\-]+$/', $country)) {
        $errors[] = 'Country contains invalid characters';
    }

    // Line items
    $product_ids = $_POST['product_id'] ?? [];
    $package_ids = $_POST['package_id'] ?? []; // hidden field aligned with product selection
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $line_count = count($product_ids);

    $items = [];
    $subtotal_amount = 0.00;

    for ($i = 0; $i < $line_count; $i++) {
        $pid = (int)$product_ids[$i];
        $pkg = $package_ids[$i] !== '' ? (int)$package_ids[$i] : null;
        $qty = max(1, (int)$quantities[$i]);
        $price = number_format((float)$unit_prices[$i], 2, '.', '');
        if ($pid <= 0) { continue; }
        $line_total = $qty * (float)$price;
        $subtotal_amount += $line_total;
        $items[] = [ 'product_id' => $pid, 'package_id' => $pkg, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $line_total ];
    }

    if (empty($items)) {
        $errors[] = 'At least one product must be selected';
    }

    // Calculate shipping cost on server side
    $shipping_cost = 0.00;
    if (!empty($items)) {
        // Group items by size to calculate shipping
        $sizeGroups = [];
        
        foreach ($items as $item) {
            // Get product size from database
            $sizeQuery = $conn->prepare("SELECT p.size FROM products p WHERE p.id = ?");
            $sizeQuery->bind_param('i', $item['product_id']);
            $sizeQuery->execute();
            $sizeResult = $sizeQuery->get_result();
            $sizeRow = $sizeResult->fetch_assoc();
            $sizeQuery->close();
            
            if ($sizeRow) {
                $size = $sizeRow['size'];
                // Normalize size to match shipping table
                if (strpos($size, '8') !== false) $size = '8oz';
                else if (strpos($size, '2') !== false) $size = '2oz';
                else if (strpos($size, '.5') !== false || strpos($size, '0.5') !== false) $size = '.5oz';
                else $size = '2oz'; // default
                
                if (!isset($sizeGroups[$size])) $sizeGroups[$size] = 0;
                $sizeGroups[$size] += $item['quantity'];
            }
        }
        
        // Shipping rate table matching Excel data
        $shippingRates = [
            '8oz' => [
                1 => 5.51, 2 => 6.91, 3 => 7.35, 4 => 7.35, 5 => 7.35, 6 => 7.35,
                12 => 10.00, 24 => 18.74, 36 => 23.15, 72 => 37.22
            ],
            '2oz' => [
                1 => 4.41, 2 => 4.74, 3 => 5.51, 4 => 6.49, 5 => 6.91, 6 => 6.91,
                12 => 7.35, 24 => 12.59, 36 => 12.59, 72 => 18.74
            ],
            '.5oz' => [
                1 => 4.41, 2 => 4.41, 3 => 4.74, 4 => 4.74, 5 => 4.74, 6 => 4.74,
                12 => 6.91, 24 => 8.48, 36 => 9.17, 72 => 15.35
            ]
        ];
        
        // Calculate shipping using Excel-style logic
        foreach ($sizeGroups as $size => $totalQty) {
            if (isset($shippingRates[$size])) {
                $rates = $shippingRates[$size];
                $quantities = array_keys($rates);
                sort($quantities);
                
                if ($size === '.5oz') {
                    // Special logic for 0.5oz products matching Excel J2 formula
                    if ($totalQty <= 72) {
                        // Simple VLOOKUP for quantities <= 72
                        $shipping_cost += vlookup($totalQty, $quantities, $rates, true);
                    } else {
                        // Cost for 72 units + CEILING((qty-72)/72,1)*6.91
                        $shipping_cost += $rates[72];
                        $additional_batches = ceil(($totalQty - 72) / 72);
                        $shipping_cost += $additional_batches * 6.91;
                    }
                } else {
                    // Logic for 8oz and 2oz products matching Excel H2 and I2 formulas
                    // First part: VLOOKUP(MIN(qty,72), table, column, TRUE)
                    $first_part_qty = min($totalQty, 72);
                    $shipping_cost += vlookup($first_part_qty, $quantities, $rates, true);
                    
                    // Second part: IF(qty>72, VLOOKUP(qty-72, table, column, TRUE), 0)
                    if ($totalQty > 72) {
                        $remaining_qty = $totalQty - 72;
                        $shipping_cost += vlookup($remaining_qty, $quantities, $rates, true);
                    }
                }
            }
        }
    }
    
    $total_amount = $subtotal_amount + $shipping_cost;

    if (!$errors) {
        $conn->begin_transaction();
        try {
            if ($updating_order_id) {
                // First, verify the order exists and get its details
                $verifyOrderStmt = $conn->prepare("SELECT id, customer_id, order_reference FROM orders WHERE id = ?");
                $verifyOrderStmt->bind_param('i', $updating_order_id);
                $verifyOrderStmt->execute();
                $verifyResult = $verifyOrderStmt->get_result();
                
                if ($verifyResult->num_rows === 0) {
                    $verifyOrderStmt->close();
                    $conn->rollback();
                    
                    // Log this event
                    // Attempted to edit non-existent order
                    
                    // Redirect back to orders page with error message
                    $_SESSION['error'] = "Order ID {$updating_order_id} does not exist or has been deleted. Please check the orders list.";
                    ob_end_clean();
                    header("Location: orders.php");
                    exit();
                }
                
                $existingOrder = $verifyResult->fetch_assoc();
                $verifyOrderStmt->close();
                
                // Admin logging for edit operation
                logAdminAction('order_update', "Edited order ID: $updating_order_id");
                
                // Update customer information
                $stmt = $conn->prepare("UPDATE customers SET customer_name=?,email=?,phone=?,addressee=?,business_name=?,address_line=?,city=?,state=?,zip=?,country=? WHERE id=?");
                $stmt->bind_param('ssssssssssi', $customer_name,$email,$phone,$addressee,$business_name,$address_line,$city,$state,$zip,$country,$existingOrder['customer_id']);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception("Failed to update customer information: " . $conn->error);
                }
                $stmt->close();
                
                // Check if orders table has subtotal and shipping_cost columns, if not add them
                $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'subtotal'");
                if ($checkColumns->num_rows == 0) {
                    $conn->query("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
                    $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal");
                }
                
                // Update order totals
                $stmt = $conn->prepare("UPDATE orders SET subtotal=?, shipping_cost=?, total_amount=? WHERE id=?");
                $stmt->bind_param('dddi', $subtotal_amount, $shipping_cost, $total_amount, $updating_order_id);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception("Failed to update order totals: " . $conn->error);
                }
                $stmt->close();
                
                // Delete existing order items
                $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
                $stmt->bind_param('i', $updating_order_id);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new Exception("Failed to delete existing order items: " . $conn->error);
                }
                $stmt->close();
                
                $order_id = $updating_order_id;
                $order_reference = $existingOrder['order_reference'];
            } else {
                // Insert / find customer (simple: always insert new row)
                $stmt = $conn->prepare("INSERT INTO customers(customer_name,email,phone,addressee,business_name,address_line,city,state,zip,country) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssssssss', $customer_name,$email,$phone,$addressee,$business_name,$address_line,$city,$state,$zip,$country);
                $stmt->execute();
                $customer_id = $stmt->insert_id;
                $stmt->close();

                // Generate simple reference
                $order_reference = 'PO-' . strtoupper(bin2hex(random_bytes(3))) . '-' . date('Ymd');
                
                // Check if orders table has subtotal and shipping_cost columns, if not add them
                $checkColumns = $conn->query("SHOW COLUMNS FROM orders LIKE 'subtotal'");
                if ($checkColumns->num_rows == 0) {
                    $conn->query("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
                    $conn->query("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal");
                }
                
                $stmt = $conn->prepare("INSERT INTO orders(customer_id,order_reference,status,subtotal,shipping_cost,total_amount) VALUES (?,?,?,?,?,?)");
                $status = 'pending';
                $stmt->bind_param('issddd', $customer_id, $order_reference, $status, $subtotal_amount, $shipping_cost, $total_amount);
                $stmt->execute();
                $order_id = $stmt->insert_id;
                $stmt->close();
            }

            // Double-check that parent order still exists before inserting items
            $checkOrderStmt = $conn->prepare("SELECT id FROM orders WHERE id = ?");
            $checkOrderStmt->bind_param('i', $order_id);
            $checkOrderStmt->execute();
            $checkOrderRes = $checkOrderStmt->get_result();
            
            if (!$checkOrderRes || $checkOrderRes->num_rows === 0) {
                $checkOrderStmt->close();
                // Provide detailed error message
                $operation_type = $updating_order_id ? "updating order ID $updating_order_id" : "new order";
                throw new Exception("Order ID {$order_id} not found after database operations ($operation_type). This may indicate a database constraint issue or transaction problem.");
            }
            $checkOrderStmt->close();

            $itemStmt = $conn->prepare("INSERT INTO order_items(order_id,product_id,package_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)");
            foreach ($items as $it) {
                $pid = $it['product_id'];
                $pkg = $it['package_id'];
                $qty = $it['quantity'];
                $up = $it['unit_price'];
                $lt = $it['line_total'];
                // bind package_id as nullable
                if ($pkg === null) {
                    $pkg = null; // ensure null
                }
                $itemStmt->bind_param('iiiidd', $order_id, $pid, $pkg, $qty, $up, $lt);
                $itemStmt->execute();
            }
            $itemStmt->close();

            $conn->commit();
            $success = true;
            
            // If updating, redirect back to orders page
            if ($updating_order_id) {
                $_SESSION['success'] = 'Order has been updated successfully.';
                ob_end_clean();
                header('Location: orders.php');
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to save order: ' . $e->getMessage();
        }
    }
}

// Fetch dynamic product & package combinations for dropdown
// We need product id, package id (if any), and label "product name - package name - flavor (if) - size"
// Only include B2B packages
$query = "SELECT p.id AS product_id, pk.id AS package_id, p.name, pk.name AS package_name, p.flavor, p.size, p.web_price, pk.image AS package_image, pi.quantity AS package_quantity
          FROM products p
          INNER JOIN package_items pi ON pi.product_id = p.id
          INNER JOIN packages pk ON pk.id = pi.package_id AND pk.b2b = 1
          ORDER BY p.name, pk.name";
$result = $conn->query($query);
$options = [];
while ($row = $result->fetch_assoc()) {
    $labelParts = [];
    // Do NOT repeat product name later; group will show name
    if ($row['package_name']) $labelParts[] = $row['package_name'];
    
    // Format flavor and size as (flavor - size)
    $flavorSizeParts = [];
    if (!empty($row['flavor']) && strtolower($row['flavor']) !== 'n/a') {
        $flavorSizeParts[] = $row['flavor'];
    }
    if (!empty($row['size'])) {
        $flavorSizeParts[] = $row['size'];
    }
    if (!empty($flavorSizeParts)) {
        $labelParts[] = '(' . implode(' - ', $flavorSizeParts) . ')';
    }
    
    $label = implode(' ', $labelParts); // now excludes base product name and removes trailing dashes
    $options[] = [
        'product_id' => $row['product_id'],
        'package_id' => $row['package_id'],
        'product_name' => $row['name'],
        'label' => $label,
        'price' => $row['web_price'],
        'package_image' => $row['package_image'],
        'package_quantity' => $row['package_quantity'],
        'size' => $row['size'] // Add size to the options
    ];
}

$optionJson = json_encode($options, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

ob_start();
?>
<div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 p-8 rounded-lg shadow po-form">
    <!-- Header with title and admin controls in same row -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
            <?php if ($editing): ?>
                <span class="flex items-center">
                    <ion-icon name="create-outline" class="w-6 h-6 mr-2 text-blue-600 dark:text-blue-400 align-middle" aria-hidden="true"></ion-icon>
                    Edit Purchase Order
                    <span class="ml-3 px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                        <?php echo htmlspecialchars($edit_order['order_reference']); ?>
                    </span>
                </span>
            <?php else: ?>
                Purchase Order Request Form
            <?php endif; ?>
        </h1>
        
        <?php if ($is_admin): ?>
        <!-- Admin Controls -->
        <div class="flex items-center space-x-4">
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                <ion-icon name="home-outline" class="w-5 h-5 mr-2 align-middle" aria-hidden="true"></ion-icon>
                Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($success): ?>
        <div class="mb-6 p-4 rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
            <div class="mb-2">Thank you! Your request has been received. Reference: <strong><?php echo htmlspecialchars($order_reference); ?></strong></div>
            <div class="text-sm">A representative will reach out to you by the end of the day to complete the verification process and confirm your preferred payment method.</div>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="mb-6 p-4 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200">
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" id="po-form" novalidate>
        <?php if ($editing): ?>
            <input type="hidden" name="updating_order_id" value="<?php echo $edit_order['order_id']; ?>">
        <?php endif; ?>
        <!-- Form fields with labels beside inputs -->
        <div class="space-y-4">
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <label class="w-full md:w-32 text-sm font-medium text-gray-700 dark:text-gray-300">Customer *</label>
                <input placeholder="Customer Name" type="text" name="customer_name" class="flex-1 rounded" required 
                       minlength="2" maxlength="100" pattern="[a-zA-Z\s\.\-']+" 
                       title="Customer name must be 2-100 characters, letters, spaces, periods, hyphens and apostrophes only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['customer_name'] : ($customer_name ?? '')); ?>">
            </div>
         <div class="flex flex-col md:flex-row md:items-center gap-2">
          <label class="w-full md:w-32 text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label>
          <input placeholder="Email Address" type="email" name="email" class="flex-1 md:mr-4 rounded" required 
              maxlength="100" title="Enter a valid email address"
              value="<?php echo htmlspecialchars($editing ? $edit_order['email'] : ($email ?? '')); ?>">
          <label class="w-full md:w-16 text-sm font-medium text-gray-700 dark:text-gray-300 md:text-right">Phone *</label>
          <input placeholder="Phone Number" type="tel" name="phone" class="flex-1 rounded" required 
              maxlength="20" pattern="[\d\s\-\(\)\.\+]{10,20}" 
              title="Phone number with 10-15 digits (numbers only after formatting)"
              value="<?php echo htmlspecialchars($editing ? $edit_order['phone'] : ($phone ?? '')); ?>">
         </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <label class="w-full md:w-32 text-sm font-medium text-gray-700 dark:text-gray-300">Addressee *</label>
                <input placeholder="Recipient Name for Shipping" type="text" name="addressee" class="flex-1 rounded" required 
                       minlength="2" maxlength="100" pattern="[a-zA-Z\s\.\-']+" 
                       title="Recipient name must be 2-100 characters, letters, spaces, periods, hyphens and apostrophes only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['addressee'] : ($addressee ?? '')); ?>">
                <label class="w-full md:w-16 text-sm font-medium text-gray-700 dark:text-gray-300 md:text-right">Business</label>
                <input placeholder="Business Name (Optional)" type="text" name="business_name" class="flex-1 rounded" 
                       maxlength="100" pattern="[a-zA-Z0-9\s\.\-'&,]*" 
                       title="Business name, letters, numbers, spaces and common punctuation only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['business_name'] : ($business_name ?? '')); ?>">
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <label class="w-full md:w-32 text-sm font-medium text-gray-700 dark:text-gray-300">Address *</label>
                <input placeholder="Street Address" type="text" name="address_line" class="flex-1 md:mr-4 rounded" required 
                       minlength="5" maxlength="200" pattern="[a-zA-Z0-9\s\.\-\#\/,]+" 
                       title="Street address must be 5-200 characters, letters, numbers, spaces and common punctuation only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['address_line'] : ($address_line ?? '')); ?>">
                <label class="w-full md:w-12 text-sm font-medium text-gray-700 dark:text-gray-300 md:text-right">City *</label>
                <input placeholder="City" type="text" name="city" class="flex-1 rounded" required 
                       minlength="2" maxlength="50" pattern="[a-zA-Z\s\.\-']+" 
                       title="City must be 2-50 characters, letters, spaces, periods, hyphens and apostrophes only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['city'] : ($city ?? '')); ?>">
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2">
                <label class="w-full md:w-32 text-sm font-medium text-gray-700 dark:text-gray-300">State *</label>
                <input placeholder="State/Province" type="text" name="state" class="flex-1 md:mr-4 rounded" required 
                       maxlength="50" pattern="[a-zA-Z\s\.\-]+" 
                       title="State/Province, letters, spaces, periods and hyphens only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['state'] : ($state ?? '')); ?>">
                <label class="w-full md:w-12 text-sm font-medium text-gray-700 dark:text-gray-300 md:text-right">ZIP *</label>
                <input placeholder="ZIP Code" type="text" name="zip" class="w-full md:w-24 md:mr-4 rounded" required 
                       minlength="3" maxlength="10" pattern="[a-zA-Z0-9\s\-]{3,10}" 
                       title="ZIP/Postal code must be 3-10 characters, letters, numbers, spaces and hyphens only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['zip'] : ($zip ?? '')); ?>">
                <label class="w-full md:w-16 text-sm font-medium text-gray-700 dark:text-gray-300 md:text-right">Country *</label>
                <input placeholder="Country" type="text" name="country" class="w-full md:w-28 rounded" required 
                       minlength="2" maxlength="50" pattern="[a-zA-Z\s\.\-]+" 
                       title="Country must be 2-50 characters, letters, spaces, periods and hyphens only"
                       value="<?php echo htmlspecialchars($editing ? $edit_order['country'] : ($country ?? '')); ?>">
            </div>
        </div>
        
        <!-- Required fields note -->
        <div class="mt-4 mb-6">
            <p class="text-xs text-gray-600 dark:text-gray-400">
                <span class="text-red-500">*</span> Required fields
            </p>
        </div>
                
        <h2 class="text-xl font-semibold mt-8 mb-3 text-gray-800 dark:text-gray-100">Products</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full rounded" id="items-table">
                <thead class="bg-gray-100 dark:bg-gray-900">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-96">Product</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-28">Quantity</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-32">Unit Price</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-32">Total</th>
                    <th class="px-3 py-2"></th>
                </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="line-items-body"></tbody>
            </table>
        </div>
        <button type="button" id="add-row" class="mt-3 inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded shadow hover:bg-blue-700">+ Add Product</button>
        <div class="mt-6 flex justify-end">
            <div class="text-right space-y-2">
                <div class="flex justify-between items-center min-w-48">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Subtotal:</span>
                    <span id="subtotal" class="text-lg font-semibold text-gray-800 dark:text-gray-100">$0.00</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Shipping:</span>
                    <span id="shipping-cost" class="text-lg font-semibold text-gray-800 dark:text-gray-100">$0.00</span>
                </div>
                <div class="border-t border-gray-300 dark:border-gray-600 pt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-medium text-gray-700 dark:text-gray-300">Total:</span>
                        <span id="grand-total" class="text-2xl font-bold text-gray-800 dark:text-gray-100">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="inline-flex justify-center px-6 py-3 <?php echo $editing ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-600 hover:bg-green-700'; ?> text-white font-semibold rounded transition-colors">
                <?php echo $editing ? 'Update Request' : 'Submit Request'; ?>
            </button>
        </div>
    </form>
    
    <?php if (!$editing): ?>
    <!-- Footer message for new orders -->
    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex items-start">
            
                <ion-icon name="information-circle-outline" class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0 align-middle" aria-hidden="true"></ion-icon>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                A representative will reach out to you by the end of the day to complete the verification process and confirm your preferred payment method.
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const productOptions = <?php echo $optionJson; ?>;
function formatMoney(v){return '$' + Number(v).toFixed(2);} 

// Shipping cost table based on quantity and size (matching Excel data)
const shippingRates = {
  '8oz': {
    1: 5.51, 2: 6.91, 3: 7.35, 4: 7.35, 5: 7.35, 6: 7.35,
    12: 10.00, 24: 18.74, 36: 23.15, 72: 37.22
  },
  '2oz': {
    1: 4.41, 2: 4.74, 3: 5.51, 4: 6.49, 5: 6.91, 6: 6.91,
    12: 7.35, 24: 12.59, 36: 12.59, 72: 18.74
  },
  '.5oz': {
    1: 4.41, 2: 4.41, 3: 4.74, 4: 4.74, 5: 4.74, 6: 4.74,
    12: 6.91, 24: 8.48, 36: 9.17, 72: 15.35
  }
};

// JavaScript VLOOKUP function to mimic Excel behavior
function vlookup(lookupValue, quantities, rates, approximateMatch = true) {
  if (approximateMatch) {
    // Find the largest quantity that is <= lookupValue
    let bestMatch = null;
    for (let qty of quantities) {
      if (qty <= lookupValue) {
        bestMatch = qty;
      } else {
        break;
      }
    }
    return bestMatch ? rates[bestMatch] : 0;
  } else {
    // Exact match
    return rates[lookupValue] || 0;
  }
}

// Function to get shipping rate for a specific quantity and size using Excel logic
function getShippingRate(totalQty, size) {
  const rates = shippingRates[size];
  if (!rates) return 0;
  
  const quantities = Object.keys(rates).map(q => parseInt(q)).sort((a, b) => a - b);
  
  if (size === '.5oz') {
    // Special logic for 0.5oz products matching Excel J2 formula
    if (totalQty <= 72) {
      // Simple VLOOKUP for quantities <= 72
      return vlookup(totalQty, quantities, rates, true);
    } else {
      // Cost for 72 units + CEILING((qty-72)/72,1)*6.91
      const baseCost = rates[72];
      const additionalBatches = Math.ceil((totalQty - 72) / 72);
      return baseCost + (additionalBatches * 6.91);
    }
  } else {
    // Logic for 8oz and 2oz products matching Excel H2 and I2 formulas
    // First part: VLOOKUP(MIN(qty,72), table, column, TRUE)
    const firstPartQty = Math.min(totalQty, 72);
    let totalShipping = vlookup(firstPartQty, quantities, rates, true);
    
    // Second part: IF(qty>72, VLOOKUP(qty-72, table, column, TRUE), 0)
    if (totalQty > 72) {
      const remainingQty = totalQty - 72;
      totalShipping += vlookup(remainingQty, quantities, rates, true);
    }
    
    return totalShipping;
  }
}

// Function to calculate total shipping cost
function calculateShipping() {
  const sizeGroups = {};
  
  // Group quantities by product size
  document.querySelectorAll('#line-items-body tr').forEach(tr => {
    const select = tr.querySelector('.product-select');
    const qtyInput = tr.querySelector('input[name="quantity[]"]');
    
    if (select && select.selectedIndex > 0 && qtyInput) {
      const opt = select.options[select.selectedIndex];
      const qty = parseInt(qtyInput.value) || 0;
      let size = opt.dataset.size || opt.getAttribute('data-size') || '';
      
      // Normalize size to match our shipping table
      if (size.includes('8')) size = '8oz';
      else if (size.includes('2')) size = '2oz';
      else if (size.includes('.5') || size.includes('0.5')) size = '.5oz';
      else size = '2oz'; // default to 2oz if size not clear
      
      if (qty > 0) {
        if (!sizeGroups[size]) sizeGroups[size] = 0;
        sizeGroups[size] += qty;
      }
    }
  });
  
  // Calculate shipping for each size group
  let totalShipping = 0;
  Object.keys(sizeGroups).forEach(size => {
    const qty = sizeGroups[size];
    totalShipping += getShippingRate(qty, size);
  });
  
  return totalShipping;
} 
// Build grouped data structure
const grouped = {};
productOptions.forEach(o => {
  if(!grouped[o.product_name]) grouped[o.product_name] = [];
  grouped[o.product_name].push(o);
});

// Select2 templating functions
function formatOption(option) {
  if (!option.id) {
    return option.text;
  }
  
  const $option = $(option.element);
  const packageImage = $option.data('package-image');
  const imageSrc = packageImage ? 'imgs/' + packageImage : 'imgs/default.jpg';
  
  const $result = $(
    '<div class="select2-option-with-image" >' +
      '<img class="select2-option-image" src="' + imageSrc + '" alt="Package Image" />' +
      '<div class="select2-option-text">' + option.text + '</div>' +
    '</div>'
  );
  
  return $result;
}

function formatSelection(option) {
  if (!option.id) {
    return option.text;
  }
  
  // For selected item, return only text without image
  return option.text;
}

let rowIndex = 0;
function buildSelect(nameAttr='product_id[]'){
  const select = document.createElement('select');
  select.name = nameAttr;
  select.className = 'product-select w-full';
  select.innerHTML = '<option></option>' + Object.keys(grouped).map(gName => {
      const opts = grouped[gName].map(o => `<option value="${o.product_id}" data-package-id="${o.package_id||''}" data-price="${o.price}" data-product-name="${o.product_name}" data-package-image="${o.package_image||''}" data-package-quantity="${o.package_quantity||1}" data-size="${o.size||''}">${o.label||'Base'}</option>`).join('');
      return `<optgroup label="${gName}">${opts}</optgroup>`;
  }).join('');
  return select;
}
function addRow(){
  const tbody = document.getElementById('line-items-body');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2 align-top " style="padding-left: 0;">
      <div class="space-y-1">
        <div class="select-wrapper"></div>
        <input type="hidden" name="package_id[]" value="">
      </div>
    </td>
    <td class="px-3 py-2">
      <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1 w-fit">
        <button type="button" class="qty-btn decrease-btn inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200  transition-colors dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 border-0 appearance-none" style="-webkit-appearance: none;">
                    <ion-icon name="remove-outline" class="h-4 w-4 align-middle" aria-hidden="true"></ion-icon>
        </button>
        <input type="number" name="quantity[]" min="1" value="1" class="w-10 h-8 mx-1 text-center bg-white dark:bg-gray-800 border-0 rounded-md  text-sm font-medium text-gray-700 dark:text-gray-300 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none" style="-webkit-appearance: none; -moz-appearance: textfield; text-align: center !important; padding:0;" data-package-qty="1" required>
        <button type="button" class="qty-btn increase-btn inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-200  transition-colors dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-600 border-0 appearance-none" style="-webkit-appearance: none;">
                    <ion-icon name="add-outline" class="h-4 w-4 align-middle" aria-hidden="true"></ion-icon>
        </button>
      </div>
    </td>
    <td class="px-3 py-2">
      <span class="unit-price text-sm font-medium dark:text-gray-400">$0.00</span>
      <input type="hidden" name="unit_price[]" value="0.00">
    </td>
    <td class="px-3 py-2">
      <span class="line-total text-sm font-medium">$0.00</span>
    </td>
    <td class="px-3 py-2 text-right">
      <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Remove</button>
    </td>`;
  tbody.appendChild(tr);
  const wrapper = tr.querySelector('.select-wrapper');
  const sel = buildSelect();
  wrapper.appendChild(sel);
  
  // Initialize Select2 with custom templating
  $(sel).select2({ 
    placeholder:'Select product', 
    width:'100%', 
    allowClear:true,
    templateResult: formatOption,
    templateSelection: formatSelection
  });
  $(sel).on('select2:select select2:clear', () => updateRow(tr));
}
function updateRow(tr){
  const select = tr.querySelector('.product-select');
  const unitPriceSpan = tr.querySelector('.unit-price');
  const unitPriceInput = tr.querySelector('input[name="unit_price[]"]');
  const qtyInput = tr.querySelector('input[name="quantity[]"]');
  const hiddenPkg = tr.querySelector('input[name="package_id[]"]');
  const opt = select.options[select.selectedIndex];
  
  if(!opt || !opt.dataset.price){ 
    unitPriceSpan.textContent = '$0.00';
    unitPriceInput.value = '0.00';
    hiddenPkg.value=''; 
    qtyInput.setAttribute('data-package-qty', '1');
    qtyInput.value = '1';
  }
  else { 
    const price = Number(opt.dataset.price).toFixed(2);
    unitPriceSpan.textContent = '$' + price;
    unitPriceInput.value = price;
    hiddenPkg.value = opt.dataset.packageId || opt.getAttribute('data-package-id');
    const packageQty = parseInt(opt.dataset.packageQuantity || opt.getAttribute('data-package-quantity')) || 1;
    qtyInput.setAttribute('data-package-qty', packageQty);
    qtyInput.value = packageQty;
    qtyInput.setAttribute('min', packageQty);
  }
  updateLineTotal(tr);
  recalcTotal();
}
function updateLineTotal(tr){
  const price = parseFloat(tr.querySelector('input[name="unit_price[]"]').value) || 0;
  const qty = parseInt(tr.querySelector('input[name="quantity[]"]').value) || 0;
  const lineTotal = price * qty;
  tr.querySelector('.line-total').textContent = formatMoney(lineTotal);
}
function recalcTotal(){
  let subtotal = 0; 
  document.querySelectorAll('#line-items-body tr').forEach(tr=>{ 
    const p=parseFloat(tr.querySelector('input[name="unit_price[]"]').value)||0; 
    const q=parseInt(tr.querySelector('input[name="quantity[]"]').value)||0; 
    subtotal += p * q;
    updateLineTotal(tr);
  });
  
  // Calculate shipping
  const shipping = calculateShipping();
  const total = subtotal + shipping;
  
  // Update display
  document.getElementById('subtotal').textContent = formatMoney(subtotal);
  document.getElementById('shipping-cost').textContent = formatMoney(shipping);
  document.getElementById('grand-total').textContent = formatMoney(total);
}
$('#add-row').on('click', addRow);
$(document).on('input', 'input[name="quantity[]"]', recalcTotal);
$(document).on('click', '.remove-row', function(){ $(this).closest('tr').remove(); recalcTotal(); });

// Handle quantity button clicks
$(document).on('click', '.qty-btn', function(e) {
  e.preventDefault();
  const btn = $(this);
  const qtyInput = btn.siblings('input[name="quantity[]"]');
  const currentQty = parseInt(qtyInput.val()) || 1;
  const packageQty = parseInt(qtyInput.attr('data-package-qty')) || 1;
  const minQty = packageQty;
  
  if (btn.hasClass('increase-btn')) {
    qtyInput.val(currentQty + packageQty);
  } else if (btn.hasClass('decrease-btn') && currentQty > minQty) {
    const newQty = currentQty - packageQty;
    qtyInput.val(newQty >= minQty ? newQty : minQty);
  }
  
  // Trigger recalculation
  qtyInput.trigger('input');
});

// Handle manual quantity input validation
$(document).on('blur', 'input[name="quantity[]"]', function() {
  const qtyInput = $(this);
  let qty = parseInt(qtyInput.val()) || 1;
  const packageQty = parseInt(qtyInput.attr('data-package-qty')) || 1;
  const minQty = packageQty;
  
  // Ensure quantity is a multiple of package quantity
  if (qty % packageQty !== 0) {
    qty = Math.round(qty / packageQty) * packageQty;
  }
  
  // Ensure minimum quantity
  if (qty < minQty) qty = minQty;
  
  qtyInput.val(qty);
  qtyInput.trigger('input');
});

if(document.getElementById('line-items-body').children.length===0){ addRow(); }

<?php if ($editing && !empty($edit_items)): ?>
// Pre-populate existing order items for editing
$(document).ready(function() {
    // Starting edit mode
    
    // Clear any existing rows first
    $('#line-items-body').empty();
    
    // Wait for products to be fully loaded, then add rows
    setTimeout(function() {
        <?php foreach ($edit_items as $index => $item): ?>
        // Add row for item <?php echo $index; ?>
        // Adding row for product
        setTimeout(function() {
            addRow();
            const row = $('#line-items-body tr').last();
            const select = row.find('.product-select');
            
            // Row added
            
            // Wait for Select2 to be fully initialized
            setTimeout(function() {
                // Setting product value
                // Set the selected option
                select.val('<?php echo $item['product_id']; ?>').trigger('change.select2');
                
                // Wait for change event to process, then update values
                setTimeout(function() {
                    // Updating values for row
                    // Set quantity
                    row.find('input[name="quantity[]"]').val(<?php echo $item['quantity']; ?>);
                    
                    // Set hidden package ID
                    row.find('input[name="package_id[]"]').val('<?php echo $item['package_id'] ?? ''; ?>');
                    
                    // Set unit price
                    row.find('input[name="unit_price[]"]').val('<?php echo $item['unit_price']; ?>');
                    row.find('.unit-price').text('$<?php echo number_format($item['unit_price'], 2); ?>');
                    
                    // Update line total
                    updateLineTotal(row[0]);
                    
                    // Row populated successfully
                    
                    // Recalculate totals (only on last item)
                    <?php if ($index === count($edit_items) - 1): ?>
                    setTimeout(function() {
                        // Recalculating total for all items
                        recalcTotal();
                    }, 200);
                    <?php endif; ?>
                }, 300);
            }, 150);
        }, <?php echo $index * 400; ?>);
        <?php endforeach; ?>
    }, 800);
});
<?php endif; ?>

<?php if ($is_admin): ?>
// Theme toggle functionality (compatible with layout.php)
$(document).ready(function() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            const html = document.documentElement;
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
        });
    }
});
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
$hide_sidebar = true;
// Force this page to render in light mode and prevent it from reading/writing the global theme preference
$force_light = true;
include 'layout.php';
