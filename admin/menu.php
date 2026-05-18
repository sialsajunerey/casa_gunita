<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db_admin.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$error = '';
$success = '';
$search_category = trim($_GET['search_category'] ?? '');
$search_product  = trim($_GET['search_product']  ?? '');
$category_id     = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (isset($_GET['delete_product']) && ctype_digit((string)$_GET['delete_product'])) {
    $delete_product_id = (int)$_GET['delete_product'];
    $get_name = mysqli_prepare($conn, "SELECT name FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $delete_product_id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $prod_name = $name_result['name'] ?? 'Unknown';

    $delete = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($delete, 'i', $delete_product_id);
    mysqli_stmt_execute($delete);

    $admin_id   = $_SESSION['user_id'] ?? null;
    $audit_stmt = mysqli_prepare($conn,
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, product_id, details)
         VALUES (?, 'menu_delete', 'product', ?, ?, ?)");
    $details = "Deleted product: $prod_name";
    mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $delete_product_id, $delete_product_id, $details);
    mysqli_stmt_execute($audit_stmt);

    header('Location: menu.php' . ($category_id ? '?category_id=' . $category_id : ''));
    exit();
}

if (isset($_GET['delete_category']) && ctype_digit((string)$_GET['delete_category'])) {
    $delete_category_id = (int)$_GET['delete_category'];
    $get_name = mysqli_prepare($conn, "SELECT name FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $delete_category_id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $cat_name = $name_result['name'] ?? 'Unknown';

    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM products WHERE category_id = ?");
    mysqli_stmt_bind_param($count_stmt, 'i', $delete_category_id);
    mysqli_stmt_execute($count_stmt);
    $product_total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);

    if ($product_total > 0) {
        $error = "Cannot delete category \"$cat_name\" because it still has $product_total menu item" . ($product_total === 1 ? '' : 's') . ". Remove or move the menu items first.";
    } else {
        $delete = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
        mysqli_stmt_bind_param($delete, 'i', $delete_category_id);
        mysqli_stmt_execute($delete);

        $admin_id   = $_SESSION['user_id'] ?? null;
        $audit_stmt = mysqli_prepare($conn,
            "INSERT INTO audit_logs (admin_id, action, target_type, target_id, category_id, details)
             VALUES (?, 'category_delete', 'category', ?, ?, ?)");
        $details = "Deleted category: $cat_name";
        mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $delete_category_id, $delete_category_id, $details);
        mysqli_stmt_execute($audit_stmt);

        header('Location: menu.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    $category_action  = $_POST['category_action'];
    $name             = sanitize(trim($_POST['name'] ?? ''));
    $edit_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_image_name = '';
    $current_image    = null;

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        if ($category_action === 'edit' && $edit_category_id > 0) {
            $check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
            mysqli_stmt_bind_param($check, 'si', $name, $edit_category_id);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) $error = 'A category with that name already exists.';
            if (!$error) {
                $img_stmt = mysqli_prepare($conn, "SELECT image FROM categories WHERE category_id = ?");
                mysqli_stmt_bind_param($img_stmt, 'i', $edit_category_id);
                mysqli_stmt_execute($img_stmt);
                $img_row       = mysqli_stmt_get_result($img_stmt)->fetch_assoc();
                $current_image = $img_row['image'] ?? null;
            }
        } else {
            $check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE name = ?");
            mysqli_stmt_bind_param($check, 's', $name);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) $error = 'A category with that name already exists.';
        }
    }

    if (!$error && !empty($_FILES['category_image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'Only JPG, PNG, and WEBP images are allowed.';
        } else {
            $category_image_name = time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = __DIR__ . '/../assets/images/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $error = 'Unable to create upload directory.';
            } elseif (!move_uploaded_file($_FILES['category_image']['tmp_name'], $upload_dir . $category_image_name)) {
                $error = 'Failed to upload category image.';
            }
        }
    }

    if (!$error) {
        $admin_id = $_SESSION['user_id'] ?? null;
        if ($category_action === 'edit' && $edit_category_id > 0) {
            if ($category_image_name === '') $category_image_name = $current_image;
            $update = mysqli_prepare($conn, "UPDATE categories SET name = ?, image = ? WHERE category_id = ?");
            mysqli_stmt_bind_param($update, 'ssi', $name, $category_image_name, $edit_category_id);
            mysqli_stmt_execute($update);

            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, category_id, details)
                 VALUES (?, 'category_edit', 'category', ?, ?, ?)");
            $details = "Updated category: $name";
            mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $edit_category_id, $edit_category_id, $details);
            mysqli_stmt_execute($audit_stmt);
            $success = 'Category updated successfully.';
        } else {
            $insert = mysqli_prepare($conn, "INSERT INTO categories (name, image) VALUES (?, ?)");
            mysqli_stmt_bind_param($insert, 'ss', $name, $category_image_name);
            mysqli_stmt_execute($insert);
            $category_id_new = mysqli_insert_id($conn);

            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, category_id, details)
                 VALUES (?, 'category_add', 'category', ?, ?, ?)");
            $details = "Added category: $name";
            mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $category_id_new, $category_id_new, $details);
            mysqli_stmt_execute($audit_stmt);
            $success = 'Category added successfully.';
        }
    }
}

