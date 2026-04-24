<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Count pending orders
$pending = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'"))['total'];

// Count low stock items
$low_stock = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM inventory WHERE stock_quantity <= low_stock_alert"))['total'];

// Count today's completed orders
$today_sales = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount_paid), 0) as total 
     FROM transactions 
     WHERE DATE(transaction_date) = CURDATE()"))['total'];

// Count total products
$total_products = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total FROM products"))['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard — Casa Gunita</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }

        .navbar {
            background: #8B0000;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar a { color: white; text-decoration: none; margin-left: 20px; }

        .container { padding: 30px; }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .card h2 { font-size: 36px; margin: 0; color: #8B0000; }
        .card p  { margin: 5px 0 0; color: #666; }

        .nav-links {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .nav-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 18px;
            font-weight: bold;
        }

        .nav-card:hover { background: #8B0000; color: white; }
    </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Admin</h2>
    <div>
        <span>Welcome, <?= $_SESSION['full_name'] ?></span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <h3>Dashboard Overview</h3>

    <!-- Summary Cards -->
    <div class="cards">
        <div class="card">
            <h2><?= $pending ?></h2>
            <p>Pending Orders</p>
        </div>
        <div class="card">
            <h2><?= $total_products ?></h2>
            <p>Total Products</p>
        </div>
        <div class="card">
            <h2><?= $low_stock ?></h2>
            <p>Low Stock Alerts</p>
        </div>
        <div class="card">
            <h2><?= formatPrice($today_sales) ?></h2>
            <p>Today's Sales</p>
        </div>
    </div>

    <!-- Navigation Cards -->
    <h3>Manage</h3>
    <div class="nav-links">
        <a class="nav-card" href="orders.php">📋 Orders</a>
        <a class="nav-card" href="products.php">🍖 Products</a>
        <a class="nav-card" href="inventory.php">📦 Inventory</a>
        <a class="nav-card" href="transactions.php">💰 Transactions</a>
        <a class="nav-card" href="products_add.php">➕ Add Product</a>
    </div>

</div>

</body>
</html>