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

// Load available customization groups
$customizationGroups = [];
$customizationResult = mysqli_query($conn, "SELECT modifier_group_id, name, pricing_type, select_option FROM modifier_groups ORDER BY name");
while ($custom = mysqli_fetch_assoc($customizationResult)) {
    $customizationGroups[] = $custom;
}
$customizationMap = [];
foreach ($customizationGroups as $custom) {
    $customizationMap[$custom['modifier_group_id']] = $custom;
}

// Fetch current stock
$inv_stmt = mysqli_prepare($conn, "SELECT * FROM inventory WHERE product_id = ?");
mysqli_stmt_bind_param($inv_stmt, 'i', $id);
mysqli_stmt_execute($inv_stmt);
$inventory = mysqli_fetch_assoc(mysqli_stmt_get_result($inv_stmt));

// Load existing customization groups and options
$existing_groups = [];
$groupStmt = mysqli_prepare($conn,
    "SELECT group_id, name, group_type, pricing_type, is_required
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
    $groupIds     = array_keys($existing_groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT option_id, group_id, name, additional_price, image
            FROM product_customization_options
            WHERE group_id IN ($placeholders)
            ORDER BY display_order, option_id";
    $optStmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('i', count($groupIds));
    $refs  = [];
    foreach ($groupIds as $idx => $gid) { $refs[$idx] = &$groupIds[$idx]; }
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
    "SELECT modifier_group_id, is_required FROM product_modifier_groups WHERE product_id = ? ORDER BY display_order");
mysqli_stmt_bind_param($linkStmt, 'i', $id);
mysqli_stmt_execute($linkStmt);
$linkResult = mysqli_stmt_get_result($linkStmt);
while ($link = mysqli_fetch_assoc($linkResult)) {
    $existing_modifier_links[] = $link;
}

$posted_group_names       = $_POST['group_name']              ?? [];
$posted_group_types       = $_POST['group_type']              ?? [];
$posted_group_required    = $_POST['group_required']          ?? [];
$posted_group_modifier_ids= $_POST['group_modifier_id']       ?? [];
$posted_group_pricing_type= $_POST['group_pricing_type']      ?? [];
$posted_option_names      = $_POST['option_name']             ?? [];
$posted_option_prices     = $_POST['option_price']            ?? [];
$posted_option_images     = $_POST['option_image_existing']   ?? [];

function get_nested_file(array $files, int $groupIndex, int $optionIndex) {
    if (empty($files['tmp_name'][$groupIndex][$optionIndex])) return null;
    return [
        'name'     => $files['name'][$groupIndex][$optionIndex]     ?? '',
        'tmp_name' => $files['tmp_name'][$groupIndex][$optionIndex],
        'error'    => $files['error'][$groupIndex][$optionIndex]    ?? UPLOAD_ERR_NO_FILE,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name']);
    $description  = trim($_POST['description'] ?? '');
    $price        = (float)$_POST['price'];
    $category_id  = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $stock        = (int)$_POST['stock_quantity'];
    $image_name   = $product['image'];

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
        $stmt = mysqli_prepare($conn,
            "UPDATE products SET name=?, description=?, price=?, category_id=?, image=?, is_available=? WHERE product_id=?");
        mysqli_stmt_bind_param($stmt, 'ssdisii',
            $name, $description, $price, $category_id, $image_name, $is_available, $id);

        if (mysqli_stmt_execute($stmt)) {
            $inv_update = mysqli_prepare($conn, "UPDATE inventory SET stock_quantity=? WHERE product_id=?");
            mysqli_stmt_bind_param($inv_update, 'ii', $stock, $id);
            mysqli_stmt_execute($inv_update);

            $admin_id   = $_SESSION['user_id'] ?? null;
            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, target_id, product_id, details) VALUES (?, 'menu_edit', 'product', ?, ?, ?)");
            $details = "Updated product: $name (Price: ₱" . number_format($price, 2) . ")";
            mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $id, $id, $details);
            mysqli_stmt_execute($audit_stmt);

            $deleteOptions = mysqli_prepare($conn,
                "DELETE o FROM product_customization_options o JOIN product_customization_groups g ON o.group_id = g.group_id WHERE g.product_id = ?");
            mysqli_stmt_bind_param($deleteOptions, 'i', $id);
            mysqli_stmt_execute($deleteOptions);

            $deleteGroups = mysqli_prepare($conn, "DELETE FROM product_customization_groups WHERE product_id = ?");
            mysqli_stmt_bind_param($deleteGroups, 'i', $id);
            mysqli_stmt_execute($deleteGroups);

            $deleteModifierLinks = mysqli_prepare($conn, "DELETE FROM product_modifier_groups WHERE product_id = ?");
            mysqli_stmt_bind_param($deleteModifierLinks, 'i', $id);
            mysqli_stmt_execute($deleteModifierLinks);

            if (!empty($posted_group_names) && is_array($posted_group_names)) {
                $groupOrder       = 0;
                $groupStmt        = mysqli_prepare($conn,
                    "INSERT INTO product_customization_groups (product_id, name, group_type, pricing_type, is_required, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                $groupOptionStmt  = mysqli_prepare($conn,
                    "INSERT INTO product_customization_options (group_id, name, additional_price, image, display_order) VALUES (?, ?, ?, ?, ?)");
                $modifierLinkStmt = mysqli_prepare($conn,
                    "INSERT INTO product_modifier_groups (product_id, modifier_group_id, is_required, display_order) VALUES (?, ?, ?, ?)");

                foreach ($posted_group_names as $groupIndex => $groupNameRaw) {
                    $groupName = sanitize(trim($groupNameRaw));
                    if ($groupName === '') continue;
                    $groupType     = in_array($posted_group_types[$groupIndex] ?? '', ['single', 'addon'], true)
                        ? $posted_group_types[$groupIndex] : 'single';
                    $pricingType   = in_array($posted_group_pricing_type[$groupIndex] ?? '', ['set_price', 'extra_charge'], true)
                        ? $posted_group_pricing_type[$groupIndex] : 'extra_charge';
                    $groupRequired = (isset($posted_group_required[$groupIndex]) && $posted_group_required[$groupIndex] == '1') ? 1 : 0;

                    mysqli_stmt_bind_param($groupStmt, 'isssii', $id, $groupName, $groupType, $pricingType, $groupRequired, $groupOrder);
                    mysqli_stmt_execute($groupStmt);
                    $groupId = mysqli_insert_id($conn);

                    // Audit log for customization group addition (inline)
                    $cust_audit_stmt = mysqli_prepare($conn,
                        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, product_id, details)
                         VALUES (?, 'modifier_add', 'customization', ?, ?, ?)");
                    $cust_details = "Added Customization Group: $groupName (inline during menu edit)";
                    mysqli_stmt_bind_param($cust_audit_stmt, 'iiis', $admin_id, $groupId, $id, $cust_details);
                    mysqli_stmt_execute($cust_audit_stmt);

                    $modifierGroupId = isset($posted_group_modifier_ids[$groupIndex]) ? (int)$posted_group_modifier_ids[$groupIndex] : 0;
                    if ($modifierGroupId > 0) {
                        mysqli_stmt_bind_param($modifierLinkStmt, 'iiii', $id, $modifierGroupId, $groupRequired, $groupOrder);
                        mysqli_stmt_execute($modifierLinkStmt);
                    }

                    $optionOrder = 0;
                    if (!empty($posted_option_names[$groupIndex]) && is_array($posted_option_names[$groupIndex])) {
                        foreach ($posted_option_names[$groupIndex] as $optionIndex => $optionNameRaw) {
                            $optionName = sanitize($optionNameRaw);
                            if ($optionName === '') continue;
                            $optionPrice = isset($posted_option_prices[$groupIndex][$optionIndex])
                                ? (float)$posted_option_prices[$groupIndex][$optionIndex] : 0.00;

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

            $redirectCategoryId = $category_id > 0 ? $category_id : $product['category_id'];
            header('Location: menu.php' . ($redirectCategoryId ? '?category_id=' . $redirectCategoryId : ''));
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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu_edit.css">
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
        <li><a href="customizations.php">Customizations</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit Log</a></li>
        <li><a href="analytics.php">Analytics</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main">

    <header class="topbar">
        <a href="menu.php?category_id=<?= $product['category_id'] ?>" class="topbar-back">← Back to <?php
            $cat_name = '';
            foreach ($categories as $cat) {
                if ((int)$cat['category_id'] === (int)$product['category_id']) {
                    $cat_name = $cat['name'];
                    break;
                }
            }
            echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8');
            ?></a>
        <span class="topbar-divider">|</span>
        <span class="topbar-title">
            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
        </span>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <main class="content">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
        <div class="edit-layout">

            <div class="edit-main">

                <div class="card">
                    <div class="card-title">Basic Information</div>
                    <div class="form-grid">

                        <div class="form-group">
                            <label>Dish Name</label>
                            <input type="text" name="name"
                                value="<?= htmlspecialchars($name ?? $product['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input type="hidden" name="category_id" value="<?= isset($category_id) ? $category_id : (int)$product['category_id'] ?>">
                            <input type="text" readonly value="<?php
                                $current_cat_id = isset($category_id) ? $category_id : (int)$product['category_id'];
                                $cat_name = '';
                                foreach ($categories as $cat) {
                                    if ((int)$cat['category_id'] === $current_cat_id) {
                                        $cat_name = $cat['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8');
                                ?>">
                        </div>

                        <div class="form-group full">
                            <label>Description</label>
                            <textarea name="description"><?= htmlspecialchars($description ?? $product['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" name="price" step="0.01"
                                value="<?= htmlspecialchars($price ?? $product['price'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input type="number" name="stock_quantity"
                                value="<?= htmlspecialchars(isset($stock) ? $stock : ($inventory['stock_quantity'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Customization Groups</div>
                    <div class="customization-section">

                        <?php if (empty($customizationGroups)): ?>
                            <p class="no-modifiers-notice">No customizations available. Create them first in the <a href="customizations.php" style="color:var(--dark);font-weight:600;">Customizations</a> page.</p>
                        <?php else: ?>
                            <div class="modifier-select-row">
                                <select id="new-modifier-group">
                                    <option value="">+ Add customization group…</option>
                                    <?php foreach ($customizationGroups as $custom): ?>
                                        <option value="<?= (int)$custom['modifier_group_id'] ?>"
                                                data-name="<?= htmlspecialchars($custom['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-pricing="<?= htmlspecialchars($custom['pricing_type'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-select="<?= htmlspecialchars($custom['select_option'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($custom['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div id="customization-groups">
                        <?php
                        $renderGroups = !empty($posted_group_names) ? $posted_group_names : [];
                        if (empty($renderGroups) && !empty($existing_groups)) {
                            foreach ($existing_groups as $group) {
                                $renderGroups[] = $group['name'];
                            }
                        }
                        if (!empty($renderGroups)):
                            foreach ($renderGroups as $groupIndex => $groupName):
                                $groupLabel = sanitize(trim($groupName));
                                $groupKey   = array_keys($existing_groups)[$groupIndex] ?? null;
                                $existingGroup = $groupKey !== null ? $existing_groups[$groupKey] : null;
                                $groupTypeValue = $posted_group_types[$groupIndex] ?? null;

                                // Metadata lookup moved outside conditional to ensure name and type are always accurate
                                $modifierGroupId = $posted_group_modifier_ids[$groupIndex] ?? null;
                                if (!$modifierGroupId && isset($existing_modifier_links[$groupIndex])) {
                                    $modifierGroupId = $existing_modifier_links[$groupIndex]['modifier_group_id'];
                                }
                                $modifierGroupId = $modifierGroupId ? (int)$modifierGroupId : '';
                                $modifierMeta = $modifierGroupId && isset($customizationMap[$modifierGroupId]) ? $customizationMap[$modifierGroupId] : null;

                                if (!in_array($groupTypeValue, ['addon', 'single'], true)) {
                                    if ($existingGroup) {
                                        $groupTypeValue = $existingGroup['group_type'] === 'addon' ? 'addon' : 'single';
                                    } else {
                                        $groupTypeValue    = $modifierMeta
                                            ? ($modifierMeta['select_option'] === 'multiple' ? 'addon' : 'single')
                                            : 'single';
                                    }
                                }
                                $groupRequiredValue = isset($posted_group_required[$groupIndex]) ? ($posted_group_required[$groupIndex] == '1') : ($existingGroup ? (bool)$existingGroup['is_required'] : false);

                                // Ensure we use the most accurate name available
                                if ($modifierMeta) {
                                    $groupLabel = $modifierMeta['name'];
                                } elseif ($existingGroup) {
                                    $groupLabel = $existingGroup['name'];
                                }
                                $pricingType  = $posted_group_pricing_type[$groupIndex] ?? ($existingGroup['pricing_type'] ?? ($modifierMeta['pricing_type'] ?? 'extra_charge'));
                                $optionNames  = $posted_option_names[$groupIndex] ?? [];
                                $optionPrices = $posted_option_prices[$groupIndex] ?? [];
                                $optionImages = $posted_option_images[$groupIndex] ?? [];
                                if (empty($optionNames) && !empty($existing_groups)) {
                                    $groupKey = array_keys($existing_groups)[$groupIndex] ?? null;
                                    if ($groupKey !== null) {
                                        foreach ($existing_groups[$groupKey]['options'] as $opt) {
                                            $optionNames[]  = $opt['name'];
                                            $optionPrices[] = $opt['additional_price'];
                                            $optionImages[] = $opt['image'] ?? '';
                                        }
                                    }
                                }
                                $badgeClass = $groupTypeValue === 'addon' ? 'badge-addon' : 'badge-single';
                                $badgeLabel = $groupTypeValue === 'addon' ? 'Multiple choice' : 'Single choice';
                        ?>
                            <div class="customization-card"
                                 data-index="<?= (int)$groupIndex ?>"
                                 data-modifier-id="<?= htmlspecialchars($modifierGroupId, ENT_QUOTES, 'UTF-8') ?>"
                                 data-type="<?= htmlspecialchars($groupTypeValue, ENT_QUOTES, 'UTF-8') ?>">

                                <div class="customization-card-header">
                                    <div class="customization-card-header-left">
                                        <span class="group-label"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="group-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                        <label style="display:flex;align-items:center;gap:6px;margin-left:12px;font-weight:500;font-size:13px;cursor:pointer;">
                                            <input type="checkbox" class="group-required-checkbox" data-group-index="<?= (int)$groupIndex ?>" <?= $groupRequiredValue ? 'checked' : '' ?>>
                                            Required
                                        </label>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger btn-remove-group">Remove</button>
                                </div>

                                <input type="hidden" name="group_modifier_id[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($modifierGroupId, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="group_name[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="group_type[<?= (int)$groupIndex ?>]" value="<?= $groupTypeValue ?>">
                                <input type="hidden" name="group_required[<?= (int)$groupIndex ?>]" value="<?= $groupRequiredValue ? '1' : '0' ?>">
                                <input type="hidden" name="group_pricing_type[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($pricingType, ENT_QUOTES, 'UTF-8') ?>">

                                <div class="customization-card-body options-list">
                                    <?php if (!empty($optionNames)): ?>
                                        <?php foreach ($optionNames as $optionIndex => $optionValue): ?>
                                            <div class="option-row">
                                                <div class="form-group">
                                                    <label>Option Name</label>
                                                    <input type="text" name="option_name[<?= (int)$groupIndex ?>][]"
                                                           value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Price (₱)</label>
                                                    <input type="number" step="0.01" name="option_price[<?= (int)$groupIndex ?>][]"
                                                           value="<?= htmlspecialchars($optionPrices[$optionIndex] ?? '0.00', ENT_QUOTES, 'UTF-8') ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Image</label>
                                                    <input type="file" name="option_image_file[<?= (int)$groupIndex ?>][]" accept="image/*">
                                                    <?php if (!empty($optionImages[$optionIndex])): ?>
                                                        <input type="hidden" name="option_image_existing[<?= (int)$groupIndex ?>][]"
                                                               value="<?= htmlspecialchars($optionImages[$optionIndex], ENT_QUOTES, 'UTF-8') ?>">
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

                    </div>
                </div>

            </div>

            <div class="edit-side">

                <div class="card">
                    <div class="card-title">Dish Image</div>
                    <?php if ($product['image']): ?>
                        <div class="img-preview-wrap">
                            <img src="/casa_gunita/assets/images/<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    <?php else: ?>
                        <div class="img-preview-wrap">
                            <span class="img-placeholder">No image</span>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Replace Image</label>
                        <input type="file" name="image" accept="image/*">
                        <span class="hint">Leave blank to keep current image.</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Availability</div>
                    <div class="availability-row">
                        <label>
                            <input type="checkbox" name="is_available"
                                <?= (isset($is_available) ? $is_available : $product['is_available']) ? 'checked' : '' ?>>
                            Available for ordering
                        </label>
                    </div>
                </div>

                <div class="save-bar">
                    <span class="save-bar-hint">Changes are saved immediately.</span>
                    <a href="menu.php?category_id=<?= $product['category_id'] ?>" class="btn btn-gray">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>

            </div>

        </div>
        </form>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
    const customizationSelect = document.getElementById('new-modifier-group');
    const availableCustoms = customizationSelect
        ? Array.from(customizationSelect.querySelectorAll('option[data-name]')).map(o => ({
            id:          o.value,
            name:        o.dataset.name,
            pricingType: 'extra_charge',
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
        const requiredCheckbox = card.querySelector('.group-required-checkbox');
        if (requiredCheckbox) {
            requiredCheckbox.addEventListener('change', () => {
                const groupIndex = card.dataset.index;
                const hiddenInput = card.querySelector(`input[name="group_required[${groupIndex}]"]`);
                if (hiddenInput) {
                    hiddenInput.value = requiredCheckbox.checked ? 1 : 0;
                }
            });
        }
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
                        <input type="checkbox" class="group-required-checkbox" data-group-index="${groupIndex}">
                        Required
                    </label>
                </div>
                <button type="button" class="btn btn-sm btn-danger btn-remove-group">Remove</button>
            </div>
            <input type="hidden" name="group_modifier_id[${groupIndex}]" value="${modifier.id}">
            <input type="hidden" name="group_name[${groupIndex}]" value="${modifier.name}">
            <input type="hidden" name="group_type[${groupIndex}]" value="${type}">
            <input type="hidden" name="group_required[${groupIndex}]" value="0">
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

    function updateCustomizationSelect() {
        if (!customizationSelect) return;
        const selectedIds = new Set(
            Array.from(container.querySelectorAll('.customization-card')).map(c => c.dataset.modifierId)
        );
        customizationSelect.querySelectorAll('option').forEach(opt => {
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

    if (customizationSelect) {
        customizationSelect.addEventListener('change', function () {
            const id = this.value;
            if (!id) return;
            const custom = availableCustoms.find(m => m.id === id);
            if (custom) {
                container.appendChild(createModifierCard(custom, getNextGroupIndex()));
                updateCustomizationSelect();
                this.value = '';
            }
        });
    }

    updateCustomizationSelect();
});
</script>
</body>
</html>