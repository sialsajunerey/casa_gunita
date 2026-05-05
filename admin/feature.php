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

        // Log audit: featured update
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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            --crimson: #210303;
            --crimson-d: #130301;
            --gold: #e8d191;
            --ink: #130301;
            --surface: #fff8eb;
            --bg: #f4f2ea;
            --line: rgba(33,3,3,.1);
            --radius: 14px;
            --shadow: 0 2px 18px rgba(33,3,3,.08);
            --sidebar-w: 220px;
            --header-h: 64px;
        }
        body { margin: 0; font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; display: flex; }
        .sidebar { width: var(--sidebar-w); background: var(--crimson); min-height: 100vh; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; }
        .sidebar-logo { padding: 22px 20px 18px; border-bottom: 1px solid rgba(255,255,255,.12); }
        .sidebar-logo .brand { font-family: 'Cinzel Decorative', serif; font-size: 18px; color: #fff; letter-spacing: 0.08em; text-transform: uppercase; }
        .nav-list { list-style: none; padding: 16px 12px; flex: 1; margin: 0; }
        .nav-list li { margin-bottom: 4px; }
        .nav-list a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,.75); text-decoration: none; font-size: 14px; font-weight: 500; transition: background .15s, color .15s; }
        .nav-list a.active, .nav-list a:hover { background: rgba(255,255,255,.14); color: #fff; }
        .nav-list a .icon { width: 20px; text-align: center; font-size: 16px; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,.65); text-decoration: none; font-size: 14px; transition: background .15s, color .15s; }
        .sidebar-footer a:hover { background: rgba(255,255,255,.1); color: #fff; }
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--line); display: flex; align-items: center; padding: 0 28px; gap: 16px; position: sticky; top: 0; z-index: 50; }
        .topbar-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--crimson); white-space: nowrap; }
        .topbar-spacer { flex: 1; }
        .topbar-user { display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 500; color: var(--ink); }
        .avatar { width: 34px; height: 34px; background: var(--crimson); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; }
        .content { padding: 24px 28px; display: flex; flex-direction: column; gap: 22px; }
        .card { background: var(--surface); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .page-header h2 { font-family: 'Cinzel Decorative', serif; font-size: 2rem; margin: 0; color: var(--crimson); }
        .hint { margin: 10px 0 0; color: #555; font-size: 0.95rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; align-items: center; }
        .filter-row input { height: 42px; padding: 0 14px; border-radius: 12px; border: 1px solid #c6c6c6; background: #fff; color: #111; flex: 1; min-width: 220px; }
        .filter-row button, .filter-row a { border-radius: 12px; }
        .feature-card { border: 1px solid #ddd; border-radius: 18px; overflow: hidden; background: #fff; display: flex; flex-direction: column; position: relative; }
        .feature-card input { position: absolute; top: 12px; right: 12px; width: 18px; height: 18px; }
        .feature-card img { width: 100%; min-height: 160px; object-fit: cover; }
        .feature-card-body { padding: 16px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .feature-card-title { font-size: 1rem; margin: 0 0 12px; font-weight: 700; }
        .feature-card-meta { color: #666; font-size: 0.95rem; line-height: 1.4; }
        .feature-actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; }
        button { border: none; border-radius: 12px; padding: 12px 18px; font-weight: 700; cursor: pointer; background: var(--crimson); color: #fff; transition: background .2s; }
        button:hover { background: var(--crimson-d); }
        .button-secondary { background: #6b7280; }
        .alert { border-radius: 14px; padding: 14px 18px; font-weight: 500; }
        .alert-error { background: #fee2e2; color: #981b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .disabled-note { color: #999; font-size: 0.95rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="menu.php"><span class="icon">🍽️</span> Menu</a></li>
        <li><a href="feature.php" class="active"><span class="icon">⭐</span> Feature</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php"><span class="icon">🚪</span> Logout</a></div>
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
                <div class="card" style="padding:20px; margin-bottom:24px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                        <div>
                            <h3 style="margin-bottom:16px;">Featured Categories</h3>
                            <p class="hint" style="margin-top:0;">Search featured categories and select up to 3.</p>
                        </div>
                        <input id="categorySearch" type="search" placeholder="Search categories" value="<?= htmlspecialchars($search_category, ENT_QUOTES, 'UTF-8') ?>" style="width:260px; min-width:200px;">
                    </div>
                    <div class="grid" id="categoryGrid">
                        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                            <?php $catImage = $cat['image'] ? (strpos($cat['image'], '/') === false ? '/casa_gunita/assets/images/' . $cat['image'] : $cat['image']) : 'bfastbg.jpg'; ?>
                            <label class="feature-card" data-title="<?= htmlspecialchars(strtolower($cat['name']), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="checkbox" class="feature-checkbox feature-category" name="featured_categories[]" value="<?= (int)$cat['category_id'] ?>" <?= in_array($cat['category_id'], $featured_categories, true) ? 'checked' : '' ?> />
                                <img src="<?= htmlspecialchars($catImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="feature-card-body">
                                    <div class="feature-card-title"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="feature-card-meta">Category ID: <?= (int)$cat['category_id'] ?></div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="card" style="padding:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                        <div>
                            <h3 style="margin-bottom:16px;">Featured Dishes</h3>
                            <p class="hint" style="margin-top:0;">Search featured dishes and select up to 3.</p>
                        </div>
                        <input id="productSearch" type="search" placeholder="Search dishes" value="<?= htmlspecialchars($search_product, ENT_QUOTES, 'UTF-8') ?>" style="width:260px; min-width:200px;">
                    </div>
                    <div class="grid" id="productGrid">
                        <?php while ($item = mysqli_fetch_assoc($products)): ?>
                            <?php $imgFile = $item['image'] ? (strpos($item['image'], '/') === false ? '/casa_gunita/assets/images/' . $item['image'] : $item['image']) : 'foodbg.png'; ?>
                            <label class="feature-card" data-title="<?= htmlspecialchars(strtolower($item['name']), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="checkbox" class="feature-checkbox feature-product" name="featured_products[]" value="<?= (int)$item['product_id'] ?>" <?= in_array($item['product_id'], $featured_products, true) ? 'checked' : '' ?> />
                                <img src="<?= htmlspecialchars($imgFile, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
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

    function initLiveSearch(inputId, gridId) {
        const input = document.getElementById(inputId);
        const grid = document.getElementById(gridId);
        if (!input || !grid) return;
        const update = () => {
            const query = input.value.trim().toLowerCase();
            const cards = grid.querySelectorAll('.feature-card');
            cards.forEach(card => {
                const title = card.dataset.title || '';
                card.style.display = title.includes(query) ? '' : 'none';
            });
        };
        input.addEventListener('input', update);
        update();
    }

    enforceLimit('.feature-category', 3);
    enforceLimit('.feature-product', 3);
    initLiveSearch('categorySearch', 'categoryGrid');
    initLiveSearch('productSearch', 'productGrid');
</script>

</body>
</html>
