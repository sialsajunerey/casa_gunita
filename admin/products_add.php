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
$category_id  = 0;
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

$posted_group_names = $_POST['group_name'] ?? [];
$posted_group_types = $_POST['group_type'] ?? [];
$posted_group_required = $_POST['group_required'] ?? [];
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
                "INSERT INTO inventory (product_id, stock_quantity, low_stock_alert)
                 VALUES (?, ?, 5)");
            mysqli_stmt_bind_param($inv, 'ii', $product_id, $stock);
            mysqli_stmt_execute($inv);

            // Save customization groups and options
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

                foreach ($posted_group_names as $groupIndex => $groupTypeKey) {
                    $groupLabels = ['addon' => 'Add-ons', 'flavor' => 'Flavor', 'size' => 'Size'];
                    $groupTypeKey = in_array($groupTypeKey, ['addon', 'flavor', 'size'], true) ? $groupTypeKey : 'flavor';
                    $groupName = sanitize($groupLabels[$groupTypeKey]);
                    $selectedType = $posted_group_types[$groupIndex] ?? $groupTypeKey;
                    $groupType = $selectedType === 'addon' ? 'addon' : 'single';
                    $groupRequired = isset($posted_group_required[$groupIndex]) ? 1 : 0;

                    mysqli_stmt_bind_param($groupStmt, 'issii',
                        $product_id, $groupName, $groupType, $groupRequired, $groupOrder);
                    mysqli_stmt_execute($groupStmt);
                    $groupId = mysqli_insert_id($conn);

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

            header('Location: products.php');
            exit();
        } else {
            $error = "Failed to add product. Please ensure the selected category still exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product — Casa Gunita Admin</title>
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
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="products.php" class="active"><span class="icon">🍖</span> Products</a></li>
        <li><a href="inventory.php"><span class="icon">📦</span> Inventory</a></li>
        <li><a href="transactions.php"><span class="icon">💰</span> Transactions</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Add Product</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <main class="content">
        <div class="card">
            <div class="page-header">
                <h2>Add New Product</h2>
                <div>
                    <a href="products.php" class="btn btn-primary">← Back to Products</a>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-grid">
                <div class="form-group">
                    <label>Dish Name</label>
                    <input type="text" name="name" placeholder="e.g. Adobong Manok" value="<?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Short description of the dish"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" name="price" step="0.01" value="<?= htmlspecialchars($price, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <?php if (empty($categories)): ?>
                            <option value="0">No categories available</option>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" <?= $category_id === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($categories)): ?>
                        <p class="customization-footer">Add categories first under the categories admin page before adding products.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" value="<?= htmlspecialchars($stock, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label>Product Image (optional)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Customization</label>
                    <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                        <select id="new-cust-type" style="width:100%;max-width:260px;padding:12px;border:1px solid #d6d2d9;border-radius:12px;background:#fff;color:var(--ink)">
                            <option value="">Select type</option>
                            <option value="addon">Add-ons</option>
                            <option value="flavor">Flavor</option>
                            <option value="size">Size</option>
                        </select>
                    </div>
                    <div id="customization-groups"></div>
                    <p class="customization-footer">Optional: groups appear when customers choose this product.</p>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>
                        <input type="checkbox" name="is_available" <?= $is_available ? 'checked' : '' ?>> Available for ordering
                    </label>
                </div>
                <div style="grid-column: span 2; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="btn btn-primary" <?= empty($categories) ? 'disabled' : '' ?>>Add Product</button>
                    <?php if (empty($categories)): ?><span class="customization-footer">Cannot add products until categories exist.</span><?php endif; ?>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('customization-groups');
    const typeSelect = document.getElementById('new-cust-type');
    const typeConfig = {
        size:   { label: 'Size',    priceNote: 'Full price (₱)' },
        flavor: { label: 'Flavor',  priceNote: 'Full price (₱)' },
        addon:  { label: 'Add-ons', priceNote: 'Additional price (₱)' }
    };

    function updateTypeOptions() {
        const selected = new Set(Array.from(container.querySelectorAll('.customization-card')).map(c => c.dataset.type));
        typeSelect.querySelectorAll('option').forEach(option => {
            if (!option.value) return;
            option.disabled = selected.has(option.value);
        });
    }

    function makeOption(groupIndex, type) {
        const cfg = typeConfig[type];
        const wrap = document.createElement('div');
        wrap.className = 'option-item';
        wrap.innerHTML = `
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Option Image</label>
                <input type="file" name="option_image_file[${groupIndex}][]" accept="image/*" style="width:100%;padding:10px;border:1px solid #d6d2d9;border-radius:12px;">
            </div>
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Option Name</label>
                <input type="text" name="option_name[${groupIndex}][]" placeholder="Option name" required style="width:100%;padding:10px;border:1px solid #d6d2d9;border-radius:12px;">
            </div>
            <div style="min-width:0;">
                <label style="display:block;font-size:13px;color:#555;margin-bottom:6px">Price</label>
                <input type="number" step="0.01" min="0" name="option_price[${groupIndex}][]" placeholder="0.00" style="width:100%;padding:10px;border:1px solid #d6d2d9;border-radius:12px;">
                <div style="font-size:11px;color:#6b7280;margin-top:6px">${cfg.priceNote}</div>
            </div>
            <button type="button" class="btn btn-gray" style="min-width:unset;width:34px;height:34px;padding:0;border-radius:50%;">✕</button>
        `;
        wrap.querySelector('button').addEventListener('click', () => {
            const list = wrap.parentElement;
            wrap.remove();
            if (list && list.querySelectorAll('.option-item').length === 0) {
                const card = list.closest('.customization-card');
                if (card) {
                    card.remove();
                    updateTypeOptions();
                }
            }
        });
        return wrap;
    }

    function makeCard(type, groupIndex) {
        const groupLabel = type === 'addon' ? 'Add-ons' : (type === 'size' ? 'Size' : 'Flavor');
        const card = document.createElement('div');
        card.className = 'customization-card';
        card.dataset.index = groupIndex;
        card.dataset.type = type;
        card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <strong style="font-size:15px">${groupLabel}</strong>
                    <span style="font-size:11px;padding:6px 12px;border-radius:999px;background:${type === 'addon' ? '#fde8c8' : (type === 'size' ? '#d4e9ff' : '#d4f5e2')};color:${type === 'addon' ? '#784212' : (type === 'size' ? '#1a5276' : '#14532d')};font-weight:700;">${type === 'addon' ? 'multiple choice • extra charge' : 'single choice • set price'}</span>
                </div>
                <button type="button" class="btn btn-gray" style="white-space:nowrap;">Remove group</button>
            </div>
            <input type="hidden" name="group_name[${groupIndex}]" value="${type}">
            <input type="hidden" name="group_type[${groupIndex}]" value="${type === 'addon' ? 'addon' : 'single'}">
            <input type="hidden" name="group_required[${groupIndex}]" value="${type === 'addon' ? '0' : '1'}">
            <div class="options-list"></div>
            <button type="button" class="btn btn-gray" style="margin-top:14px;">Add Option</button>
        `;
        const removeButton = card.querySelector('button.btn-gray');
        const addButton = card.querySelectorAll('button.btn-gray')[1];
        const optionList = card.querySelector('.options-list');
        removeButton.addEventListener('click', () => {
            card.remove();
            updateTypeOptions();
        });
        addButton.addEventListener('click', () => optionList.appendChild(makeOption(groupIndex, type)));
        optionList.appendChild(makeOption(groupIndex, type));
        return card;
    }

    function getNextGroupIndex() {
        const cards = Array.from(container.querySelectorAll('.customization-card'));
        return cards.reduce((max, card) => Math.max(max, Number(card.dataset.index)), -1) + 1;
    }

    function addSelectedGroup() {
        if (!typeSelect.value) return;
        const existingTypes = new Set(Array.from(container.querySelectorAll('.customization-card')).map(c => c.dataset.type));
        if (existingTypes.has(typeSelect.value)) {
            typeSelect.value = '';
            return;
        }
        container.appendChild(makeCard(typeSelect.value, getNextGroupIndex()));
        typeSelect.value = '';
        updateTypeOptions();
    }

    typeSelect.addEventListener('change', addSelectedGroup);
    updateTypeOptions();
});
</script>
</body>
</html>