<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

// Fetch featured products (latest 6)
$featured = mysqli_query($conn,
    "SELECT * FROM products WHERE is_available = 1 ORDER BY created_at DESC LIMIT 6");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Casa Gunita — Home</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f9f4ef; }

        /* Navbar */
        .navbar {
            background: #8B0000;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .navbar a:hover { text-decoration: underline; }

        /* Hero */
        .hero {
            background: #8B0000;
            color: white;
            text-align: center;
            padding: 80px 20px;
        }
        .hero h1 { font-size: 48px; margin-bottom: 10px; }
        .hero p  { font-size: 20px; margin-bottom: 30px; opacity: 0.9; }
        .hero a  {
            background: white;
            color: #8B0000;
            padding: 14px 35px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
        }
        .hero a:hover { background: #f0e0e0; }

        /* Featured */
        .container { padding: 40px 30px; }
        .container h2 { text-align: center; margin-bottom: 30px; color: #8B0000; }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .card-body { padding: 15px; }
        .card-body h3 { margin-bottom: 5px; color: #333; }
        .card-body p  { color: #666; font-size: 13px; margin-bottom: 10px; }
        .card-body span { color: #8B0000; font-weight: bold; font-size: 18px; }

        .no-image {
            width: 100%;
            height: 180px;
            background: #f0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        /* Order Now Button */
        .order-btn {
            display: block;
            width: fit-content;
            margin: 30px auto 0;
            background: #8B0000;
            color: white;
            padding: 14px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
        }
        .order-btn:hover { background: #a00000; }

        /* Footer */
        footer {
            background: #8B0000;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <h2>🍽️ Casa Gunita</h2>
    <div>
        <a href="menu.php">Menu</a>
        <a href="cart.php">🛒 Cart
            (<?= isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0 ?>)
        </a>
        <a href="order_status.php">My Orders</a>
        <span style="margin-left:20px">
            👤 <?= $_SESSION['full_name'] ?>
        </span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- Hero Section -->
<div class="hero">
    <h1>🍚 Casa Gunita</h1>
    <p>Authentic Filipino Food — Luto ng may Puso</p>
    <a href="menu.php">Order Now</a>
</div>

<!-- Featured Dishes -->
<div class="container">
    <h2>Featured Dishes</h2>

    <?php if (mysqli_num_rows($featured) === 0): ?>
        <p style="text-align:center; color:#999;">
            Menu coming soon. Check back later!
        </p>
    <?php else: ?>
        <div class="grid">
            <?php while ($item = mysqli_fetch_assoc($featured)): ?>
            <div class="card">
                <?php if ($item['image']): ?>
                    <img src="/casa_gunita/assets/images/<?= $item['image'] ?>" 
                         alt="<?= $item['name'] ?>">
                <?php else: ?>
                    <div class="no-image">🍽️</div>
                <?php endif; ?>
                <div class="card-body">
                    <h3><?= $item['name'] ?></h3>
                    <p><?= $item['description'] ?></p>
                    <span><?= formatPrice($item['price']) ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <a class="order-btn" href="menu.php">View Full Menu →</a>
</div>

<!-- Footer -->
<footer>
    <p>© <?= date('Y') ?> Casa Gunita — Authentic Filipino Food</p>
</footer>

</body>
</html>