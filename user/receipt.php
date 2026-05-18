<?php
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$order_id = (int)$_GET['order_id'];
$user_id  = $_SESSION['user_id'];

// Fetch order — make sure it belongs to this customer
$order_stmt = mysqli_prepare($conn,
    "SELECT o.*, CONCAT_WS(' ', u.first_name, u.last_name) AS full_name, u.email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ? AND o.user_id = ?");
mysqli_stmt_bind_param($order_stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) {
    echo "Order not found.";
    exit();
}

$txn_stmt = mysqli_prepare($conn,
    "SELECT payment_method, amount_paid FROM transactions WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($txn_stmt, 'i', $order_id);
mysqli_stmt_execute($txn_stmt);
$transaction = mysqli_fetch_assoc(mysqli_stmt_get_result($txn_stmt));

// Fetch order items
$items_stmt = mysqli_prepare($conn,
    "SELECT oi.*, p.name 
     FROM order_items oi 
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?");
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
$items = mysqli_stmt_get_result($items_stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Receipt — Casa Gunita</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="receipt.css">
</head>
<body>

<div class="page-wrapper">
    <div class="receipt-card">
        <div class="receipt-header">
            <h2>Casa Gunita</h2>
            <p>Authentic Filipino Food</p>
        </div>

<div class="divider"></div>

<p><b>Order #:</b> <?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></p>
<p><b>Customer:</b> <?= $order['full_name'] ?></p>
<p><b>Date:</b> <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></p>
<p><b>Type:</b> <?= $order['order_type'] === 'takeout' ? 'Pick-Up' : ucfirst($order['order_type']) ?></p>
<?php
    $userAddressParts = array_filter([
        trim(($order['house_number'] ?? '') . ' ' . ($order['street'] ?? '')),
        $order['barangay'] ?? '',
        $order['city'] ?? ''
    ]);
?>
<?php if (!empty($userAddressParts)): ?>
<p><b>Address:</b> <?= htmlspecialchars(implode(', ', $userAddressParts), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($transaction): ?>
<p><b>Payment:</b> <?= strtoupper(htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>
<?php if ($order['notes']): ?>
<p><b>Notes:</b> <?= $order['notes'] ?></p>
<?php endif; ?>

<div class="divider"></div>

<b>Items Ordered:</b><br><br>

<?php while ($item = mysqli_fetch_assoc($items)): ?>
<?php $options = !empty($item['options']) ? json_decode($item['options'], true) : []; ?>
<div class="row">
    <span><?= $item['name'] ?> x<?= $item['quantity'] ?></span>
    <span><?= formatPrice($item['subtotal']) ?></span>
</div>
<?php if (!empty($options) && is_array($options)): ?>
    <div class="row" style="padding-left:14px;font-size:13px;color:#555;">
        <div style="width:100%;">
            <?php
            $grouped = [];
            foreach ($options as $opt) {
                $group = htmlspecialchars($opt['group_name'] ?? ($opt['group_type'] === 'addon' ? 'Add-ons' : ($opt['group_type'] === 'size' ? 'Size' : 'Flavor')), ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($opt['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $price = '';
                if (isset($opt['additional_price']) && $opt['additional_price'] > 0) {
                    $price = ' (+' . formatPrice($opt['additional_price']) . ')';
                }
                $grouped[$group][] = $label . $price;
            }
            foreach ($grouped as $group => $items_list):
            ?>
                <div><strong><?= $group ?>:</strong> <?= htmlspecialchars(implode(', ', $items_list), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php endwhile; ?>

<div class="divider"></div>

<div class="row">
    <b>TOTAL</b>
    <b><?= formatPrice($order['total_amount']) ?></b>
</div>

<div class="divider"></div>

<div style="text-align:center">
    <p>Status: <b><?= strtoupper($order['status']) ?></b></p>
    <p>Thank you for ordering at Casa Gunita!</p>
</div>

<div class="actions">
    <button class="button" onclick="window.print()">Print Receipt</button>
    <a class="button button-outline" href="index.php">Home</a>
</div>
</div>

</body>
</html>