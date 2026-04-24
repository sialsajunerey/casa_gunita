<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Filter by date (optional)
$filter_date  = isset($_GET['date']) ? $_GET['date'] : '';
$where_clause = $filter_date ? "WHERE DATE(t.transaction_date) = '$filter_date'" : '';

// Fetch transactions
$transactions = mysqli_query($conn,
    "SELECT t.*, o.order_type, o.notes, u.full_name
     FROM transactions t
     JOIN orders o ON t.order_id = o.order_id
     JOIN users u ON t.user_id = u.user_id
     $where_clause
     ORDER BY t.transaction_date DESC");

// Total for the filter/day
$total_sql = mysqli_query($conn,
    "SELECT COALESCE(SUM(amount_paid), 0) as grand_total 
     FROM transactions t
     $where_clause");
$grand_total = mysqli_fetch_assoc($total_sql)['grand_total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transactions — Casa Gunita Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .navbar {
            background: #8B0000; color: white;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .container { padding: 30px; }
        .top-bar {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 20px;
            flex-wrap: wrap; gap: 10px;
        }
        .filter-form {
            display: flex; align-items: center; gap: 10px;
        }
        input[type=date] {
            padding: 8px 12px;
            border: 1px solid #ddd; border-radius: 5px; font-size: 14px;
        }
        .btn {
            padding: 8px 18px; border: none;
            border-radius: 5px; cursor: pointer;
            font-weight: bold; text-decoration: none;
            display: inline-block; font-size: 14px;
        }
        .btn-dark   { background: #8B0000; color: white; }
        .btn-gray   { background: #95a5a6; color: white; }
        .btn-blue   { background: #3498db; color: white; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #8B0000; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .cash  {
            background: #d4edda; color: #155724;
            padding: 4px 10px; border-radius: 10px; font-size: 12px; font-weight: bold;
        }
        .gcash {
            background: #cce5ff; color: #004085;
            padding: 4px 10px; border-radius: 10px; font-size: 12px; font-weight: bold;
        }
        .summary-bar {
            background: white; border-radius: 8px;
            padding: 15px 20px; margin-bottom: 20px;
            display: flex; gap: 30px; align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        .summary-bar .amount {
            font-size: 28px; font-weight: bold; color: #8B0000;
        }
        .summary-bar .label { color: #666; font-size: 13px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Transactions</h2>
    <div>
        <a href="index.php">Dashboard</a>
        <a href="orders.php">Orders</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="top-bar">
        <h3>Transaction History</h3>

        <!-- Date Filter -->
        <form method="GET" class="filter-form">
            <input type="date" name="date" 
                   value="<?= $filter_date ?>">
            <button type="submit" class="btn btn-dark">Filter</button>
            <?php if ($filter_date): ?>
                <a href="transactions.php" class="btn btn-gray">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Summary Bar -->
    <div class="summary-bar">
        <div>
            <div class="amount"><?= formatPrice($grand_total) ?></div>
            <div class="label">
                <?= $filter_date 
                    ? 'Total for ' . date('F d, Y', strtotime($filter_date))
                    : 'All-time Total Revenue' ?>
            </div>
        </div>
        <div>
            <div class="amount"><?= mysqli_num_rows($transactions) ?></div>
            <div class="label">
                <?= $filter_date ? 'Orders on this date' : 'Total Transactions' ?>
            </div>
        </div>
    </div>

    <?php if (mysqli_num_rows($transactions) === 0): ?>
        <p style="text-align:center; color:#999; padding:30px;">
            No transactions found.
            <?= $filter_date ? '<a href="transactions.php">View all</a>' : '' ?>
        </p>
    <?php else: ?>
    <table>
        <tr>
            <th>Transaction #</th>
            <th>Order #</th>
            <th>Customer</th>
            <th>Order Type</th>
            <th>Amount Paid</th>
            <th>Payment</th>
            <th>Date & Time</th>
            <th>Receipt</th>
        </tr>

        <?php while ($t = mysqli_fetch_assoc($transactions)): ?>
        <tr>
            <td>#<?= str_pad($t['transaction_id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td>#<?= str_pad($t['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= $t['full_name'] ?></td>
            <td><?= ucfirst($t['order_type']) ?></td>
            <td><?= formatPrice($t['amount_paid']) ?></td>
            <td>
                <span class="<?= $t['payment_method'] ?>">
                    <?= strtoupper($t['payment_method']) ?>
                </span>
            </td>
            <td><?= date('M d, Y h:i A', strtotime($t['transaction_date'])) ?></td>
            <td>
                <a href="receipt.php?order_id=<?= $t['order_id'] ?>" 
                   class="btn btn-blue">Receipt</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>

</div>

</body>
</html>