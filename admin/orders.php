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

    // Update order status only.
    $stmt = mysqli_prepare($conn,
        "UPDATE orders SET status = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $newstatus, $order_id);
    mysqli_stmt_execute($stmt);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    header("Location: orders.php");
    exit();
}

// Filter by date
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$where_clause = '';
if ($filter_date) {
    $filter_date = mysqli_real_escape_string($conn, $filter_date);
    $where_clause = "WHERE DATE(o.created_at) = '$filter_date'";
}

// Fetch all orders with customer name
$orders = mysqli_query($conn,
    "SELECT o.*, u.full_name 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     $where_clause
     ORDER BY o.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Casa Gunita Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --crimson: #210303;
    --crimson-d: #130301;
    --primary-dark: var(--crimson-d);
    --crimson-l: #f7e3c6;
    --gold: #e8d191;
    --ink: #130301;
    --muted: #674328;
    --line: rgba(33,3,3,.1);
    --surface: #fff8eb;
    --bg: #f4f2ea;
    --sidebar-w: 220px;
    --header-h: 64px;
    --radius: 14px;
    --shadow: 0 2px 18px rgba(33,3,3,.08);
}
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
}
.sidebar {
    width: var(--sidebar-w);
    background: var(--crimson);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
}
.sidebar-logo {
    padding: 22px 20px 18px;
    border-bottom: 1px solid rgba(255,255,255,.12);
}
.sidebar-logo .brand {
    font-family: 'Cinzel Decorative', serif;
    font-size: 18px;
    color: #fff;
    line-height: 1.2;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.sidebar-logo .sub {
    font-size: 11px;
    color: rgba(255,255,255,.55);
    margin-top: 2px;
    letter-spacing: .5px;
}
.nav-list {
    list-style: none;
    padding: 16px 12px;
    flex: 1;
}
.nav-list li { margin-bottom: 4px; }
.nav-list a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background .15s, color .15s;
}
.nav-list a:hover,
.nav-list a.active {
    background: rgba(255,255,255,.14);
    color: #fff;
}
.nav-list a .icon { font-size: 16px; width: 20px; text-align: center; }
.sidebar-footer {
    padding: 16px 12px;
    border-top: 1px solid rgba(255,255,255,.12);
}
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.65);
    text-decoration: none;
    font-size: 14px;
    transition: background .15s, color .15s;
}
.sidebar-footer a:hover { background: rgba(255,255,255,.1); color: #fff; }
.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.topbar {
    height: var(--header-h);
    background: var(--surface);
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-title {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: var(--crimson);
    white-space: nowrap;
}
.topbar-spacer { flex: 1; }
.topbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--ink);
}
.avatar {
    width: 34px;
    height: 34px;
    background: var(--crimson);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}
.content {
    padding: 24px 28px;
    display: flex;
    flex-direction: column;
    gap: 22px;
    flex: 1;
}
.card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow);
}
table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
th, td {
    padding: 14px 16px;
    border-bottom: 1px solid #ecebf1;
    text-align: left;
}
th {
    background: var(--gold);
    color: var(--primary-dark);
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: .08em;
}
.content h3 {
    font-family: 'Cinzel Decorative', serif;
    font-size: 1.8rem;
    color: var(--crimson);
    margin-bottom: 0.5rem;
}
tr:last-child td { border-bottom: none; }
tr:nth-child(even) { background: #fbfbfd; }
.badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: #111;
    background: #f4f2ea;
}
.badge-pending   { background: #fef3c7; color: #92400e; }
.badge-preparing { background: #dbeafe; color: #1e40af; }
.badge-ready     { background: #d1fae5; color: #065f46; }
.badge-completed { background: #dcfce7; color: #14532d; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
select, button { padding: 9px 12px; border: 1px solid #d6d2d9; border-radius: 10px; font-size: 14px; }
button { cursor: pointer; border: none; background: var(--crimson); color: #fff; transition: background .2s; }
button:hover { background: var(--crimson-d); }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php" class="active"><span class="icon">📋</span> Orders</a></li>
        <li><a href="products.php"><span class="icon">🍖</span> Products</a></li>
        <li><a href="inventory.php"><span class="icon">📦</span> Inventory</a></li>
        <li><a href="transactions.php"><span class="icon">💰</span> Transactions</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Orders</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">
        <div class="card">
            <div class="page-header">
                <h2>Orders</h2>
                <form method="GET" class="filter-row" style="margin:0;">
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($filter_date): ?><a href="orders.php" class="btn btn-gray">Clear</a><?php endif; ?>
                </form>
            </div>
        </div>

    <table>
        <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Update Status</th>
            <th>Receipt</th>
        </tr>

        <?php if (mysqli_num_rows($orders) === 0): ?>
        <tr>
            <td colspan="8" style="text-align:center; color:#999; padding:30px;">
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
                        <select name="status" onchange="this.form.submit()">
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
</div>

</body>
</html>