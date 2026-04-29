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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="menu.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=EB+Garamond:wght@400;500&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </div>
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search">
    </div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="about.php">About</a>
    </div>
    <div class="nav-icons">
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= count($_SESSION['cart']) ?></span>
            <?php endif; ?>
        </a>
        <div class="account-wrap">
            <button class="nav-icon-btn" id="accountBtn" aria-label="Account">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </button>
            <div class="account-dropdown" id="accountDropdown">
                <a href="account.php">Account Information</a>
                <a href="order_status.php">My Orders</a>
                <hr>
                <a href="logout.php">Log Out</a>
            </div>
        </div>
    </div>
</nav>

<!-- ===== CONTENT ===== -->
<div class="content">
    <h1 class="page-title">Hapág ng Gunita</h1>

    <!-- Category Bar -->
    <div class="top-category-bar" id="categoryBar">
        <?php foreach ($categories as $cat): ?>
            <a href="menu.php?category_id=<?= (int)$cat['category_id'] ?>"
               class="category-button <?= $category_id === (int)$cat['category_id'] ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Menu Grid -->
    <div class="menu-grid">
        <?php if (empty($products)): ?>
            <div class="empty-msg">No products found for this category.</div>
        <?php else: ?>
            <?php foreach ($products as $item): ?>
                <div class="item-card">
                    <?php if (!empty($item['image'])): ?>
                        <div class="item-img-wrap">
                            <img src="../assets/images/<?= htmlspecialchars($item['image']) ?>"
                                 alt="<?= htmlspecialchars($item['name']) ?>">
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
    // Account dropdown
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    accountBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });
    document.addEventListener('click', function() {
        accountDropdown.classList.remove('open');
    });

    // Hide/show category bar on scroll
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
</script>

</body>
</html>