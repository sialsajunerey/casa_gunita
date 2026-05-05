<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$error   = '';
$success = '';

$id = (int)$_GET['id'];

// Fetch existing product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    echo "Menu item not found.";
    exit();
}

// Fetch categories
$categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}

// Load available modifier groups
$modifierGroups = [];
$modifierResult = mysqli_query($conn, "SELECT modifier_group_id, name, pricing_type, select_option FROM modifier_groups ORDER BY name");
while ($modifier = mysqli_fetch_assoc($modifierResult)) {
    $modifierGroups[] = $modifier;
}
$modifierMap = [];
foreach ($modifierGroups as $modifier) {
    $modifierMap[$modifier['modifier_group_id']] = $modifier;
}

// Fetch current stock
$inv_stmt = mysqli_prepare($conn,
    "SELECT * FROM inventory WHERE product_id = ?");
mysqli_stmt_bind_param($inv_stmt, 'i', $id);
mysqli_stmt_execute($inv_stmt);
$inventory = mysqli_fetch_assoc(mysqli_stmt_get_result($inv_stmt));

// Load existing customization groups and options
$existing_groups = [];
$groupStmt = mysqli_prepare($conn,
    "SELECT group_id, name, group_type, is_required
     FROM product_customization_groups
     WHERE product_id = ?
     ORDER BY display_order, group_id");
mysqli_stmt_bind_param($groupStmt, 'i', $id);
mysqli_stmt_execute($groupStmt);
$groupResult = mysqli_stmt_get_result($groupStmt);
while ($group = mysqli_fetch_assoc($groupResult)) {
    $existing_groups[$group['group_id']] = $group;
    $existing_groups[$group['group_id']]['options'] = [];
}
if (!empty($existing_groups)) {
    $groupIds = array_keys($existing_groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT option_id, group_id, name, additional_price, image
            FROM product_customization_options
            WHERE group_id IN ($placeholders)
            ORDER BY display_order, option_id";
    $optStmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('i', count($groupIds));
    $refs = [];
    foreach ($groupIds as $idx => $gid) {
        $refs[$idx] = &$groupIds[$idx];
    }
    array_unshift($refs, $types);
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$optStmt], $refs));
    mysqli_stmt_execute($optStmt);
    $optionResult = mysqli_stmt_get_result($optStmt);
    while ($option = mysqli_fetch_assoc($optionResult)) {
        $existing_groups[$option['group_id']]['options'][] = $option;
    }
}

