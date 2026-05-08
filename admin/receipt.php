<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$order_id = (int)$_GET['order_id'];
$from = $_GET['from'] ?? 'dashboard';
$back_href = $from === 'orders' ? 'orders.php' : 'index.php';
$back_label = $from === 'orders' ? 'Back to Orders' : 'Back to Dashboard';

// Fetch order with customer info
$order_stmt = mysqli_prepare($conn,
    "SELECT o.*, u.full_name, u.email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.order_id = ?");
mysqli_stmt_bind_param($order_stmt, 'i', $order_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order) { echo "Order not found."; exit(); }

// Fetch order items
$columnExists = mysqli_query($conn, "SHOW COLUMNS FROM order_items LIKE 'options'");
if ($columnExists && mysqli_num_rows($columnExists) === 0) {
    mysqli_query($conn, "ALTER TABLE order_items ADD COLUMN options TEXT NULL");
}

$items_stmt = mysqli_prepare($conn,
    "SELECT oi.item_id, oi.order_id, oi.product_id, oi.quantity, oi.unit_price, oi.subtotal, oi.options, p.name 
     FROM order_items oi 
     JOIN products p ON oi.product_id = p.product_id 
     WHERE oi.order_id = ?");
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
mysqli_stmt_store_result($items_stmt);
mysqli_stmt_bind_result($items_stmt, $item_id, $order_id_fk, $product_id_fk, $quantity, $unit_price, $subtotal, $options_json, $name);
$items = [];
while (mysqli_stmt_fetch($items_stmt)) {
    $items[] = [
        'item_id' => $item_id,
        'order_id' => $order_id_fk,
        'product_id' => $product_id_fk,
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'subtotal' => $subtotal,
        'options' => $options_json,
        'name' => $name,
    ];
}

// Fetch transaction if completed
$trans_stmt = mysqli_prepare($conn,
    "SELECT payment_method, amount_paid FROM transactions WHERE order_id = ?");
mysqli_stmt_bind_param($trans_stmt, 'i', $order_id);
mysqli_stmt_execute($trans_stmt);
mysqli_stmt_store_result($trans_stmt);
$transaction = null;
if (mysqli_stmt_num_rows($trans_stmt) > 0) {
    mysqli_stmt_bind_result($trans_stmt, $payment_method, $amount_paid);
    mysqli_stmt_fetch($trans_stmt);
    $transaction = [
        'payment_method' => $payment_method,
        'amount_paid'    => $amount_paid,
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $order_id ?> — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../user/receipt.css">
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
<p><b>Email:</b> <?= $order['email'] ?></p>
<?php
    $adminAddressParts = array_filter([
        trim(($order['house_number'] ?? '') . ' ' . ($order['street'] ?? '')),
        $order['barangay'] ?? '',
        $order['city'] ?? ''
    ]);
?>
<?php if (!empty($adminAddressParts)): ?>
<p><b>Address:</b> <?= htmlspecialchars(implode(', ', $adminAddressParts), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<p><b>Date:</b> <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></p>
<p><b>Type:</b> <?= ucfirst($order['order_type']) ?></p>
<?php if ($order['notes']): ?>
<p><b>Notes:</b> <?= $order['notes'] ?></p>
<?php endif; ?>

<div class="divider"></div>

<b>Items Ordered:</b><br><br>
<?php foreach ($items as $item): ?>
<?php $options = !empty($item['options']) ? json_decode($item['options'], true) : []; ?>
<div class="row">
    <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?> x<?= (int)$item['quantity'] ?></span>
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
                    if (isset($opt['group_type']) && $opt['group_type'] === 'addon') {
                        $price = ' (+' . formatPrice($opt['additional_price']) . ')';
                    } else {
                        $price = ' (' . formatPrice($opt['additional_price']) . ')';
                    }
                }
                $grouped[$group][] = $label . $price;
            }
            foreach ($grouped as $group => $items):
            ?>
                <div><strong><?= $group ?>:</strong> <?= htmlspecialchars(implode(', ', $items), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php endforeach; ?>

<div class="divider"></div>

<div class="row">
    <b>TOTAL</b>
    <b><?= formatPrice($order['total_amount']) ?></b>
</div>

<?php if ($transaction): ?>
<div class="divider"></div>
<p><b>Payment:</b> <?= strtoupper($transaction['payment_method']) ?></p>
<p><b>Paid:</b> <?= formatPrice($transaction['amount_paid']) ?></p>
<?php endif; ?>

<div class="divider"></div>

<div style="text-align:center">
    <p>Status: <b><?= strtoupper($order['status']) ?></b></p>
    <p>Thank you for dining at Casa Gunita!</p>
</div>

<div class="actions">
    <button class="button" onclick="window.print()">🖨️ Print Receipt</button>
    <a class="button button-outline" href="<?= $back_href ?>">← <?= $back_label ?></a>
</div>

    </div>
</div>

</body>
</html>
