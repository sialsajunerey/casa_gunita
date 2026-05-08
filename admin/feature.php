<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$error = '';
$success = '';
$search_category = trim($_GET['search_category'] ?? '');
$search_product  = trim($_GET['search_product'] ?? '');

$categoryColumn = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'is_featured'");
if ($categoryColumn && mysqli_num_rows($categoryColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE categories ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
}

$productColumn = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'is_featured'");
if ($productColumn && mysqli_num_rows($productColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_categories = array_map('intval', $_POST['featured_categories'] ?? []);
    $selected_products   = array_map('intval', $_POST['featured_products'] ?? []);

    if (count($selected_categories) > 3 || count($selected_products) > 3) {
        $error = 'You can select up to 3 categories and up to 3 featured dishes.';
    } else {
        mysqli_query($conn, "UPDATE categories SET is_featured = 0");
        if (!empty($selected_categories)) {
            mysqli_query($conn, "UPDATE categories SET is_featured = 1 WHERE category_id IN (" . implode(',', $selected_categories) . ")");
        }

        mysqli_query($conn, "UPDATE products SET is_featured = 0");
        if (!empty($selected_products)) {
            mysqli_query($conn, "UPDATE products SET is_featured = 1 WHERE product_id IN (" . implode(',', $selected_products) . ")");
        }

        $admin_id = $_SESSION['user_id'] ?? null;
        $cat_list = !empty($selected_categories) ? implode(', ', $selected_categories) : 'None';
        $prod_list = !empty($selected_products) ? implode(', ', $selected_products) : 'None';
        $audit_stmt = mysqli_prepare($conn,
            "INSERT INTO audit_logs (admin_id, action, target_type, details)
             VALUES (?, 'menu_featured', 'featured', ?)");
        $details = "Featured Categories: $cat_list | Featured Products: $prod_list";
        mysqli_stmt_bind_param($audit_stmt, 'is', $admin_id, $details);
        mysqli_stmt_execute($audit_stmt);

        $success = 'Featured categories and dishes have been updated.';
    }
}

$categorySql = "SELECT * FROM categories";
$categoryParams = [];
if ($search_category !== '') {
    $categorySql .= " WHERE name LIKE ?";
    $categoryParams[] = '%' . $search_category . '%';
}
$categorySql .= " ORDER BY name";
if (!empty($categoryParams)) {
    $stmt = mysqli_prepare($conn, $categorySql);
    mysqli_stmt_bind_param($stmt, 's', $categoryParams[0]);
    mysqli_stmt_execute($stmt);
    $categories = mysqli_stmt_get_result($stmt);
} else {
    $categories = mysqli_query($conn, $categorySql);
}

$productSql = "SELECT * FROM products";
$productParams = [];
if ($search_product !== '') {
    $productSql .= " WHERE name LIKE ?";
    $productParams[] = '%' . $search_product . '%';
}
$productSql .= " ORDER BY name";
if (!empty($productParams)) {
    $stmt = mysqli_prepare($conn, $productSql);
    mysqli_stmt_bind_param($stmt, 's', $productParams[0]);
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt);
} else {
    $products = mysqli_query($conn, $productSql);
}

$featured_categories = [];
$featured_products = [];
$sel = mysqli_query($conn, "SELECT category_id FROM categories WHERE is_featured = 1");
while ($row = mysqli_fetch_assoc($sel)) {
    $featured_categories[] = $row['category_id'];
}
$sel = mysqli_query($conn, "SELECT product_id FROM products WHERE is_featured = 1");
while ($row = mysqli_fetch_assoc($sel)) {
    $featured_products[] = $row['product_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="feature.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php" class="active">Feature</a></li>
        <li><a href="customizations.php">Customizations</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php">Logout</a></div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Feature Management</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">
        <div class="card">
            <div class="page-header">
                <div>
                    <h2>Feature Settings</h2>
                    <p class="hint">Choose which categories appear in We Offer and which dishes appear in Featured Dishes. Maximum 3 of each.</p>
                </div>
                <div class="feature-actions">
                    <button type="submit" form="featureForm">Save Changes</button>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form id="featureForm" method="POST">
                <div class="section-block">
                    <div class="section-header">
                        <div>
                            <h3>Featured Categories</h3>
                            <p class="hint">Select up to 3 categories.</p>
                        </div>
                        <input id="categorySearch" type="search" placeholder="Search Categories" value="<?= htmlspecialchars($search_category, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="grid" id="categoryGrid">
                        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                            <?php 
                                if ($cat['image']) {
                                    $catImage = strpos($cat['image'], '/') === false ? '../assets/images/' . $cat['image'] : $cat['image'];
                                } else {
                                    $catImage = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIj48cmVjdCBmaWxsPSIjZThhMDcyIiB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIvPjwvc3ZnPg==';
                                }
                            ?>
                            <label class="feature-card" data-title="<?= htmlspecialchars(strtolower($cat['name']), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="checkbox" class="feature-checkbox feature-category" name="featured_categories[]" value="<?= (int)$cat['category_id'] ?>" <?= in_array($cat['category_id'], $featured_categories, true) ? 'checked' : '' ?> />
                                <img src="<?= $catImage ?>" alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="feature-card-body">
                                    <div class="feature-card-title"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="feature-card-meta">Category ID: <?= (int)$cat['category_id'] ?></div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="section-block">
                    <div class="section-header">
                        <div>
                            <h3>Featured Dishes</h3>
                            <p class="hint">Select up to 3 dishes.</p>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <select id="dishCategoryFilter" class="input-group" style="min-width:160px; height:34px; padding:0 10px;">
                                <option value="all">All Categories</option>
                                <?php 
                                mysqli_data_seek($categories, 0);
                                while($cf = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?= (int)$cf['category_id'] ?>"><?= htmlspecialchars($cf['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input id="productSearch" type="search" placeholder="Search Dishes" value="<?= htmlspecialchars($search_product, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="grid" id="productGrid">
                        <?php while ($item = mysqli_fetch_assoc($products)): ?>
                            <?php 
                                if ($item['image']) {
                                    $imgFile = strpos($item['image'], '/') === false ? '../assets/images/' . $item['image'] : $item['image'];
                                } else {
                                    $imgFile = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIj48cmVjdCBmaWxsPSIjZThhMDcyIiB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIvPjwvc3ZnPg==';
                                }
                            ?>
                            <label class="feature-card" data-title="<?= htmlspecialchars(strtolower($item['name']), ENT_QUOTES, 'UTF-8') ?>" data-category-id="<?= (int)$item['category_id'] ?>">
                                <input type="checkbox" class="feature-checkbox feature-product" name="featured_products[]" value="<?= (int)$item['product_id'] ?>" <?= in_array($item['product_id'], $featured_products, true) ? 'checked' : '' ?> />
                                <img src="<?= $imgFile ?>" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="feature-card-body">
                                    <div class="feature-card-title"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="feature-card-meta">PHP <?= formatPrice($item['price']) ?></div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function enforceLimit(selector, limit) {
        const checkboxes = document.querySelectorAll(selector);
        function update() {
            const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
            checkboxes.forEach(cb => {
                cb.disabled = !cb.checked && checked >= limit;
            });
        }
        checkboxes.forEach(cb => cb.addEventListener('change', update));
        update();
    }

    function initLiveSearch(inputId, gridId, filterId = null) {
        const input = document.getElementById(inputId);
        const grid = document.getElementById(gridId);
        const filter = filterId ? document.getElementById(filterId) : null;
        if (!input || !grid) return;
        
        const update = () => {
            const query = input.value.trim().toLowerCase();
            const filterVal = filter ? filter.value : 'all';
            const cards = grid.querySelectorAll('.feature-card');
            cards.forEach(card => {
                const title = card.dataset.title || '';
                const matchesSearch = title.includes(query);
                const matchesFilter = filterVal === 'all' || card.dataset.categoryId === filterVal;
                card.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
            });
        };
        input.addEventListener('input', update);
        if (filter) filter.addEventListener('change', update);
        update();
    }

    enforceLimit('.feature-category', 3);
    enforceLimit('.feature-product', 3);
    initLiveSearch('categorySearch', 'categoryGrid');
    initLiveSearch('productSearch', 'productGrid', 'dishCategoryFilter');
</script>

</body>
</html>