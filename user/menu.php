<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selected_category_name = null;
if ($category_id > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['category_id'] === $category_id) {
            $selected_category_name = $cat['name'];
            break;
        }
    }
}

if (!$selected_category_name && !empty($categories)) {
    $category_id = (int)$categories[0]['category_id'];
    $selected_category_name = $categories[0]['name'];
}

$query = "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_available = 1";
if ($category_id > 0) {
    $query .= " AND p.category_id = ?";
}
$query .= " ORDER BY p.name";

$stmt = mysqli_prepare($conn, $query);
if ($category_id > 0) {
    mysqli_stmt_bind_param($stmt, 'i', $category_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Menu — Casa Gunita</title>
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
        .navbar .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: .08em; }
        .navbar .links a { color: rgba(255,255,255,.92); margin-left: 18px; font-weight: 600; }
        .navbar .links a:hover { opacity: .9; }
        .content {
            padding: 32px 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .page-title {
            margin: 0 0 16px;
            color: var(--crimson);
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
        }
        .cart-summary { margin: 16px 0 28px; font-weight: 700; color: var(--muted); }
        .top-category-bar {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0 auto 22px;
            padding: 12px 16px;
            overflow-x: auto;
            position: sticky;
            top: 0;
            z-index: 15;
            background: var(--bg);
            border-bottom: 1px solid rgba(33,3,3,.08);
        }
        .category-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            border: 1px solid rgba(33,3,3,.08);
            font-weight: 700;
            transition: all .2s ease;
            white-space: nowrap;
        }
        .category-button:hover { background: #f7e8d8; }
        .category-button.active {
            background: var(--crimson);
            color: #fff;
            border-color: var(--crimson);
        }
        .menu-grid {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            align-items: stretch;
        }
        .item-card {
            border: 1px solid rgba(33,3,3,.08);
            border-radius: var(--radius);
            overflow: hidden;
            background: var(--surface);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        .item-img-wrap {
            width: 100%;
            height: 180px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff7ed;
        }
        .item-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .item-img-wrap.placeholder { color: var(--muted); font-size: 0.95rem; text-align: center; padding: 16px; }
        .item-info {
            padding: 18px 18px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }
        .item-name {
            font-size: 1rem;
            color: var(--ink);
            margin: 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .item-price { font-size: 1rem; font-weight: 700; color: var(--crimson); }
        .order-link {
            display: inline-flex;
            width: 100%;
            text-align: center;
            background: var(--crimson);
            color: #fff;
            padding: 13px 0;
            border-radius: 0 0 var(--radius) var(--radius);
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: .02em;
            transition: opacity .2s ease;
        }
        .order-link:hover { opacity: .95; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand">Casa Gunita — Menu</div>
        <div class="links">
            <a href="index.php">🏠</a>
            <a href="cart.php">Cart (<span id="nav-cart-count"><?= isset($_SESSION['cart']) ? getCartItemCount($_SESSION['cart']) : 0 ?></span>)</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    <div class="content">
        <h1 class="page-title">Casa Gunita Menu</h1>
        <p class="cart-summary">🛒 Current Cart Item (<span id="menu-cart-count"><?= isset($_SESSION['cart']) ? getCartItemCount($_SESSION['cart']) : 0 ?></span>)</p>

        <div class="top-category-bar" id="categoryBar">
            <?php foreach ($categories as $cat): ?>
                <a href="menu.php?category_id=<?= (int)$cat['category_id'] ?>"
                   class="category-button <?= $category_id === (int)$cat['category_id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="menu-grid">
            <?php if (empty($products)): ?>
                <div style="grid-column:1/-1; padding: 20px; background:#fff; border-radius:18px; border:1px solid #eee;">
                    <p style="margin:0; color:#555;">No products found for this category.</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $item): ?>
                    <div class="item-card">
                        <?php if (!empty($item['image'])): ?>
                            <div class="item-img-wrap">
                                <img src="../assets/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                        <?php else: ?>
                            <div class="item-img-wrap placeholder">
                                <span>Image coming soon</span>
                            </div>
                        <?php endif; ?>
                        <div class="item-info">
                            <strong class="item-name"><?= htmlspecialchars($item['name']) ?></strong>
                            <span class="item-price"><?= formatPrice($item['price']) ?></span>
                        </div>
                        <a href="customize.php?product_id=<?= htmlspecialchars($item['product_id']) ?>" class="order-link">Order</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateMenuCartCount(count) {
            const navCount = document.getElementById('nav-cart-count');
            const menuCount = document.getElementById('menu-cart-count');
            if (navCount) navCount.textContent = count;
            if (menuCount) menuCount.textContent = count;
        }

        (function() {
            const categoryBar = document.getElementById('categoryBar');
            let lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;

            window.addEventListener('scroll', function() {
                const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (!categoryBar) return;

                if (currentScroll > lastScrollTop + 10) {
                    categoryBar.classList.add('hidden');
                } else if (currentScroll < lastScrollTop - 10) {
                    categoryBar.classList.remove('hidden');
                }

                lastScrollTop = currentScroll;
            });
        })();

    </script>
</body>
</html>