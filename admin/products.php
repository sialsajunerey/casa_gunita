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
$valid_category_ids = array_column($categories, 'category_id');
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
<html>
<head>
    <title>Products — Casa Gunita Admin</title>
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
        }
        .btn {
            padding: 10px 20px; border-radius: 5px;
            text-decoration: none; font-weight: bold;
            display: inline-block;
        }
        .btn-green { background: #27ae60; color: white; }
        .btn-blue  { background: #3498db; color: white; }
        .btn-red   { background: #e74c3c; color: white; }
        .btn-secondary { background: #f6c70c; color: #111; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #8B0000; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        img.thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; }
        .no-img {
            width: 60px; height: 60px; background: #f0e0e0;
            display: flex; align-items: center;
            justify-content: center; border-radius: 5px; font-size: 24px;
        }
        .available   { color: #27ae60; font-weight: bold; }
        .unavailable { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Products</h2>
    <div>
        <a href="index.php">Dashboard</a>
        <a href="orders.php">Orders</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="top-bar">
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <form method="GET" style="display:flex; gap:10px; align-items:center; margin:0;">
                    <label style="margin:0; color:#333; font-weight:bold;">Category:</label>
                    <select name="category" onchange="this.form.submit()" style="padding:8px; border-radius:5px; border:1px solid #ddd;">
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
            <td><?= $p['name'] ?></td>
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

</body>
</html>