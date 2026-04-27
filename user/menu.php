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
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .navbar { background: #8B0000; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 18px; font-weight: bold; }
        .navbar .links a { color: #fff; text-decoration: none; margin-left: 18px; }
        .navbar .links a:hover { text-decoration: underline; }
        .content { padding: 30px 20px; max-width: 1200px; margin: 0 auto; }
        .page-title { margin: 0 0 16px; color: #333; }
        .cart-summary { margin: 12px 0 24px; font-weight: bold; }
        .top-category-bar { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin: 0 auto 20px; padding: 12px 16px; overflow-x: auto; position: sticky; top: 0; z-index: 15; background: #f5f5f5; transition: transform .25s ease, opacity .25s ease; border-bottom: 1px solid #e9e9e9; }
        .top-category-bar.hidden { transform: translateY(-110%); opacity: 0; }
        .category-button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 999px; border: 1px solid transparent; background: #f7f7f7; color: #222; text-decoration: none; font-weight: 600; transition: all .2s ease; white-space: nowrap; }
        .category-button:hover { background: #f0f0f0; }
        .category-button.active { background: #8B0000; color: #fff; border-color: #8B0000; }
        .menu-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(240px, 240px)); justify-content: center; align-items: start; }
        .item-card { border: 1px solid #e5e5e5; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 8px 22px rgba(0,0,0,0.08); display: flex; flex-direction: column; width: 240px; }
        .item-img-wrap { width: 100%; height: 180px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fafafa; }
        .item-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .item-img-wrap.placeholder { color: #777; font-size: 14px; text-align: center; padding: 16px; }
        .item-info { padding: 14px 14px 10px; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
        .item-name { font-size: 17px; color: #222; margin: 0; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .item-price { font-size: 15px; font-weight: 700; color: #8B0000; }
        .order-link { display: block; width: 100%; text-align: center; background: #8B0000; color: #fff; text-decoration: none; padding: 12px 0; border-radius: 12px; font-size: 15px; font-weight: 700; letter-spacing: .02em; }
        .order-link:hover { background: #a10000; }
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