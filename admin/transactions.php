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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transactions — Casa Gunita Admin</title>
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
    font-size: 17px;
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
tr:last-child td { border-bottom: none; }
tr:nth-child(even) { background: #fbfbfd; }
.btn { padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; }
.btn-blue { background: #3498db; color: white; }
.btn-gray { background: #95a5a6; color: white; }
.status-cash { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 999px; font-weight: 700; }
.status-gcash { background: #cce5ff; color: #004085; padding: 6px 12px; border-radius: 999px; font-weight: 700; }
.summary-bar {
    display: flex; gap: 30px; align-items: center; background: var(--surface); padding: 18px 22px; border-radius: 14px; box-shadow: var(--shadow);
}
.summary-bar .amount { font-size: 28px; font-weight: 700; color: var(--crimson); }
.summary-bar .label { color: var(--muted); font-size: 13px; }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="menu.php"><span class="icon">�️</span> Menu</a></li>
        <li><a href="feature.php"><span class="icon">⭐</span> Feature</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Transactions</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom: 18px;">
                <h3>Transaction History</h3>
                <form method="GET" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin:0;">
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8') ?>" style="padding:10px 14px; border-radius:10px; border:1px solid #d6d2d9; background:#fff;">
                    <button type="submit" class="btn btn-blue">Filter</button>
                    <?php if ($filter_date): ?><a href="transactions.php" class="btn btn-gray">Clear</a><?php endif; ?>
                </form>
            </div>
            <div class="summary-bar">
                <div>
                    <div class="amount"><?= formatPrice($grand_total) ?></div>
                    <div class="label"><?= $filter_date ? 'Total for ' . date('F d, Y', strtotime($filter_date)) : 'All-time Total Revenue' ?></div>
                </div>
                <div>
                    <div class="amount"><?= mysqli_num_rows($transactions) ?></div>
                    <div class="label"><?= $filter_date ? 'Orders on this date' : 'Total Transactions' ?></div>
                </div>
            </div>
        </div>
        <?php if (mysqli_num_rows($transactions) === 0): ?>
            <div class="card" style="text-align:center; color:var(--muted); padding: 40px;">No transactions found.</div>
        <?php else: ?>
        <div class="card">
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
                    <td><?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(ucfirst($t['order_type']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= formatPrice($t['amount_paid']) ?></td>
                    <td><span class="status-<?= htmlspecialchars($t['payment_method'], ENT_QUOTES, 'UTF-8') ?>"><?= strtoupper(htmlspecialchars($t['payment_method'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                    <td><?= date('M d, Y h:i A', strtotime($t['transaction_date'])) ?></td>
                    <td><a href="receipt.php?order_id=<?= (int)$t['order_id'] ?>" class="btn btn-blue">Receipt</a></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>