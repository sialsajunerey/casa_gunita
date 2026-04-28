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
            --line: rgba(33,3,3,.1);
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
        a { color: inherit; text-decoration: none; }
        .navbar {
            background: var(--crimson);
            color: #fff;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h2 {
            margin: 0;
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            letter-spacing: .08em;
        }
        .navbar a { color: rgba(255,255,255,.92); margin-left: 18px; font-weight: 600; }
        .navbar a:hover { opacity: .9; }
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }
        h3 { margin: 0 0 22px; font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--crimson); }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
            box-shadow: var(--shadow);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .order-table th,
        .order-table td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }
        .order-table th {
            background: var(--crimson-d);
            color: var(--gold);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: .08em;
        }
        .order-table tr:last-child td { border-bottom: none; }
        .order-table tr:nth-child(even) { background: #fcf5e8; }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .pending   { background: #f59e0b; }
        .preparing { background: #0d6efd; }
        .ready     { background: #16a34a; }
        .completed { background: #15803d; }
        .cancelled { background: #dc2626; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            transition: opacity .15s ease, transform .15s ease;
        }
        .btn-primary { background: var(--crimson); color: #fff; }
        .btn-primary:hover { opacity: .95; transform: translateY(-1px); }
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
    <table class="order-table">
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