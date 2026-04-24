<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id  = (int)$_POST['order_id'];
    $newstatus = $_POST['status'];

    // If marking as completed, insert into transactions
    if ($newstatus === 'completed') {
        $order = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM orders WHERE order_id = $order_id"));

        $stmt = mysqli_prepare($conn,
            "INSERT INTO transactions (order_id, user_id, amount_paid, payment_method)
             VALUES (?, ?, ?, ?)");
        $payment = $_POST['payment_method'] ?? 'cash';
        mysqli_stmt_bind_param($stmt, 'iids',
            $order_id, $order['user_id'], $order['total_amount'], $payment);
        mysqli_stmt_execute($stmt);
    }

    // Update order status
    $stmt = mysqli_prepare($conn,
        "UPDATE orders SET status = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $newstatus, $order_id);
    mysqli_stmt_execute($stmt);

    header("Location: orders.php");
    exit();
}

// Fetch all orders with customer name
$orders = mysqli_query($conn,
    "SELECT o.*, u.full_name 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     ORDER BY o.created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Orders — Casa Gunita Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
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
        select, button {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        button {
            background: #8B0000; color: white;
            border: none; cursor: pointer;
            font-weight: bold;
        }
        button:hover { background: #a00000; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Orders</h2>
    <div>
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>All Orders</h3>

    <table>
        <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Total</th>
            <th>Notes</th>
            <th>Status</th>
            <th>Date</th>
            <th>Update Status</th>
            <th>Receipt</th>
        </tr>

        <?php if (mysqli_num_rows($orders) === 0): ?>
        <tr>
            <td colspan="9" style="text-align:center; color:#999; padding:30px;">
                No orders yet. Waiting for customers...
            </td>
        </tr>
        <?php else: ?>
            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
            <tr>
                <td>#<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td><?= $order['full_name'] ?></td>
                <td><?= ucfirst($order['order_type']) ?></td>
                <td><?= formatPrice($order['total_amount']) ?></td>
                <td><?= $order['notes'] ?: '—' ?></td>
                <td>
                    <span class="badge <?= $order['status'] ?>">
                        <?= strtoupper($order['status']) ?>
                    </span>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
                <td>
                    <?php if ($order['status'] !== 'completed' && 
                              $order['status'] !== 'cancelled'): ?>
                    <form method="POST">
                        <input type="hidden" name="order_id" 
                               value="<?= $order['order_id'] ?>">
                        <select name="status">
                            <option value="pending"
                                <?= $order['status']==='pending'   ? 'selected':'' ?>>
                                Pending</option>
                            <option value="preparing"
                                <?= $order['status']==='preparing' ? 'selected':'' ?>>
                                Preparing</option>
                            <option value="ready"
                                <?= $order['status']==='ready'     ? 'selected':'' ?>>
                                Ready</option>
                            <option value="completed"
                                <?= $order['status']==='completed' ? 'selected':'' ?>>
                                Completed</option>
                            <option value="cancelled"
                                <?= $order['status']==='cancelled' ? 'selected':'' ?>>
                                Cancelled</option>
                        </select>
                        <select name="payment_method">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                        </select>
                        <button type="submit">Update</button>
                    </form>
                    <?php else: ?>
                        <i>Finalized</i>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="receipt.php?order_id=<?= $order['order_id'] ?>">
                        View
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>

    </table>
</div>

</body>
</html>