<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$user_id = $_SESSION['user_id'];

// Fetch all orders by this customer
$orders = mysqli_prepare($conn,
    "SELECT * FROM orders 
     WHERE user_id = ? 
     ORDER BY created_at DESC");
mysqli_stmt_bind_param($orders, 'i', $user_id);
mysqli_stmt_execute($orders);
$result = mysqli_stmt_get_result($orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders — Casa Gunita</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f9f4ef; }
        .navbar {
            background: #8B0000; color: white;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .container { padding: 30px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #8B0000; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge {
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: bold; color: white;
        }
        .pending   { background: #f39c12; }
        .preparing { background: #3498db; }
        .ready     { background: #2ecc71; }
        .completed { background: #27ae60; }
        .cancelled { background: #e74c3c; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita</h2>
    <div>
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="cart.php">🛒 Cart</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>My Orders</h3>

    <?php if (mysqli_num_rows($result) === 0): ?>
        <p style="text-align:center; color:#999; padding:30px;">
            You have no orders yet. 
            <a href="menu.php">Order now!</a>
        </p>
    <?php else: ?>
    <table>
        <tr>
            <th>Order #</th>
            <th>Total</th>
            <th>Type</th>
            <th>Status</th>
            <th>Date</th>
            <th>Receipt</th>
        </tr>
        <?php while ($order = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td>#<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= formatPrice($order['total_amount']) ?></td>
            <td><?= ucfirst($order['order_type']) ?></td>
            <td>
                <span class="badge <?= $order['status'] ?>">
                    <?= strtoupper($order['status']) ?>
                </span>
            </td>
            <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
            <td>
                <a href="receipt.php?order_id=<?= $order['order_id'] ?>">
                    View Receipt
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>
</div>

</body>
</html>