$selected_category = null;
if ($category_id > 0) {
    $cat_stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($cat_stmt, 'i', $category_id);
    mysqli_stmt_execute($cat_stmt);
    $selected_category = mysqli_stmt_get_result($cat_stmt)->fetch_assoc();
    if (!$selected_category) $category_id = 0;
}

$category_query  = "SELECT * FROM categories";
$category_params = [];
if ($search_category !== '') {
    $category_query   .= " WHERE name LIKE ?";
    $category_params[] = '%' . $search_category . '%';
}
$category_query .= " ORDER BY name";
if (!empty($category_params)) {
    $stmt = mysqli_prepare($conn, $category_query);
    mysqli_stmt_bind_param($stmt, 's', $category_params[0]);
    mysqli_stmt_execute($stmt);
    $categories_result = mysqli_stmt_get_result($stmt);
} else {
    $categories_result = mysqli_query($conn, $category_query);
}

$product_counts = [];
$count_result   = mysqli_query($conn, "SELECT category_id, COUNT(*) AS count FROM products GROUP BY category_id");
while ($row = mysqli_fetch_assoc($count_result)) {
    $product_counts[$row['category_id']] = $row['count'];
}

$products = null;
if ($category_id > 0) {
    $product_query = "SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.category_id = ?";
    if ($search_product !== '') $product_query .= " AND p.name LIKE ?";
    $product_query .= " ORDER BY p.name";
    $stmt = mysqli_prepare($conn, $product_query);
    if ($search_product !== '') {
        $like_product = '%' . $search_product . '%';
        mysqli_stmt_bind_param($stmt, 'is', $category_id, $like_product);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $category_id);
    }
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu — Casa Gunita Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="menuphp.css">
<style>
/* ══════════════════════════════════════
   HAMBURGER + COLLAPSIBLE SIDEBAR
══════════════════════════════════════ */
.hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 36px;
    height: 36px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: background 0.2s;
    flex-shrink: 0;
}
.hamburger:hover { background: rgba(33,3,3,0.08); }
.hamburger span {
    display: block;
    height: 2px;
    background: #210303;
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: center;
    width: 100%;
}
.hamburger span:nth-child(2) { width: 70%; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 49;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.visible {
    opacity: 1;
    pointer-events: all;
}

.sidebar {
    transition: transform 0.3s ease;
    will-change: transform;
}
.sidebar.collapsed { transform: translateX(-100%); }
.main { transition: margin-left 0.3s ease; }
.main.expanded { margin-left: 0 !important; }

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 50;
    }
    .sidebar.open { transform: translateX(0); }
    .main,
    .main.expanded { margin-left: 0 !important; }
    .topbar { padding: 0 16px; gap: 12px; }
    .topbar-title { font-size: 0.95rem; }
    .content { padding: 16px; }
    .top-bar { flex-direction: column; align-items: stretch; gap: 10px; }
    .top-bar-right { flex-wrap: wrap; }
    .folder-grid { grid-template-columns: 1fr !important; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php" class="active">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="customizations.php">Customizations</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main" id="main">
    <header class="topbar">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="topbar-title">
            Menu<?= $selected_category ? ' › ' . htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Top Bar -->
        <div class="card top-bar">
            <div class="top-bar-left">
                <?php if ($selected_category): ?>
                    <a href="menu.php" class="btn btn-gray">Back to Categories</a>
                    <span class="top-bar-title">
                        <?= htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php else: ?>
                    <span class="top-bar-title">Categories</span>
                <?php endif; ?>
            </div>
            <div class="top-bar-right">
                <?php if ($selected_category): ?>
                    <input id="menuSearch" type="search" name="search_product"
                           placeholder="Search items in <?= htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars($search_product, ENT_QUOTES, 'UTF-8') ?>"
                           class="input-group">
                    <a href="menu_add.php?category_id=<?= $selected_category['category_id'] ?>" class="btn btn-green">Add Menu Item</a>
                <?php else: ?>
                    <input id="categorySearch" type="search" name="search_category"
                           placeholder="Search Categories"
                           value="<?= htmlspecialchars($search_category, ENT_QUOTES, 'UTF-8') ?>"
                           class="input-group">
                    <button type="button" class="btn btn-green" onclick="openCategoryModal('add')">Add Category</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grid -->
        <?php if ($selected_category): ?>
            <?php if (!$products || mysqli_num_rows($products) === 0): ?>
                <div class="empty-state">No menu items found in this category.</div>
            <?php else: ?>
                <div class="folder-grid" id="productList">
                    <?php while ($p = mysqli_fetch_assoc($products)): ?>
                    <div class="folder-card">
                        <div class="folder-heading">
                            <div>
                                <div class="folder-name">
                                    <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="folder-meta">
                                    <?= formatPrice($p['price']) ?> &middot;
                                    Stock: <?= $p['stock_quantity'] !== null ? (int)$p['stock_quantity'] : 'N/A' ?>
                                </div>
                            </div>
                            <?php if ($p['image']): ?>
                                <img class="folder-img"
                                     src="/casa_gunita/assets/images/<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <div class="folder-img-placeholder">—</div>
                            <?php endif; ?>
                        </div>
                        <div class="folder-actions">
                            <a href="menu_edit.php?id=<?= $p['product_id'] ?>" class="small-btn small-btn-blue">Edit</a>
                            <a href="menu.php?delete_product=<?= $p['product_id'] ?>&category_id=<?= $selected_category['category_id'] ?>"
                               class="small-btn small-btn-red"
                               onclick="return confirm('Delete this menu item?')">Delete</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <?php if (mysqli_num_rows($categories_result) === 0): ?>
                <div class="empty-state">No categories found. Add one to start organizing the menu.</div>
            <?php else: ?>
                <div class="folder-grid" id="categoryList">
                    <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                    <?php $category_item_count = (int)($product_counts[$cat['category_id']] ?? 0); ?>
                    <div class="folder-card">
                        <div class="folder-heading">
                            <div>
                                <div class="folder-name">
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="folder-meta">
                                    <?= $category_item_count ?> menu item<?= $category_item_count === 1 ? '' : 's' ?>
                                </div>
                            </div>
                            <?php if (!empty($cat['image'])): ?>
                                <img class="folder-img"
                                     src="/casa_gunita/assets/images/<?= htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <div class="folder-img-placeholder">+</div>
                            <?php endif; ?>
                        </div>
                        <div class="folder-actions">
                            <button type="button" class="small-btn small-btn-blue"
                                    onclick='openCategoryModal("edit", <?= $cat['category_id'] ?>, <?= json_encode($cat['name']) ?>)'>
                                Edit
                            </button>
                            <?php if ($category_item_count > 0): ?>
                                <button type="button"
                                        class="small-btn small-btn-red"
                                        onclick="alert('This category cannot be deleted because it still has <?= $category_item_count ?> menu item<?= $category_item_count === 1 ? '' : 's' ?>. Remove or move the menu items first.')">
                                    Delete
                                </button>
                            <?php else: ?>
                            <a href="menu.php?delete_category=<?= $cat['category_id'] ?>"
                               class="small-btn small-btn-red"
                               onclick="return confirm('Delete this category?')">Delete</a>
                            <?php endif; ?>
                        </div>
                        <a class="full-link" href="menu.php?category_id=<?= $cat['category_id'] ?>" aria-label="Open category"></a>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div><!-- .content -->
</div><!-- .main -->

<!-- Category Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <button type="button" class="modal-close" onclick="closeCategoryModal()">✕</button>
        <h2 id="modalTitle">Add Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_action" id="categoryAction" value="add">
            <input type="hidden" name="category_id"     id="categoryId"     value="0">
            <div class="form-group">
                <label for="categoryName">Category Name</label>
                <input type="text" name="name" id="categoryName" required>
            </div>
            <div class="form-group">
                <label for="categoryImage">Category Image</label>
                <input type="file" name="category_image" id="categoryImage" accept="image/*">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-green">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Category Modal ── */
function openCategoryModal(action, id = 0, name = '') {
    document.getElementById('categoryModal').classList.add('active');
    document.getElementById('categoryAction').value = action;
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit Category' : 'Add Category';
}
function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}

