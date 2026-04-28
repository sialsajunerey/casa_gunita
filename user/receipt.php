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
    <style>
        :root {
            --crimson: #210303;
            --crimson-d: #130301;
            --gold: #e8d191;
            --ink: #130301;
            --muted: #674328;
            --surface: #fff8eb;
            --bg: #f4f2ea;
            --radius: 16px;
            --shadow: 0 18px 50px rgba(33,3,3,.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        .page-wrapper {
            max-width: 760px;
            margin: 24px auto;
            padding: 20px;
        }
        .receipt-card {
            background: var(--surface);
            border: 1px solid var(--crimson);
            border-radius: var(--radius);
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .receipt-header { text-align: center; margin-bottom: 24px; }
        .receipt-header h2 {
            margin: 0;
            color: var(--crimson);
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
        }
        .receipt-header p { margin: 8px 0 0; color: var(--muted); }
        .divider { border-top: 1px dashed #d3c6b1; margin: 22px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 9px; color: var(--ink); }
        .row strong { color: var(--ink); }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; justify-content: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border-radius: 14px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; }
        .btn-primary { background: var(--crimson); color: #fff; }
        .btn-primary:hover { opacity: .95; }
        .btn-outline { background: #fff; color: var(--crimson); border: 1px solid var(--crimson); }
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
<?php if ($transaction): ?>
<p><b>Payment:</b> <?= strtoupper(htmlspecialchars($transaction['payment_method'], ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>
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