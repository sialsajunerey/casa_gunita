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

$name         = '';
$description  = '';
$price        = '';
$category_id  = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$is_available = 1;
$stock        = 50;
$image_name   = '';
$categories   = [];

$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}
if (!$category_id && !empty($categories)) {
    $category_id = (int)$categories[0]['category_id'];
}

$modifierGroups = [];
$modifierResult = mysqli_query($conn, "SELECT modifier_group_id, name, pricing_type, select_option FROM modifier_groups ORDER BY name");
while ($modifier = mysqli_fetch_assoc($modifierResult)) {
    $modifierGroups[] = $modifier;
}

// Handle customization groups and options
$posted_group_modifier_ids = $_POST['group_modifier_id'] ?? [];
$posted_group_names = $_POST['group_name'] ?? [];
$posted_group_types = $_POST['group_type'] ?? [];
$posted_group_required = $_POST['group_required'] ?? [];
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
    $image_name   = '';

    if ($category_id <= 0) {
        $error = "Please select a valid category.";
    } else {
        $cat_check = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
        mysqli_stmt_bind_param($cat_check, 'i', $category_id);
        mysqli_stmt_execute($cat_check);
        $cat_check_result = mysqli_stmt_get_result($cat_check);
        if (mysqli_num_rows($cat_check_result) === 0) {
            $error = "Selected category does not exist.";
        }
    }

    // Handle image upload
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
        // Insert product
        $stmt = mysqli_prepare($conn,
            "INSERT INTO products (name, description, price, category_id, image, is_available)
             VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssdisi',
            $name, $description, $price, $category_id, $image_name, $is_available);

        if (mysqli_stmt_execute($stmt)) {
            $product_id = mysqli_insert_id($conn);

            // Insert inventory row
            $inv = mysqli_prepare($conn,
                "INSERT INTO inventory (product_id, stock_quantity)
                 VALUES (?, ?)");
            mysqli_stmt_bind_param($inv, 'ii', $product_id, $stock);
            mysqli_stmt_execute($inv);

            // Log audit: menu_add
            $admin_id = $_SESSION['user_id'] ?? null;
            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, product_id, details)
                 VALUES (?, 'menu_add', 'product', ?, ?, ?)");
            $details = "Added product: $name (Price: ₱" . number_format($price, 2) . ")";
            mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $product_id, $product_id, $details);
            mysqli_stmt_execute($audit_stmt);

            // Save customization groups and options
            if (!empty($posted_group_names) && is_array($posted_group_names)) {
                $groupStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_groups
                     (product_id, name, group_type, is_required, display_order)
                     VALUES (?, ?, ?, ?, ?)");
                $optionStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_options
                     (group_id, name, additional_price, image, display_order)
                     VALUES (?, ?, ?, ?, ?)");

                $displayOrder = 0;
                foreach ($posted_group_names as $groupIndex => $groupName) {
                    $groupName = sanitize(trim($groupName));
                    if ($groupName === '') continue;

                    $groupType = $posted_group_types[$groupIndex] ?? 'single';
                    $isRequired = isset($posted_group_required[$groupIndex]) ? 1 : 0;

                    mysqli_stmt_bind_param($groupStmt, 'issii',
                        $product_id, $groupName, $groupType, $isRequired, $displayOrder);
                    mysqli_stmt_execute($groupStmt);
                    $groupId = mysqli_insert_id($conn);

                    // Save options for this group
                    $optionNames = $posted_option_names[$groupIndex] ?? [];
                    $optionPrices = $posted_option_prices[$groupIndex] ?? [];
                    $optionImages = $posted_option_images[$groupIndex] ?? [];

                    $optionDisplayOrder = 0;
                    foreach ($optionNames as $optionIndex => $optionName) {
                        $optionName = sanitize(trim($optionName));
                        if ($optionName === '') continue;

                        $price = isset($optionPrices[$optionIndex]) ? (float)$optionPrices[$optionIndex] : 0.00;
                        $imageName = '';

                        // Handle image upload
                        $file = get_nested_file($_FILES['option_image_file'] ?? [], $groupIndex, $optionIndex);
                        if ($file && $file['error'] === UPLOAD_ERR_OK) {
                            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, $allowed)) {
                                $imageName = time() . '_' . uniqid() . '_' . basename($file['name']);
                                $upload_path = __DIR__ . '/../assets/images/' . $imageName;
                                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                                    // Image uploaded successfully
                                }
                            }
                        }

                        mysqli_stmt_bind_param($optionStmt, 'isdsi',
                            $groupId, $optionName, $price, $imageName, $optionDisplayOrder);
                        mysqli_stmt_execute($optionStmt);
                        $optionDisplayOrder++;
                    }
                    $displayOrder++;
                }

                // Save linked modifier groups for reference
                if (!empty($posted_group_modifier_ids) && is_array($posted_group_modifier_ids)) {
                    $linkStmt = mysqli_prepare($conn,
                        "INSERT INTO product_modifier_groups
                         (product_id, modifier_group_id, is_required, display_order)
                         VALUES (?, ?, ?, ?)");
                    $linkDisplayOrder = 0;
                    foreach ($posted_group_modifier_ids as $groupIndex => $modifierGroupId) {
                        $modifierGroupId = (int)$modifierGroupId;
                        if ($modifierGroupId <= 0) continue;
                        $isRequired = isset($posted_group_required[$groupIndex]) ? 1 : 0;
                        mysqli_stmt_bind_param($linkStmt, 'iiii', $product_id, $modifierGroupId, $isRequired, $linkDisplayOrder);
                        mysqli_stmt_execute($linkStmt);
                        $linkDisplayOrder++;
                    }
                }

            }

            $redirectUrl = 'menu.php' . ($category_id > 0 ? '?category_id=' . $category_id : '');
            header('Location: ' . $redirectUrl);
            exit();
        } else {
            $error = "Failed to add menu item. Please ensure the selected category still exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu Item — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu_edit.css">
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
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
        .page-header h2 { font-family: 'Cinzel Decorative', serif; font-size: 2.2rem; color: var(--crimson); margin: 0; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-weight: 600; color: var(--ink); }
        input[type=text], input[type=number], textarea, select, input[type=file] { width: 100%; border: 1px solid #d6d2d9; border-radius: 12px; background: #fff; color: var(--ink); padding: 12px 14px; font-size: 0.95rem; }
        textarea { min-height: 110px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; padding: 12px 20px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--crimson); color: #fff; }
        .btn-gray { background: #6b7280; color: #fff; }
        .alert { padding: 14px 16px; border-radius: 14px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #981b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .customization-card { border: 1px solid #d9d4d4; border-radius: 16px; background: #faf8f5; padding: 18px; }
        .customization-card .options-list { display: flex; flex-direction: column; gap: 14px; }
        .option-item { display:grid; grid-template-columns:1.5fr 1fr minmax(120px,180px) auto; gap:12px; align-items:start; padding:14px; border:1px dashed #d6d2d9; border-radius:14px; background:#fff; }
        .customization-footer { font-size: 0.95rem; color: #6b7280; margin-top: 6px; }
        .btn-add-option, .btn-remove-option { min-width: fit-content; }
        .btn-remove-option { border-radius: 50%; width: 34px; height: 34px; padding: 0; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php" class="active">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="modifiers.php">Modifiers</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <a href="menu.php?category_id=<?= $category_id ?>" class="topbar-back">← Back to <?php
            $cat_name = '';
            foreach ($categories as $cat) {
                if ((int)$cat['category_id'] === $category_id) {
                    $cat_name = $cat['name'];
                    break;
                }
            }
            echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8');
            ?></a>
        <span class="topbar-divider">|</span>
        <span class="topbar-title">Add Menu Item</span>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <main class="content">
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="edit-layout">

                    <div class="edit-main">

                        <div class="card">
                            <div class="card-title">Basic Information</div>
                            <div class="form-grid">

                                <div class="form-group">
                                    <label>Dish Name</label>
                                    <input type="text" name="name"
                                        value="<?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Category</label>
                                    <input type="hidden" name="category_id" value="<?= $category_id ?>">
                                    <input type="text" readonly value="<?php
                                        $cat_name = '';
                                        foreach ($categories as $cat) {
                                            if ((int)$cat['category_id'] === $category_id) {
                                                $cat_name = $cat['name'];
                                                break;
                                            }
                                        }
                                        echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8');
                                        ?>">
                                </div>

                                <div class="form-group full">
                                    <label>Description</label>
                                    <textarea name="description"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Price (₱)</label>
                                    <input type="number" name="price" step="0.01"
                                        value="<?= htmlspecialchars($price, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Stock Quantity</label>
                                    <input type="number" name="stock_quantity"
                                        value="<?= htmlspecialchars($stock, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                                </div>

                            </div>
                        </div>

                        <div class="card">
                            <div class="card-title">Customization Groups</div>
                            <div class="customization-section">
                                <?php if (empty($modifierGroups)): ?>
                                    <p class="no-modifiers-notice">No modifier groups available. Create them first in the <a href="modifiers.php" style="color:var(--dark);font-weight:600;">Modifiers</a> page.</p>
                                <?php else: ?>
                                    <div class="modifier-select-row">
                                        <select id="new-modifier-group">
                                            <option value="">+ Add modifier group…</option>
                                            <?php foreach ($modifierGroups as $modifier): ?>
                                                <option value="<?= (int)$modifier['modifier_group_id'] ?>"
                                                        data-name="<?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-pricing="<?= htmlspecialchars($modifier['pricing_type'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-select="<?= htmlspecialchars($modifier['select_option'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div id="customization-groups">
                                    <?php
                                    $renderGroups = !empty($posted_group_names) ? $posted_group_names : [];
                                    if (!empty($renderGroups)):
                                        foreach ($renderGroups as $groupIndex => $groupName):
                                            $groupName = sanitize($groupName);
                                            $groupTypeValue = $posted_group_types[$groupIndex] ?? 'single';
                                            $groupTypeValue = $groupTypeValue === 'addon' ? 'addon' : 'single';
                                            $groupRequiredValue = isset($posted_group_required[$groupIndex]) ? true : false;
                                            $optionNames = $posted_option_names[$groupIndex] ?? [];
                                            $optionPrices = $posted_option_prices[$groupIndex] ?? [];
                                            $optionImages = $posted_option_images[$groupIndex] ?? [];
                                            $pricingType = $posted_group_pricing_type[$groupIndex] ?? 'set_price';
                                    ?>
                                            <div class="customization-card" data-index="<?= (int)$groupIndex ?>" data-modifier-id="<?= htmlspecialchars($posted_group_modifier_ids[$groupIndex] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($groupTypeValue, ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="customization-card-header">
                                                    <div class="customization-card-header-left">
                                                        <span class="group-label"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="group-badge <?= $groupTypeValue === 'addon' ? 'badge-addon' : 'badge-single' ?>">
                                                            <?= $groupTypeValue === 'addon' ? 'Multiple choice' : 'Single choice' ?>
                                                        </span>
                                                        <label style="display:flex;align-items:center;gap:6px;margin-left:12px;font-weight:500;font-size:13px;cursor:pointer;">
                                                            <input type="checkbox" class="group-required-checkbox" data-group-index="<?= (int)$groupIndex ?>" <?= $groupRequiredValue ? 'checked' : '' ?>>
                                                            Required
                                                        </label>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-danger btn-remove-group">Remove</button>
                                                </div>

                                                <input type="hidden" name="group_modifier_id[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($posted_group_modifier_ids[$groupIndex] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="group_name[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="group_type[<?= (int)$groupIndex ?>]" value="<?= $groupTypeValue ?>">
                                                <input type="hidden" name="group_required[<?= (int)$groupIndex ?>]" value="<?= $groupRequiredValue ? 1 : 0 ?>">
                                                <input type="hidden" name="group_pricing_type[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($pricingType, ENT_QUOTES, 'UTF-8') ?>">

                                                <div class="customization-card-body options-list">
                                                    <?php if (!empty($optionNames)): ?>
                                                        <?php foreach ($optionNames as $optionIndex => $optionValue): ?>
                                                            <div class="option-row">
                                                                <div class="form-group">
                                                                    <label>Option Name</label>
                                                                    <input type="text" name="option_name[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" required>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Price (₱)</label>
                                                                    <input type="number" step="0.01" name="option_price[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionPrices[$optionIndex] ?? '0.00', ENT_QUOTES, 'UTF-8') ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Image</label>
                                                                    <input type="file" name="option_image_file[<?= (int)$groupIndex ?>][]" accept="image/*">
                                                                    <?php if (!empty($optionImages[$optionIndex])): ?>
                                                                        <input type="hidden" name="option_image_existing[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionImages[$optionIndex], ENT_QUOTES, 'UTF-8') ?>">
                                                                        <span class="option-current-img"><?= htmlspecialchars($optionImages[$optionIndex], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    <?php else: ?>
                                                                        <input type="hidden" name="option_image_existing[<?= (int)$groupIndex ?>][]" value="">
                                                                    <?php endif; ?>
                                                                </div>
                                                                <button type="button" class="btn-icon btn-remove-option" title="Remove option">✕</button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="customization-card-footer">
                                                    <button type="button" class="btn btn-sm btn-gray btn-add-option">+ Add Option</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="hint">Optional — groups appear when customers select this menu item.</p>
                            </div>
                        </div>

                    </div>

                    <div class="edit-side">
                        <div class="card">
                            <div class="card-title">Dish Image</div>
                            <div class="img-preview-wrap">
                                <span class="img-placeholder">No image selected</span>
                            </div>
                            <div class="form-group">
                                <label>Choose Image</label>
                                <input type="file" name="image" accept="image/*">
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-title">Availability</div>
                            <div class="availability-row">
                                <label>
                                    <input type="checkbox" name="is_available" <?= $is_available ? 'checked' : '' ?>> Available for ordering
                                </label>
                            </div>
                        </div>

                        <div class="save-bar">
                            <span class="save-bar-hint">Ready to add this item.</span>
                            <a href="menu.php?category_id=<?= $category_id ?>" class="btn btn-gray">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Menu Item</button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle image preview
    const imageInput = document.querySelector('input[name="image"]');
    const imgPreviewWrap = document.querySelector('.img-preview-wrap');
    
    if (imageInput && imgPreviewWrap) {
        imageInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imgPreviewWrap.innerHTML = `<img src="${e.target.result}" alt="Preview" class="preview-img">`;
                };
                reader.readAsDataURL(file);
            } else if (file) {
                imgPreviewWrap.innerHTML = '<span class="img-placeholder">Invalid image file</span>';
            }
        });
    }

    const container      = document.getElementById('customization-groups');
    const modifierSelect = document.getElementById('new-modifier-group');
    const availableModifiers = modifierSelect
        ? Array.from(modifierSelect.querySelectorAll('option[data-name]')).map(o => ({
            id:          o.value,
            name:        o.dataset.name,
            pricingType: o.dataset.pricing || 'set_price',
            selectType:  o.dataset.select  || 'single'
          }))
        : [];

    function createOptionRow(groupIndex) {
        const wrap = document.createElement('div');
        wrap.className = 'option-row';
        wrap.innerHTML = `
            <div class="form-group">
                <label>Option Name</label>
                <input type="text" name="option_name[${groupIndex}][]" placeholder="e.g. Large" required>
            </div>
            <div class="form-group">
                <label>Price (₱)</label>
                <input type="number" step="0.01" min="0" name="option_price[${groupIndex}][]" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Image</label>
                <input type="file" name="option_image_file[${groupIndex}][]" accept="image/*">
                <input type="hidden" name="option_image_existing[${groupIndex}][]" value="">
            </div>
            <button type="button" class="btn-icon btn-remove-option" title="Remove">✕</button>
        `;
        wrap.querySelector('.btn-remove-option').addEventListener('click', () => {
            wrap.remove();
            removeCardIfEmpty(wrap);
        });
        return wrap;
    }

    function removeCardIfEmpty(el) {
        const card = el.closest ? el.closest('.customization-card') : null;
        if (card) {
            const list = card.querySelector('.options-list');
            if (list && list.querySelectorAll('.option-row').length === 0) {
                card.remove();
                updateModifierSelect();
            }
        }
    }

    function attachOptionRemovers(card) {
        card.querySelectorAll('.btn-remove-option').forEach(btn => {
            btn.addEventListener('click', function () {
                const row = this.closest('.option-row');
                if (row) row.remove();
                removeCardIfEmpty(this);
            });
        });
    }

    function attachCardEvents(card) {
        card.querySelector('.btn-remove-group')?.addEventListener('click', () => {
            card.remove();
            updateModifierSelect();
        });
        card.querySelector('.btn-add-option')?.addEventListener('click', () => {
            card.querySelector('.options-list').appendChild(createOptionRow(card.dataset.index));
        });
        attachOptionRemovers(card);
    }

    function createModifierCard(modifier, groupIndex) {
        const type  = modifier.selectType === 'multiple' ? 'addon' : 'single';
        const badge = type === 'addon' ? 'Multiple choice' : 'Single choice';
        const badgeClass = type === 'addon' ? 'badge-addon' : 'badge-single';
        const card  = document.createElement('div');
        card.className = 'customization-card';
        card.dataset.index      = groupIndex;
        card.dataset.modifierId = modifier.id;
        card.dataset.type       = type;
        card.innerHTML = `
            <div class="customization-card-header">
                <div class="customization-card-header-left">
                    <span class="group-label">${modifier.name}</span>
                    <span class="group-badge ${badgeClass}">${badge}</span>
                    <label style="display:flex;align-items:center;gap:6px;margin-left:12px;font-weight:500;font-size:13px;cursor:pointer;">
                        <input type="checkbox" class="group-required-checkbox" data-group-index="${groupIndex}" ${type === 'addon' ? '' : 'checked'}>
                        Required
                    </label>
                </div>
                <button type="button" class="btn btn-sm btn-danger btn-remove-group">Remove</button>
            </div>
            <input type="hidden" name="group_modifier_id[${groupIndex}]" value="${modifier.id}">
            <input type="hidden" name="group_name[${groupIndex}]" value="${modifier.name}">
            <input type="hidden" name="group_type[${groupIndex}]" value="${type}">
            <input type="hidden" name="group_required[${groupIndex}]" value="${type === 'addon' ? '0' : '1'}">
            <input type="hidden" name="group_pricing_type[${groupIndex}]" value="${modifier.pricingType}">
            <div class="customization-card-body options-list"></div>
            <div class="customization-card-footer">
                <button type="button" class="btn btn-sm btn-gray btn-add-option">+ Add Option</button>
            </div>
        `;
        card.querySelector('.btn-remove-group').addEventListener('click', () => {
            card.remove();
            updateModifierSelect();
        });
        card.querySelector('.btn-add-option').addEventListener('click', () => {
            card.querySelector('.options-list').appendChild(createOptionRow(groupIndex));
        });
        card.querySelector('.options-list').appendChild(createOptionRow(groupIndex));
        return card;
    }

    function updateModifierSelect() {
        if (!modifierSelect) return;
        const selectedIds = new Set(
            Array.from(container.querySelectorAll('.customization-card')).map(c => c.dataset.modifierId)
        );
        modifierSelect.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            opt.disabled = selectedIds.has(opt.value);
        });
    }

    function getNextGroupIndex() {
        const indices = Array.from(container.querySelectorAll('.customization-card'))
            .map(c => parseInt(c.dataset.index, 10)).filter(Number.isFinite);
        return indices.length ? Math.max(...indices) + 1 : 0;
    }

    container.querySelectorAll('.customization-card').forEach(attachCardEvents);

    if (modifierSelect) {
        modifierSelect.addEventListener('change', function () {
            const id = this.value;
            if (!id) return;
            const modifier = availableModifiers.find(m => m.id === id);
            if (modifier) {
                container.appendChild(createModifierCard(modifier, getNextGroupIndex()));
                updateModifierSelect();
                this.value = '';
            }
        });
    }

    updateModifierSelect();
});
</script>
</body>
</html>