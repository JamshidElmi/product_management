<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Order ID is required');
}

$order_id = (int)$_GET['id'];

// Check if status column exists, if not add it
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
if ($checkStatusColumn->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN status ENUM('pending','processing','completed','canceled') NOT NULL DEFAULT 'pending' AFTER order_reference");
}

// Fetch order details with customer information
$orderQuery = $conn->prepare("
    SELECT o.*, c.* 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    WHERE o.id = ?
");
$orderQuery->bind_param('i', $order_id);
$orderQuery->execute();
$orderResult = $orderQuery->get_result();

if ($orderResult->num_rows === 0) {
    die('Order not found');
}

$order = $orderResult->fetch_assoc();
$orderQuery->close();

// Fetch order items with product details
$itemsQuery = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.flavor, p.size, pk.name as package_name
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    LEFT JOIN packages pk ON oi.package_id = pk.id 
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemsQuery->bind_param('i', $order_id);
$itemsQuery->execute();
$itemsResult = $itemsQuery->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}
$itemsQuery->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?php echo htmlspecialchars($order['order_reference']); ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 20px;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-logo {
            width: 180px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 5px;
        }
        
        .company-address {
            font-size: 11px;
            line-height: 1.3;
            color: #666;
        }
        
        .po-info {
            text-align: right;
            flex: 1;
        }
        
        .po-title {
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 10px;
        }
        
        .po-details {
            font-size: 11px;
        }
        
        .po-details div {
            margin-bottom: 3px;
        }
        
        .customer-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .customer-info, .order-info {
            flex: 1;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .customer-info {
            margin-right: 20px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #0066cc;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }
        
        .items-table td {
            font-size: 11px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        
        .totals-table .label {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: right;
            width: 60%;
        }
        
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
        }
        
        .total-row {
            background-color: #0066cc;
            color: white !important;
        }
        
        .footer {
            clear: both;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #cce5ff; color: #004085; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-canceled { background-color: #f8d7da; color: #721c24; }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0066cc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0052a3;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>
    
    <div class="header">
        <div class="company-info">
            <img src="imgs/yfs.png" alt="Company Logo" class="company-logo">
            <div class="company-name">Lubricity Innovations, Inc.</div>
            <div class="company-address">
                485 Cayuga Road<br>
                Buffalo, NY 14225
            </div>
        </div>
        <div class="po-info">
            <div class="po-title">PURCHASE ORDER</div>
            <div class="po-details">
                <div><strong>PO Number:</strong> <?php echo htmlspecialchars($order['order_reference']); ?></div>
                <div><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                <div><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo $order['status'] ?? 'pending'; ?>">
                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="customer-section">
        <div class="customer-info">
            <div class="section-title">Customer Information</div>
            <div><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
            <?php if ($order['business_name']): ?>
                <div><strong>Business:</strong> <?php echo htmlspecialchars($order['business_name']); ?></div>
            <?php endif; ?>
            <?php if ($order['email']): ?>
                <div><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></div>
            <?php endif; ?>
            <?php if ($order['phone']): ?>
                <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="order-info">
            <div class="section-title">Shipping Address</div>
            <?php if ($order['addressee']): ?>
                <div><strong>Addressee:</strong> <?php echo htmlspecialchars($order['addressee']); ?></div>
            <?php endif; ?>
            <?php if ($order['address_line']): ?>
                <div><?php echo htmlspecialchars($order['address_line']); ?></div>
            <?php endif; ?>
            <div>
                <?php 
                $address_parts = array_filter([
                    $order['city'], 
                    $order['state'], 
                    $order['zip']
                ]);
                echo htmlspecialchars(implode(', ', $address_parts)); 
                ?>
            </div>
            <?php if ($order['country']): ?>
                <div><?php echo htmlspecialchars($order['country']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%">Item #</th>
                <th style="width: 40%">Product Description</th>
                <th style="width: 15%">Package</th>
                <th style="width: 10%" class="text-center">Quantity</th>
                <th style="width: 12%" class="text-right">Unit Price</th>
                <th style="width: 13%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td class="text-center"><?php echo $index + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        <?php if ($item['flavor'] && strtolower($item['flavor']) !== 'n/a'): ?>
                            <br><small>Flavor: <?php echo htmlspecialchars($item['flavor']); ?></small>
                        <?php endif; ?>
                        <?php if ($item['size']): ?>
                            <br><small>Size: <?php echo htmlspecialchars($item['size']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['package_name'] ?? 'N/A'); ?></td>
                    <td class="text-center"><?php echo number_format($item['quantity']); ?></td>
                    <td class="text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="text-right">$<?php echo number_format($item['line_total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="amount">$<?php echo number_format($order['subtotal'] ?? 0, 2); ?></td>
            </tr>
            <tr>
                <td class="label">Shipping:</td>
                <td class="amount">$<?php echo number_format($order['shipping_cost'] ?? 0, 2); ?></td>
            </tr>
            <tr class="total-row">
                <td class="label" style="color:#000; font-weight:700">Total:</td>
                <td class="amount" style="color:white">$<?php echo number_format($order['total_amount'], 2); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="footer">
        <p>This is a purchase order request and not an invoice. Payment terms and methods will be confirmed separately.</p>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?> | Order ID: <?php echo $order['id']; ?></p>
    </div>
</body>
</html>