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
$search_product = trim($_GET['search_product'] ?? '');
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if (isset($_GET['delete_product']) && ctype_digit((string)$_GET['delete_product'])) {
    $delete_product_id = (int)$_GET['delete_product'];
    // Get product name before delete
    $get_name = mysqli_prepare($conn, "SELECT name FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $delete_product_id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $prod_name = $name_result['name'] ?? 'Unknown';
    
    $delete = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($delete, 'i', $delete_product_id);
    mysqli_stmt_execute($delete);
    
    // Log audit: menu_delete
    $admin_id = $_SESSION['user_id'] ?? null;
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
    // Get category name before delete
    $get_name = mysqli_prepare($conn, "SELECT name FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $delete_category_id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $cat_name = $name_result['name'] ?? 'Unknown';
    
    $delete = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($delete, 'i', $delete_category_id);
    mysqli_stmt_execute($delete);
    
    // Log audit: category_delete
    $admin_id = $_SESSION['user_id'] ?? null;
    $audit_stmt = mysqli_prepare($conn,
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, category_id, details)
         VALUES (?, 'category_delete', 'category', ?, ?, ?)");
    $details = "Deleted category: $cat_name";
    mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $delete_category_id, $delete_category_id, $details);
    mysqli_stmt_execute($audit_stmt);
    
    header('Location: menu.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_action'])) {
    $category_action = $_POST['category_action'];
    $name = sanitize(trim($_POST['name'] ?? ''));
    $edit_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $category_image_name = '';
    $current_image = null;

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        if ($category_action === 'edit' && $edit_category_id > 0) {
            $check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE name = ? AND category_id != ?");
            mysqli_stmt_bind_param($check, 'si', $name, $edit_category_id);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) {
                $error = 'A category with that name already exists.';
            }
            if (!$error) {
                $img_stmt = mysqli_prepare($conn, "SELECT image FROM categories WHERE category_id = ?");
                mysqli_stmt_bind_param($img_stmt, 'i', $edit_category_id);
                mysqli_stmt_execute($img_stmt);
                $img_row = mysqli_stmt_get_result($img_stmt)->fetch_assoc();
                $current_image = $img_row['image'] ?? null;
            }
        } else {
            $check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE name = ?");
            mysqli_stmt_bind_param($check, 's', $name);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) {
                $error = 'A category with that name already exists.';
            }
        }
    }

    if (!$error && !empty($_FILES['category_image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION));
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
        if ($category_action === 'edit' && $edit_category_id > 0) {
            if ($category_image_name === '') {
                $category_image_name = $current_image;
            }
            $update = mysqli_prepare($conn, "UPDATE categories SET name = ?, image = ? WHERE category_id = ?");
            mysqli_stmt_bind_param($update, 'ssi', $name, $category_image_name, $edit_category_id);
            mysqli_stmt_execute($update);
            
            // Log audit: category_edit
            $admin_id = $_SESSION['user_id'] ?? null;
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
            // Log audit: category_add
            $admin_id = $_SESSION['user_id'] ?? null;
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
    if (!$selected_category) {
        $category_id = 0;
    }
}

$category_query = "SELECT * FROM categories";
$category_params = [];
if ($search_category !== '') {
    $category_query .= " WHERE name LIKE ?";
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
$count_result = mysqli_query($conn, "SELECT category_id, COUNT(*) AS count FROM products GROUP BY category_id");
while ($row = mysqli_fetch_assoc($count_result)) {
    $product_counts[$row['category_id']] = $row['count'];
}

$products = null;
if ($category_id > 0) {
    $product_query = "SELECT p.*, i.stock_quantity FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.category_id = ?";
    if ($search_product !== '') {
        $product_query .= " AND p.name LIKE ?";
    }
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
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --crimson:#210303; --gold:#e8d191; --ink:#130301; --surface:#fff8eb; --bg:#f4f2ea; --line:rgba(33,3,3,.1); --radius:14px; --shadow:0 2px 18px rgba(33,3,3,.08); --sidebar-w:220px; --header-h:64px; }
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar-w);background:var(--crimson);min-height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column;}
.sidebar-logo{padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,.12);}
.sidebar-logo .brand{font-family:'Cinzel Decorative',serif;font-size:17px;color:#fff;letter-spacing:.08em;text-transform:uppercase;};
.nav-list{list-style:none;padding:16px 12px;margin:0;flex:1;};
.nav-list li{margin-bottom:4px;}
.nav-list a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;}
.nav-list a.active,.nav-list a:hover{background:rgba(255,255,255,.14);color:#fff;}
.nav-list a .icon{width:20px;text-align:center;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,.12);}
.sidebar-footer a{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.65);text-decoration:none;}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:var(--header-h);background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:5;}
.topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--crimson);}.topbar-spacer{flex:1;}.topbar-user{display:flex;align-items:center;gap:10px;color:var(--ink);font-size:14px;}.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--crimson);color:#fff;font-weight:700;}
.content{padding:24px 28px;display:flex;flex-direction:column;gap:20px;}
.card{background:var(--surface);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);}
.top-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;border:none;padding:12px 18px;font-weight:700;text-decoration:none;cursor:pointer;}
.btn-blue{background:#3498db;color:#fff;}.btn-green{background:#27ae60;color:#fff;}.btn-gray{background:#6b7280;color:#fff;}.btn-red{background:#e74c3c;color:#fff;}
.input-group{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.input-group input{padding:12px 14px;border-radius:12px;border:1px solid #d6d2d9;min-width:240px;}
.folder-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;}
.folder-card{background:#fff;border-radius:20px;padding:22px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
.folder-card a.full-link{position:absolute;inset:0;z-index:1;text-decoration:none;}
.folder-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
.folder-name{font-size:18px;font-weight:700;}
.folder-meta{color:#6b7280;font-size:14px;}
.folder-actions{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:2;}
.small-btn{padding:8px 12px;border-radius:10px;border:none;cursor:pointer;font-size:13px;}
.small-btn-blue{background:#3498db;color:#fff;}.small-btn-red{background:#e74c3c;color:#fff;}.small-btn-gray{background:#f4f4f4;color:#333;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;padding:20px;z-index:20;}
.modal-overlay.active{display:flex;}
.modal{width:100%;max-width:520px;background:#fff;border-radius:18px;padding:24px;position:relative;}
.modal h2{margin:0 0 18px;font-family:'Cinzel Decorative',serif;font-size:1.75rem;}
.modal .form-group{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}
.modal label{font-weight:600;}.modal input{width:100%;border:1px solid #d6d2d9;border-radius:12px;padding:12px 14px;}.modal-close{position:absolute;top:18px;right:18px;background:#f4f4f4;border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;}
.alert{padding:14px 16px;border-radius:14px;}.alert-error{background:#fee2e2;color:#981b1b;}.alert-success{background:#d1fae5;color:#065f46;}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="menu.php" class="active"><span class="icon">🍽️</span> Menu</a></li>
        <li><a href="feature.php"><span class="icon">⭐</span> Feature</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php"><span class="icon">🚪</span> Logout</a></div>
</aside>
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Menu<?= $selected_category ? ' › ' . htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') : '' ?></div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user"><div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div><span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
    </header>
    <div class="content">
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <div class="card top-bar" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <?php if ($selected_category): ?>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <a href="menu.php" class="btn btn-gray">← Back to Categories</a>
                    <div style="font-weight:700;font-size:1.05rem;">Category: <?= htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <input id="menuSearch" type="search" name="search_product" placeholder="Search menu items in <?= htmlspecialchars($selected_category['name'], ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($search_product, ENT_QUOTES, 'UTF-8') ?>" class="input-group" style="min-width:240px;">
                    <a href="menu_add.php?category_id=<?= $selected_category['category_id'] ?>" class="btn btn-green">+ Add Menu Item</a>
                </div>
            <?php else: ?>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;"><div style="font-weight:700;font-size:1.05rem;">Categories</div></div>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <input id="categorySearch" type="search" name="search_category" placeholder="Search categories" value="<?= htmlspecialchars($search_category, ENT_QUOTES, 'UTF-8') ?>" class="input-group" style="min-width:240px;">
                    <button type="button" class="btn btn-green" onclick="openCategoryModal('add')">+ Add Category</button>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($selected_category): ?>
            <?php if (!$products || mysqli_num_rows($products) === 0): ?>
                <div class="card" style="text-align:center;color:#777;padding:60px 0;">No menu items found in this category.</div>
            <?php else: ?>
                <div class="folder-grid" id="productList">
                    <?php while ($p = mysqli_fetch_assoc($products)): ?>
                        <div class="folder-card">
                            <div class="folder-heading">
                                <div>
                                    <div class="folder-name"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="folder-meta"><?= formatPrice($p['price']) ?> · Stock: <?= $p['stock_quantity'] !== null ? (int)$p['stock_quantity'] : 'N/A' ?></div>
                                </div>
                                <?php if ($p['image']): ?>
                                    <img src="/casa_gunita/assets/images/<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>" style="width:72px;height:72px;object-fit:cover;border-radius:18px;">
                                <?php else: ?>
                                    <div style="width:72px;height:72px;border-radius:18px;background:#f0eee8;display:flex;align-items:center;justify-content:center;font-size:24px;color:#7c6a4b;">🍽️</div>
                                <?php endif; ?>
                            </div>
                            <div class="folder-actions">
                                <a href="menu_edit.php?id=<?= $p['product_id'] ?>" class="small-btn small-btn-blue">Edit</a>
                                <a href="menu.php?delete_product=<?= $p['product_id'] ?>&category_id=<?= $selected_category['category_id'] ?>" class="small-btn small-btn-red" onclick="return confirm('Delete this menu item?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if (mysqli_num_rows($categories_result) === 0): ?>
                <div class="card" style="text-align:center;color:#777;padding:60px 0;">No categories found. Add one to start organizing the menu.</div>
            <?php else: ?>
                <div class="folder-grid" id="categoryList">
                    <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                        <div class="folder-card">
                            <div class="folder-heading">
                                <div>
                                    <div class="folder-name"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="folder-meta"><?= ($product_counts[$cat['category_id']] ?? 0) ?> menu items</div>
                                </div>
                                <?php if (!empty($cat['image'])): ?>
                                    <img src="/casa_gunita/assets/images/<?= htmlspecialchars($cat['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>" style="width:72px;height:72px;object-fit:cover;border-radius:18px;">
                                <?php else: ?>
                                    <div style="width:72px;height:72px;border-radius:18px;background:#f0eee8;display:flex;align-items:center;justify-content:center;font-size:24px;color:#7c6a4b;">+</div>
                                <?php endif; ?>
                            </div>
                            <div class="folder-actions">
                                <button type="button" class="small-btn small-btn-blue" onclick='openCategoryModal("edit", <?= $cat['category_id'] ?>, <?= json_encode($cat['name']) ?>)'>Edit</button>
                                <a href="menu.php?delete_category=<?= $cat['category_id'] ?>" class="small-btn small-btn-red" onclick="return confirm('Delete this category?')">Delete</a>
                            </div>
                            <a class="full-link" href="menu.php?category_id=<?= $cat['category_id'] ?>" aria-label="Open category"></a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <button type="button" class="modal-close" onclick="closeCategoryModal()">✕</button>
        <h2 id="modalTitle">Add Category</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_action" id="categoryAction" value="add">
            <input type="hidden" name="category_id" id="categoryId" value="0">
            <div class="form-group">
                <label for="categoryName">Category Name</label>
                <input type="text" name="name" id="categoryName" required>
            </div>
            <div class="form-group">
                <label for="categoryImage">Category Image</label>
                <input type="file" name="category_image" id="categoryImage" accept="image/*">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-top:10px;">
                <button type="button" class="btn btn-gray" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-green">Save Category</button>
            </div>
        </form>
    </div>
</div>
<script>
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

function initLiveSearch(inputId, listId) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    if (!input || !list) return;
    const update = () => {
        const query = input.value.trim().toLowerCase();
        const cards = list.querySelectorAll('.folder-card');
        cards.forEach(card => {
            const title = card.querySelector('.folder-name')?.textContent.toLowerCase() || '';
            card.style.display = title.includes(query) ? '' : 'none';
        });
    };
    input.addEventListener('input', update);
    update();
}

initLiveSearch('categorySearch', 'categoryList');
initLiveSearch('menuSearch', 'productList');

window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeCategoryModal();
});
</script>
</body>
</html>
