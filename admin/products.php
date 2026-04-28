<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM products WHERE product_id = $id");
    header("Location: products.php");
    exit();
}

$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}
$valid_category_ids = array_map('intval', array_column($categories, 'category_id'));
if ($category_filter && !in_array($category_filter, $valid_category_ids, true)) {
    $category_filter = 0;
}

if ($category_filter) {
    $stmt = mysqli_prepare($conn,
        "SELECT p.*, c.name AS category_name, i.stock_quantity
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.category_id
         LEFT JOIN inventory i ON p.product_id = i.product_id
         WHERE p.category_id = ?
         ORDER BY c.name, p.name");
    mysqli_stmt_bind_param($stmt, 'i', $category_filter);
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt);
} else {
    $products = mysqli_query($conn,
        "SELECT p.*, c.name AS category_name, i.stock_quantity
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.category_id
         LEFT JOIN inventory i ON p.product_id = i.product_id
         ORDER BY c.name, p.name");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — Casa Gunita Admin</title>
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
.btn {
    padding: 10px 20px; border-radius: 10px;
    text-decoration: none; font-weight: bold;
    display: inline-flex; align-items: center; gap: 8px;
}
.btn-green { background: #27ae60; color: white; }
.btn-blue { background: #3498db; color: white; }
.btn-red { background: #e74c3c; color: white; }
.btn-secondary { background: #f6c70c; color: #111; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
select, input, .btn { border: 1px solid #d6d2d9; }
img.thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; }
.no-img { width: 60px; height: 60px; background: #f0e0e0; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 24px; }
.available { color: #27ae60; font-weight: bold; }
.unavailable { color: #e74c3c; font-weight: bold; }
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
        <li><a href="products.php" class="active"><span class="icon">🍖</span> Products</a></li>
        <li><a href="inventory.php"><span class="icon">📦</span> Inventory</a></li>
        <li><a href="transactions.php"><span class="icon">💰</span> Transactions</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Products</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">
        <div class="card">
            <div class="top-bar" style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <form method="GET" style="display:flex; gap:10px; align-items:center; margin:0;">
                        <label style="margin:0; color:#333; font-weight:bold;">Category:</label>
                        <select name="category" onchange="this.form.submit()" style="padding:10px 12px; border-radius:10px; border:1px solid #ddd;">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" <?= $category_filter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($category_filter): ?>
                            <a href="products.php" class="btn btn-secondary">Show All</a>
                        <?php endif; ?>
                    </form>
                </div>
                <a href="products_add.php" class="btn btn-green">+ Add New Product</a>
            </div>
        </div>
    <?php if (mysqli_num_rows($products) === 0): ?>
        <p style="text-align:center; color:#999; padding:30px;">
            No products yet. Click "Add New Product" to start.
        </p>
    <?php else: ?>
    <table>
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Available</th>
            <th>Actions</th>
        </tr>
        <?php while ($p = mysqli_fetch_assoc($products)): ?>
        <tr>
            <td>
                <?php if ($p['image']): ?>
                    <img class="thumb"
                         src="/casa_gunita/assets/images/<?= $p['image'] ?>"
                         alt="<?= $p['name'] ?>">
                <?php else: ?>
                    <div class="no-img">🍽️</div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= formatPrice($p['price']) ?></td>
            <td><?= $p['stock_quantity'] ?? 0 ?></td>
            <td>
                <?php if ($p['is_available']): ?>
                    <span class="available">Yes</span>
                <?php else: ?>
                    <span class="unavailable">No</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="products_edit.php?id=<?= $p['product_id'] ?>"
                   class="btn btn-blue">Edit</a>
                <a href="products.php?delete=<?= $p['product_id'] ?>"
                   class="btn btn-red"
                   onclick="return confirm('Delete this product?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>
    </div>
</div>

</body>
</html>