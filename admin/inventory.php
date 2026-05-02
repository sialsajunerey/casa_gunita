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

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$inventory = mysqli_query($conn,
    "SELECT i.*, p.name AS product_name, c.name AS category_name
     FROM inventory i
     LEFT JOIN products p ON i.product_id = p.product_id
     LEFT JOIN categories c ON p.category_id = c.category_id
     ORDER BY p.name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory — Casa Gunita Admin</title>
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
.status-low { color: #b45309; font-weight: 700; }
.status-ok  { color: #065f46; font-weight: 700; }
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
        <div class="topbar-title">Inventory</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">
        <div class="card">
            <h3>Inventory Records</h3>
            <?php if (mysqli_num_rows($inventory) === 0): ?>
                <p style="color:var(--muted); padding: 24px 0;">No inventory records found.</p>
            <?php else: ?>
            <table>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Low Stock Alert</th>
                    <th>Updated</th>
                    <th>Action</th>
                </tr>
                <?php while ($item = mysqli_fetch_assoc($inventory)): ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name'] ?: 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$item['stock_quantity'] ?></td>
                    <td class="<?= (int)$item['stock_quantity'] <= (int)$item['low_stock_alert'] ? 'status-low' : 'status-ok' ?>">
                        <?= (int)$item['low_stock_alert'] ?>
                    </td>
                    <td><?= htmlspecialchars($item['updated_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a href="menu_edit.php?id=<?= (int)$item['product_id'] ?>" class="btn btn-blue">Edit</a></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>