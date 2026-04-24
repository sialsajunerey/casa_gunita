<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$order_id = (int)$_GET['order_id'];
$user_id  = $_SESSION['user_id'];

// Fetch order — make sure it belongs to this customer
$order_stmt = mysqli_prepare($conn,
    "SELECT o.*, u.full_name, u.email 
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
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .page-wrapper { max-width: 700px; margin: 20px auto; padding: 20px; }
        .receipt-card { background: #fff; border: 1px solid #8B0000; border-radius: 12px; padding: 24px; box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .receipt-header { text-align: center; margin-bottom: 22px; }
        .receipt-header h2 { margin: 0; color: #8B0000; }
        .receipt-header p { margin: 6px 0 0; color: #555; }
        .divider { border-top: 1px dashed #ccc; margin: 18px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; color: #333; }
        .row strong { color: #111; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; justify-content: center; }
        .button { background: #8B0000; color: #fff; border: none; border-radius: 8px; padding: 12px 20px; text-decoration: none; cursor: pointer; font-weight: bold; }
        .button-outline { background: #fff; color: #8B0000; border: 1px solid #8B0000; }
        .button:hover { opacity: 0.95; }
    </style>
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
<p><b>Type:</b> <?= ucfirst($order['order_type']) ?></p>
<?php if ($order['notes']): ?>
<p><b>Notes:</b> <?= $order['notes'] ?></p>
<?php endif; ?>

<div class="divider"></div>

<b>Items Ordered:</b><br><br>

<?php while ($item = mysqli_fetch_assoc($items)): ?>
<div class="row">
    <span><?= $item['name'] ?> x<?= $item['quantity'] ?></span>
    <span><?= formatPrice($item['subtotal']) ?></span>
</div>
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
    <button class="button" onclick="window.print()">🖨️ Print Receipt</button>
        <a class="button button-outline" href="index.php">Home</a>

    </div>
</div>

</body>
</html>