/* ── Live Search ── */
function initLiveSearch(inputId, listId) {
    const input = document.getElementById(inputId);
    const list  = document.getElementById(listId);
    if (!input || !list) return;
    const update = () => {
        const query = input.value.trim().toLowerCase();
        list.querySelectorAll('.folder-card').forEach(card => {
            const title = card.querySelector('.folder-name')?.textContent.toLowerCase() || '';
            card.style.display = title.includes(query) ? '' : 'none';
        });
    };
    input.addEventListener('input', update);
    update();
}
initLiveSearch('categorySearch', 'categoryList');
initLiveSearch('menuSearch', 'productList');

window.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeCategoryModal();
});

/* ══════════════════════════════════════
   HAMBURGER — all screen sizes
══════════════════════════════════════ */
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const mainEl         = document.getElementById('main');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const isMobile = () => window.innerWidth <= 768;

function openSidebar() {
    hamburgerBtn.classList.add('open');
    if (isMobile()) {
        sidebar.classList.add('open');
        sidebar.classList.remove('collapsed');
        sidebarOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
    }
    localStorage.setItem('sidebarOpen', '1');
}

function closeSidebar() {
    hamburgerBtn.classList.remove('open');
    if (isMobile()) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('collapsed');
        mainEl.classList.add('expanded');
    }
    localStorage.setItem('sidebarOpen', '0');
}

function toggleSidebar() {
    const desktopOpen = !isMobile() && !sidebar.classList.contains('collapsed');
    const mobileOpen  =  isMobile() &&  sidebar.classList.contains('open');
    (desktopOpen || mobileOpen) ? closeSidebar() : openSidebar();
}

(function init() {
    const saved = localStorage.getItem('sidebarOpen');
    if (isMobile()) {
        sidebar.classList.remove('open');
        mainEl.classList.remove('expanded');
    } else {
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    }
})();

hamburgerBtn.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeSidebar();
        closeCategoryModal();
    }
});

window.addEventListener('resize', () => {
    if (!isMobile()) {
        sidebarOverlay.classList.remove('visible');
        sidebar.classList.remove('open');
        document.body.style.overflow = '';
        const saved = localStorage.getItem('sidebarOpen');
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
        mainEl.style.marginLeft = '';
    }
});
</script>

</body>
</html>