$existing_modifier_links = [];
$linkStmt = mysqli_prepare($conn,
    "SELECT modifier_group_id, is_required
     FROM product_modifier_groups
     WHERE product_id = ?
     ORDER BY display_order");
mysqli_stmt_bind_param($linkStmt, 'i', $id);
mysqli_stmt_execute($linkStmt);
$linkResult = mysqli_stmt_get_result($linkStmt);
while ($link = mysqli_fetch_assoc($linkResult)) {
    $existing_modifier_links[] = $link;
}

$posted_group_names = $_POST['group_name'] ?? [];
$posted_group_types = $_POST['group_type'] ?? [];
$posted_group_required = $_POST['group_required'] ?? [];
$posted_group_modifier_ids = $_POST['group_modifier_id'] ?? [];
$posted_group_pricing_type = $_POST['group_pricing_type'] ?? [];
$posted_option_names = $_POST['option_name'] ?? [];
$posted_option_prices = $_POST['option_price'] ?? [];
$posted_option_images = $_POST['option_image_existing'] ?? [];

function get_nested_file(array $files, int $groupIndex, int $optionIndex) {
    if (empty($files['tmp_name'][$groupIndex][$optionIndex])) {
        return null;
    }
    return [
        'name' => $files['name'][$groupIndex][$optionIndex] ?? '',
        'tmp_name' => $files['tmp_name'][$groupIndex][$optionIndex],
        'error' => $files['error'][$groupIndex][$optionIndex] ?? UPLOAD_ERR_NO_FILE,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name']);
    $description  = trim($_POST['description'] ?? '');
    $price        = (float)$_POST['price'];
    $category_id  = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $stock        = (int)$_POST['stock_quantity'];
    $image_name   = $product['image']; // keep old image by default

    // Handle new image upload
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, WEBP images allowed.";
        } else {
            $image_name  = time() . '_' . basename($_FILES['image']['name']);
            $upload_dir  = __DIR__ . '/../assets/images/';
            $upload_path = $upload_dir . $image_name;

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $error = "Unable to create upload folder.";
            } elseif (!is_uploaded_file($_FILES['image']['tmp_name'])) {
                $error = "Uploaded file is invalid.";
            } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $error = "Failed to save uploaded image.";
            }
        }
    }

    if (!$error) {
        // Update product
        $stmt = mysqli_prepare($conn,
            "UPDATE products 
             SET name=?, description=?, price=?, category_id=?, image=?, is_available=?
             WHERE product_id=?");
        mysqli_stmt_bind_param($stmt, 'ssdisii',
            $name, $description, $price, $category_id, $image_name, $is_available, $id);

        if (mysqli_stmt_execute($stmt)) {
            // Update inventory stock
            $inv_update = mysqli_prepare($conn,
                "UPDATE inventory SET stock_quantity=? WHERE product_id=?");
            mysqli_stmt_bind_param($inv_update, 'ii', $stock, $id);
            mysqli_stmt_execute($inv_update);

            // Log audit: menu_edit
            $admin_id = $_SESSION['user_id'] ?? null;
            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, product_id, details)
                 VALUES (?, 'menu_edit', 'product', ?, ?, ?)");
            $details = "Updated product: $name (Price: ₱" . number_format($price, 2) . ")";
            mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $id, $id, $details);
            mysqli_stmt_execute($audit_stmt);

            // Remove old customization groups/options
            $deleteOptions = mysqli_prepare($conn,
                "DELETE o FROM product_customization_options o
                 JOIN product_customization_groups g ON o.group_id = g.group_id
                 WHERE g.product_id = ?");
            mysqli_stmt_bind_param($deleteOptions, 'i', $id);
            mysqli_stmt_execute($deleteOptions);

            $deleteGroups = mysqli_prepare($conn,
                "DELETE FROM product_customization_groups WHERE product_id = ?");
            mysqli_stmt_bind_param($deleteGroups, 'i', $id);
            mysqli_stmt_execute($deleteGroups);

            $deleteModifierLinks = mysqli_prepare($conn,
                "DELETE FROM product_modifier_groups WHERE product_id = ?");
            mysqli_stmt_bind_param($deleteModifierLinks, 'i', $id);
            mysqli_stmt_execute($deleteModifierLinks);

            // Save new customization groups, options, and modifier links
            if (!empty($posted_group_names) && is_array($posted_group_names)) {
                $groupOrder = 0;
                $groupStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_groups
                     (product_id, name, group_type, is_required, display_order)
                     VALUES (?, ?, ?, ?, ?)");
                $groupOptionStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_options
                     (group_id, name, additional_price, image, display_order)
                     VALUES (?, ?, ?, ?, ?)");
                $modifierLinkStmt = mysqli_prepare($conn,
                    "INSERT INTO product_modifier_groups
                     (product_id, modifier_group_id, is_required, display_order)
                     VALUES (?, ?, ?, ?)");

                foreach ($posted_group_names as $groupIndex => $groupNameRaw) {
                    $groupName = sanitize(trim($groupNameRaw));
                    if ($groupName === '') {
                        continue;
                    }
                    $groupType = in_array($posted_group_types[$groupIndex] ?? '', ['single', 'addon'], true)
                        ? $posted_group_types[$groupIndex]
                        : 'single';
                    $groupRequired = isset($posted_group_required[$groupIndex]) ? 1 : 0;

                    mysqli_stmt_bind_param($groupStmt, 'issii',
                        $id, $groupName, $groupType, $groupRequired, $groupOrder);
                    mysqli_stmt_execute($groupStmt);
                    $groupId = mysqli_insert_id($conn);

                    $modifierGroupId = isset($posted_group_modifier_ids[$groupIndex]) ? (int)$posted_group_modifier_ids[$groupIndex] : 0;
                    if ($modifierGroupId > 0) {
                        mysqli_stmt_bind_param($modifierLinkStmt, 'iiii', $id, $modifierGroupId, $groupRequired, $groupOrder);
                        mysqli_stmt_execute($modifierLinkStmt);
                    }

                    $optionOrder = 0;
                    if (!empty($posted_option_names[$groupIndex]) && is_array($posted_option_names[$groupIndex])) {
                        foreach ($posted_option_names[$groupIndex] as $optionIndex => $optionNameRaw) {
                            $optionName = sanitize($optionNameRaw);
                            if ($optionName === '') {
                                continue;
                            }
                            $optionPrice = isset($posted_option_prices[$groupIndex][$optionIndex])
                                ? (float)$posted_option_prices[$groupIndex][$optionIndex]
                                : 0.00;

                            $optionImageName = '';
                            $uploadedFile = get_nested_file($_FILES['option_image_file'] ?? [], $groupIndex, $optionIndex);
                            if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK && is_uploaded_file($uploadedFile['tmp_name'])) {
                                $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                    $optionImageName = time() . '_' . uniqid() . '.' . $ext;
                                    $uploadDir = __DIR__ . '/../assets/images/';
                                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                                        $error = 'Unable to create upload folder for option images.';
                                    } elseif (!move_uploaded_file($uploadedFile['tmp_name'], $uploadDir . $optionImageName)) {
                                        $error = 'Failed to save option image.';
                                    }
                                }
                            } elseif (!empty($posted_option_images[$groupIndex][$optionIndex])) {
                                $optionImageName = sanitize($posted_option_images[$groupIndex][$optionIndex]);
                            }

                            mysqli_stmt_bind_param($groupOptionStmt, 'isdsi',
                                $groupId, $optionName, $optionPrice, $optionImageName, $optionOrder);
                            mysqli_stmt_execute($groupOptionStmt);
                            $optionOrder++;
                        }
                    }
                    $groupOrder++;
                }
            }

            header('Location: menu.php');
            exit();
        } else {
            $error = "Failed to update menu item.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Item — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --crimson: #210303;
            --crimson-d: #130301;
            --crimson-l: #f7e3c6;
            --gold: #e8d191;
            --ink: #130301;
            --muted: #674328;
            --line: rgba(33,3,3,.1);
            --surface: #fff8eb;
            --bg: #f4f2ea;
            --sidebar-w: 220px;
            --header-h: 64px;
            --radius: 14px;
            --shadow: 0 2px 18px rgba(33,3,3,.08);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; display: flex; }
        .sidebar { width: var(--sidebar-w); background: var(--crimson); min-height: 100vh; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; }
        .sidebar-logo { padding: 22px 20px 18px; border-bottom: 1px solid rgba(255,255,255,.12); }
        .sidebar-logo .brand { font-family: 'Cinzel Decorative', serif; font-size: 18px; color: #fff; letter-spacing: 0.08em; text-transform: uppercase; }
        .sidebar-logo .sub { font-size: 11px; color: rgba(255,255,255,.55); margin-top: 4px; letter-spacing: .5px; }
        .nav-list { list-style: none; padding: 16px 12px; flex: 1; }
        .nav-list li { margin-bottom: 4px; }
        .nav-list a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,.75); text-decoration: none; font-size: 14px; font-weight: 500; transition: background .15s, color .15s; }
        .nav-list a:hover, .nav-list a.active { background: rgba(255,255,255,.14); color: #fff; }
        .nav-list a .icon { font-size: 16px; width: 20px; text-align: center; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,.12); }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,.65); text-decoration: none; font-size: 14px; transition: background .15s, color .15s; }
        .sidebar-footer a:hover { background: rgba(255,255,255,.1); color: #fff; }
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--line); display: flex; align-items: center; padding: 0 28px; gap: 16px; position: sticky; top: 0; z-index: 50; }
        .topbar-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--crimson); white-space: nowrap; }
        .topbar-spacer { flex: 1; }
        .topbar-user { display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 500; color: var(--ink); }
        .avatar { width: 34px; height: 34px; background: var(--crimson); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; }
        .content { padding: 24px 28px; display: flex; flex-direction: column; gap: 22px; flex: 1; }
        .card { background: var(--surface); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
        .page-header { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
        .page-header h2 { font-family: 'Cinzel Decorative', serif; font-size: 2.2rem; color: var(--crimson); margin:0; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px; }
        .form-group { display:flex; flex-direction:column; gap:8px; }
        label { font-weight:600; color: var(--ink); }
        input[type=text], input[type=number], textarea, select, input[type=file] { width:100%; border:1px solid #d6d2d9; border-radius:12px; background:#fff; color:var(--ink); padding:12px 14px; font-size:0.95rem; }
        textarea { min-height:110px; resize:vertical; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:12px 20px; border:none; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn-primary { background: var(--crimson); color:#fff; }
        .btn-gray { background:#6b7280; color:#fff; }
        .alert { padding:14px 16px; border-radius:14px; margin-bottom:20px; }
        .alert-error { background:#fee2e2; color:#981b1b; }
        .alert-success { background:#d1fae5; color:#065f46; }
        .current-img { margin-bottom:10px; }
        .current-img img { width:100px; height:100px; object-fit:cover; border-radius:12px; border:2px solid #d6d2d9; }
        .customization-card { border:1px solid #d6d2d9; border-radius:16px; background:#faf8f5; padding:18px; }
        .options-list { display:flex; flex-direction:column; gap:14px; }
        .option-item { display:grid; grid-template-columns:1.5fr 1fr minmax(120px,180px) auto; gap:12px; align-items:start; padding:14px; border:1px dashed #d6d2d9; border-radius:14px; background:#fff; }
        .customization-footer { font-size:0.95rem; color:#6b7280; margin-top:6px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="menu.php" class="active"><span class="icon">�️</span> Menu</a></li>
        <li><a href="feature.php"><span class="icon">⭐</span> Feature</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Edit Menu Item</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <main class="content">
        <div class="card">
            <div class="page-header">
                <h2>Edit Menu Item</h2>
            <a href="menu.php" class="btn btn-primary">← Back to Menu</a>

            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-grid">

        <div class="form-group">
            <label>Dish Name</label>
            <input type="text" name="name"
                   value="<?= htmlspecialchars($name ?? $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($description ?? $product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <label>Price (₱)</label>
            <input type="number" name="price" step="0.01"
                   value="<?= htmlspecialchars($price ?? $product['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category_id">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['category_id'] ?>"
                        <?= (isset($category_id) ? $category_id : (int)$product['category_id']) === (int)$cat['category_id'] ? 'selected' : '' ?> >
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Stock Quantity</label>
            <input type="number" name="stock_quantity"
                   value="<?= htmlspecialchars(isset($stock) ? $stock : ($inventory['stock_quantity'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Dish Image</label>
            <?php if ($product['image']): ?>
            <div class="current-img">
                <p style="font-size:12px; color:#666;">Current image:</p>
                <img src="/casa_gunita/assets/images/<?= $product['image'] ?>"
                     alt="current">
            </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
            <small style="color:#999;">Leave blank to keep current image</small>
        </div>

        <div class="form-group" style="grid-column: span 2;">
            <label>Customization</label>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                <?php if (empty($modifierGroups)): ?>
                    <p class="customization-footer">No modifier groups available. Create them first in the Modifiers admin page.</p>
                <?php else: ?>
                    <select id="new-modifier-group" style="width:100%;max-width:320px;padding:10px;border:1px solid #ddd;border-radius:8px;background:#fff">
                        <option value="">Add modifier group...</option>
                        <?php foreach ($modifierGroups as $modifier): ?>
                            <option value="<?= (int)$modifier['modifier_group_id'] ?>"
                                    data-name="<?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-pricing="<?= htmlspecialchars($modifier['pricing_type'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-select="<?= htmlspecialchars($modifier['select_option'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div id="customization-groups">
                <?php
                $renderGroups = !empty($posted_group_names) ? $posted_group_names : [];
                if (empty($renderGroups) && !empty($existing_groups)) {
                    foreach ($existing_groups as $group) {
                        $renderGroups[] = $group['group_type'] === 'addon' ? 'addon' : 'single';
                    }
                }
                if (!empty($renderGroups)):
                    foreach ($renderGroups as $groupIndex => $groupName):
                        $groupLabel = sanitize(trim($groupName));
                        $groupKey = array_keys($existing_groups)[$groupIndex] ?? null;
                        $existingGroup = $groupKey !== null ? $existing_groups[$groupKey] : null;
                        $groupTypeValue = $posted_group_types[$groupIndex] ?? null;
                        if (!in_array($groupTypeValue, ['addon', 'single'], true)) {
                            if ($existingGroup) {
                                $groupTypeValue = $existingGroup['group_type'] === 'addon' ? 'addon' : 'single';
                            } else {
                                $existingModifier = $existing_modifier_links[$groupIndex] ?? null;
                                $modifierGroupId = $existingModifier['modifier_group_id'] ?? null;
                                $modifierMeta = $modifierGroupId && isset($modifierMap[$modifierGroupId]) ? $modifierMap[$modifierGroupId] : null;
                                if ($modifierMeta) {
                                    $groupTypeValue = $modifierMeta['select_option'] === 'multiple' ? 'addon' : 'single';
                                } else {
                                    $groupTypeValue = in_array($groupLabel, ['addon', 'size', 'flavor'], true)
                                        ? ($groupLabel === 'addon' ? 'addon' : 'single')
                                        : 'single';
                                }
                            }
                        }
                        $groupRequiredValue = isset($posted_group_required[$groupIndex]) ? true : ($existingGroup ? (bool)$existingGroup['is_required'] : false);
                        $modifierGroupId = $posted_group_modifier_ids[$groupIndex] ?? null;
                        if (!$modifierGroupId && isset($existing_modifier_links[$groupIndex])) {
                            $modifierGroupId = $existing_modifier_links[$groupIndex]['modifier_group_id'];
                        }
                        $modifierGroupId = $modifierGroupId ? (int)$modifierGroupId : '';
                        $modifierMeta = $modifierGroupId && isset($modifierMap[$modifierGroupId]) ? $modifierMap[$modifierGroupId] : null;
                        if ($modifierMeta) {
                            $groupLabel = $posted_group_names[$groupIndex] ?? $modifierMeta['name'];
                            if (!in_array($groupTypeValue, ['addon', 'single'], true)) {
                                $groupTypeValue = $modifierMeta['select_option'] === 'multiple' ? 'addon' : 'single';
                            }
                        }
                        if ($groupLabel === '' && $existingGroup) {
                            $groupLabel = $existingGroup['name'];
                        }
                        if ($groupLabel === '') {
                            $groupLabel = $modifierMeta ? $modifierMeta['name'] : ($groupTypeValue === 'addon' ? 'Add-ons' : 'Options');
                        }
                        $pricingType = $posted_group_pricing_type[$groupIndex] ?? ($modifierMeta['pricing_type'] ?? 'set_price');
                        $optionNames = $posted_option_names[$groupIndex] ?? [];
                        $optionPrices = $posted_option_prices[$groupIndex] ?? [];
                        $optionImages = $posted_option_images[$groupIndex] ?? [];
                        if (empty($optionNames) && !empty($existing_groups)) {
                            $groupKey = array_keys($existing_groups)[$groupIndex] ?? null;
                            if ($groupKey !== null) {
                                foreach ($existing_groups[$groupKey]['options'] as $opt) {
                                    $optionNames[] = $opt['name'];
                                    $optionPrices[] = $opt['additional_price'];
                                    $optionImages[] = $opt['image'] ?? '';
                                }
                            }
                        }
                ?>
                        <div class="customization-card" data-index="<?= (int)$groupIndex ?>" data-modifier-id="<?= htmlspecialchars($modifierGroupId, ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($groupTypeValue, ENT_QUOTES, 'UTF-8') ?>" style="border:1px solid #ddd;border-radius:12px;padding:16px;margin-bottom:14px;background:#fcfcfc">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                                <div style="display:flex;align-items:center;gap:10px">
                                    <strong style="font-size:15px"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:<?= $groupTypeValue === 'addon' ? '#fde8c8' : '#d4f5e2' ?>;color:<?= $groupTypeValue === 'addon' ? '#784212' : '#145a32' ?>;font-weight:600">
                                        <?= $groupTypeValue === 'addon' ? 'multiple choice • extra charge' : 'single choice • set price' ?>
                                    </span>
                                </div>
                                <button type="button" class="btn btn-gray btn-remove-group" style="white-space:nowrap;">Remove group</button>
                            </div>
                            <input type="hidden" name="group_modifier_id[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($modifierGroupId, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="group_name[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="group_type[<?= (int)$groupIndex ?>]" value="<?= $groupTypeValue ?>">
                            <input type="hidden" name="group_required[<?= (int)$groupIndex ?>]" value="<?= $groupRequiredValue ? 1 : 0 ?>">
                            <input type="hidden" name="group_pricing_type[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($pricingType, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="options-list" style="display:flex;flex-direction:column;gap:12px;padding:0 0 6px;font-size:11px;color:#999">
                                <?php if (!empty($optionNames)): ?>
                                    <?php foreach ($optionNames as $optionIndex => $optionValue): ?>
                                        <div class="option-item" style="display:grid;grid-template-columns:1.5fr 1fr minmax(120px,180px) auto;gap:12px;align-items:start;margin-bottom:14px;padding:14px;border:1px dashed #ddd;border-radius:12px;background:#fff">
                                            <div class="form-group" style="min-width:0;">
                                                <label>Option Name</label>
                                                <input type="text" name="option_name[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" required>
                                            </div>
                                            <div class="form-group" style="min-width:0;">
                                                <label>Price</label>
                                                <input type="number" step="0.01" name="option_price[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionPrices[$optionIndex] ?? '0.00', ENT_QUOTES, 'UTF-8') ?>" style="width:100%;">
                                            </div>
                                            <div class="form-group" style="min-width:0;">
                                                <label>Option Image</label>
                                                <input type="file" name="option_image_file[<?= (int)$groupIndex ?>][]" accept="image/*">
                                                <?php if (!empty($optionImages[$optionIndex])): ?>
                                                    <input type="hidden" name="option_image_existing[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionImages[$optionIndex], ENT_QUOTES, 'UTF-8') ?>">
                                                    <p style="margin:8px 0 0; font-size:13px; color:#555;">Current: <?= htmlspecialchars($optionImages[$optionIndex], ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php else: ?>
                                                    <input type="hidden" name="option_image_existing[<?= (int)$groupIndex ?>][]" value="">
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn-remove-option" style="background:#e74c3c;color:white;border:none;border-radius:50%;width:34px;height:34px;cursor:pointer;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;margin-left:8px">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn-add-option">Add Option</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p style="margin-top:6px; color:#666; font-size:14px;">Optional: groups appear when customers choose this menu item.</p>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available"
                    <?= (isset($is_available) ? $is_available : $product['is_available']) ? 'checked' : '' ?>>
                Available for ordering
            </label>
        </div>

        <div style="grid-column: span 2; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('customization-groups');
    const modifierSelect = document.getElementById('new-modifier-group');
    const availableModifiers = modifierSelect ? Array.from(modifierSelect.querySelectorAll('option[data-name]')).map(option => ({
        id: option.value,
        name: option.dataset.name,
        pricingType: option.dataset.pricing || 'set_price',
        selectType: option.dataset.select || 'single'
    })) : [];

    function createOptionRow(groupIndex) {
        const wrap = document.createElement('div');
        wrap.className = 'option-item';
        wrap.style.cssText = 'display:grid;grid-template-columns:1.5fr 1fr minmax(120px,180px) auto;gap:12px;align-items:start;margin-bottom:14px;padding:14px;border:1px dashed #ddd;border-radius:12px;background:#fff';
        wrap.innerHTML = `
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Option Image</label>
                <input type="file" name="option_image_file[${groupIndex}][]" accept="image/*" style="width:100%">
            </div>
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Option Name</label>
                <input type="text" name="option_name[${groupIndex}][]" placeholder="Option name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px">
            </div>
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Price</label>
                <input type="number" step="0.01" min="0" name="option_price[${groupIndex}][]" placeholder="0.00" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                <div style="font-size:11px;color:#999;margin-top:6px">Additional price (₱) or set price depending on modifier group</div>
            </div>
            <button type="button" style="background:#e74c3c;color:white;border:none;border-radius:50%;width:34px;height:34px;cursor:pointer;font-size:16px;line-height:1;display:flex;align-items:center;justify-content:center;margin-left:8px">✕</button>
        `;

        wrap.querySelector('button').addEventListener('click', () => {
            const list = wrap.parentElement;
            wrap.remove();
            if (list && list.querySelectorAll('.option-item').length === 0) {
                const card = list.closest('.customization-card');
                if (card) {
                    card.remove();
                    updateModifierSelect();
                }
            }
        });
        return wrap;
    }

    function attachOptionRemovers(card) {
        card.querySelectorAll('.btn-remove-option').forEach(button => {
            button.addEventListener('click', function () {
                const item = this.closest('.option-item');
                const list = item ? item.parentElement : null;
                if (item) item.remove();
                if (list && list.querySelectorAll('.option-item').length === 0) {
                    const card = list.closest('.customization-card');
                    if (card) {
                        card.remove();
                        updateModifierSelect();
                    }
                }
            });
        });
    }

    function attachCardEvents(card) {
        const groupType = card.dataset.type;
        const groupIndex = card.dataset.index;
        card.querySelector('.btn-remove-group')?.addEventListener('click', () => {
            card.remove();
            updateModifierSelect();
        });
        card.querySelector('.btn-add-option')?.addEventListener('click', () => {
            card.querySelector('.options-list').appendChild(createOptionRow(groupIndex));
        });
        attachOptionRemovers(card);
    }

    function createModifierCard(modifier, groupIndex) {
        const type = modifier.selectType === 'multiple' ? 'addon' : 'single';
        const card = document.createElement('div');
        card.className = 'customization-card';
        card.dataset.index = groupIndex;
        card.dataset.modifierId = modifier.id;
        card.dataset.type = type;
        card.style.cssText = 'position:relative;border:1px solid #ddd;border-radius:12px;padding:16px;margin-bottom:14px;background:#fcfcfc';
        card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <strong style="font-size:15px">${modifier.name}</strong>
                    <span style="font-size:11px;padding:6px 12px;border-radius:999px;background:${type === 'addon' ? '#fde8c8' : '#d4f5e2'};color:${type === 'addon' ? '#784212' : '#145a32'};font-weight:700;">${type === 'addon' ? 'multiple choice' : 'single choice'} · ${modifier.pricingType === 'extra_charge' ? 'extra charge' : 'set price'}</span>
                </div>
                <button type="button" class="btn btn-gray btn-remove-group" style="white-space:nowrap;">Remove group</button>
            </div>
            <input type="hidden" name="group_modifier_id[${groupIndex}]" value="${modifier.id}">
            <input type="hidden" name="group_name[${groupIndex}]" value="${modifier.name}">
            <input type="hidden" name="group_type[${groupIndex}]" value="${type}">
            <input type="hidden" name="group_required[${groupIndex}]" value="${type === 'addon' ? '0' : '1'}">
            <input type="hidden" name="group_pricing_type[${groupIndex}]" value="${modifier.pricingType}">
            <div class="options-list"></div>
            <button type="button" class="btn btn-gray btn-add-option" style="margin-top:14px;">Add Option</button>
        `;
        const removeButton = card.querySelector('.btn-remove-group');
        const addButton = card.querySelector('.btn-add-option');
        const optionList = card.querySelector('.options-list');
        removeButton.addEventListener('click', () => {
            card.remove();
            updateModifierSelect();
        });
        addButton.addEventListener('click', () => optionList.appendChild(createOptionRow(groupIndex)));
        optionList.appendChild(createOptionRow(groupIndex));
        return card;
    }

    function updateModifierSelect() {
        if (!modifierSelect) return;
        const selectedIds = new Set(Array.from(container.querySelectorAll('.customization-card')).map(card => card.dataset.modifierId));
        modifierSelect.querySelectorAll('option').forEach(option => {
            if (!option.value) return;
            option.disabled = selectedIds.has(option.value);
        });
    }

    function attachExistingCardEvents() {
        container.querySelectorAll('.customization-card').forEach(card => {
            const removeButton = card.querySelector('.btn-remove-group');
            const addButton = card.querySelector('.btn-add-option');
            const optionList = card.querySelector('.options-list');
            const index = card.dataset.index;
            if (removeButton) {
                removeButton.addEventListener('click', () => {
                    card.remove();
                    updateModifierSelect();
                });
            }
            if (addButton) {
                addButton.addEventListener('click', () => optionList.appendChild(createOptionRow(index)));
            }
            attachOptionRemovers(card);
        });
    }

    function getNextGroupIndex() {
        const indices = Array.from(container.querySelectorAll('.customization-card')).map(card => parseInt(card.dataset.index, 10)).filter(Number.isFinite);
        return indices.length ? Math.max(...indices) + 1 : 0;
    }

    function addModifierGroup(modifier) {
        const groupIndex = getNextGroupIndex();
        container.appendChild(createModifierCard(modifier, groupIndex));
        updateModifierSelect();
        if (modifierSelect) {
            modifierSelect.value = '';
        }
    }

    if (modifierSelect) {
        modifierSelect.addEventListener('change', function () {
            const selectedModifierId = this.value;
            if (!selectedModifierId) return;
            const modifier = availableModifiers.find(m => m.id === selectedModifierId);
            if (modifier) {
                addModifierGroup(modifier);
            }
        });
    }

    attachExistingCardEvents();
    updateModifierSelect();
});
</script>
</body>
</html>