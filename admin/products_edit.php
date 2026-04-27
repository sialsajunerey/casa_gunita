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
    echo "Product not found.";
    exit();
}

// Fetch categories
$categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
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
    $sql = "SELECT option_id, group_id, name, additional_price
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

$posted_group_names = $_POST['group_name'] ?? [];
$posted_group_types = $_POST['group_type'] ?? [];
$posted_group_required = $_POST['group_required'] ?? [];
$posted_option_names = $_POST['option_name'] ?? [];
$posted_option_prices = $_POST['option_price'] ?? [];

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

            // Save new customization groups and options
            if (!empty($posted_group_names) && is_array($posted_group_names)) {
                $groupOrder = 0;
                $groupStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_groups
                     (product_id, name, group_type, is_required, display_order)
                     VALUES (?, ?, ?, ?, ?)");
                $groupOptionStmt = mysqli_prepare($conn,
                    "INSERT INTO product_customization_options
                     (group_id, name, additional_price, display_order)
                     VALUES (?, ?, ?, ?)");

                foreach ($posted_group_names as $groupIndex => $groupNameRaw) {
                    $groupName = sanitize($groupNameRaw);
                    if ($groupName === '') {
                        continue;
                    }
                    $groupType = in_array($posted_group_types[$groupIndex] ?? 'single', ['single', 'addon'], true)
                        ? $posted_group_types[$groupIndex]
                        : 'single';
                    $groupRequired = isset($posted_group_required[$groupIndex]) ? 1 : 0;

                    mysqli_stmt_bind_param($groupStmt, 'issii',
                        $id, $groupName, $groupType, $groupRequired, $groupOrder);
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
                            mysqli_stmt_bind_param($groupOptionStmt, 'isdi',
                                $groupId, $optionName, $optionPrice, $optionOrder);
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
            $error = "Failed to update product.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product — Casa Gunita Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .navbar {
            background: #8B0000; color: white;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .container { padding: 30px; max-width: 600px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type=text], input[type=number],
        textarea, select {
            width: 100%; padding: 10px;
            border: 1px solid #ddd; border-radius: 5px; font-size: 14px;
        }
        textarea { height: 80px; resize: vertical; }
        .btn-submit {
            background: #8B0000; color: white;
            padding: 12px 30px; border: none;
            border-radius: 5px; font-size: 16px;
            cursor: pointer; font-weight: bold;
        }
        .btn-submit:hover { background: #a00000; }
        .success { color: #27ae60; font-weight: bold; margin-bottom: 15px; }
        .error   { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        .current-img { margin-bottom: 10px; }
        .current-img img {
            width: 100px; height: 100px;
            object-fit: cover; border-radius: 5px;
            border: 2px solid #ddd;
        }
        .customization-card { border: 1px solid #ddd; border-radius: 12px; padding: 16px; margin-bottom: 16px; background: #fcfcfc; }
        .group-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
        .btn-remove-group, .btn-remove-option, .btn-add-option, #add-custom-group { background: #e74c3c; color: #fff; border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 14px; }
        .btn-add-option, #add-custom-group { background: #3498db; }
        .btn-remove-option { margin-top: 8px; }
        .option-item { border: 1px dashed #ddd; border-radius: 10px; padding: 12px; margin-bottom: 10px; background: #fff; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Edit Product</h2>
    <div>
        <a href="products.php">← Back to Products</a>
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>Edit: <?= $product['name'] ?></h3>

    <?php if ($error):   ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

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
            <label>Product Image</label>
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

        <div class="form-group">
            <label>Customization Groups</label>
            <div id="customization-groups">
                <?php
                $renderGroups = !empty($posted_group_names) ? $posted_group_names : [];
                if (empty($renderGroups) && !empty($existing_groups)) {
                    foreach ($existing_groups as $group) {
                        $renderGroups[] = $group['name'];
                    }
                }
                if (!empty($renderGroups)):
                    foreach ($renderGroups as $groupIndex => $groupNameValue):
                        $groupTypeValue = $posted_group_types[$groupIndex] ?? ($existing_groups[array_keys($existing_groups)[$groupIndex]]['group_type'] ?? 'single');
                        $groupRequiredValue = isset($posted_group_required[$groupIndex]) ? true : false;
                        $optionNames = $posted_option_names[$groupIndex] ?? [];
                        $optionPrices = $posted_option_prices[$groupIndex] ?? [];
                        if (empty($optionNames) && !empty($existing_groups)) {
                            $groupKey = array_keys($existing_groups)[$groupIndex] ?? null;
                            if ($groupKey !== null) {
                                foreach ($existing_groups[$groupKey]['options'] as $opt) {
                                    $optionNames[] = $opt['name'];
                                    $optionPrices[] = $opt['additional_price'];
                                }
                            }
                        }
                ?>
                        <div class="customization-card" data-index="<?= (int)$groupIndex ?>">
                            <div class="group-head">
                                <strong>Group #<?= (int)$groupIndex + 1 ?></strong>
                                <button type="button" class="btn-remove-group">Remove Group</button>
                            </div>
                            <div class="form-group">
                                <label>Group Name</label>
                                <input type="text" name="group_name[<?= (int)$groupIndex ?>]" value="<?= htmlspecialchars($groupNameValue, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Group Type</label>
                                <select name="group_type[<?= (int)$groupIndex ?>]">
                                    <option value="single" <?= $groupTypeValue === 'single' ? 'selected' : '' ?>>Single choice</option>
                                    <option value="addon" <?= $groupTypeValue === 'addon' ? 'selected' : '' ?>>Add-on / multiple choice</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="group_required[<?= (int)$groupIndex ?>]" value="1" <?= $groupRequiredValue ? 'checked' : '' ?> >
                                    Required selection
                                </label>
                            </div>
                            <div class="options-list">
                                <?php if (!empty($optionNames)): ?>
                                    <?php foreach ($optionNames as $optionIndex => $optionValue): ?>
                                        <div class="option-item">
                                            <div class="form-group">
                                                <label>Option Name</label>
                                                <input type="text" name="option_name[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Additional Price</label>
                                                <input type="number" step="0.01" name="option_price[<?= (int)$groupIndex ?>][]" value="<?= htmlspecialchars($optionPrices[$optionIndex] ?? '0.00', ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <button type="button" class="btn-remove-option">Remove Option</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn-add-option">Add Option</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" id="add-custom-group">Add Group</button>
            <p style="margin-top:6px; color:#666; font-size:14px;">Optional: groups appear when customers choose this product.</p>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available"
                    <?= (isset($is_available) ? $is_available : $product['is_available']) ? 'checked' : '' ?>>
                Available for ordering
            </label>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('customization-groups');
    const addBtn    = document.getElementById('add-custom-group');

    const typeConfig = {
        size:   { label: 'Size',    priceNote: 'Full price (₱)',       inputType: 'radio',    groupType: 'size'   },
        flavor: { label: 'Flavor',  priceNote: 'Full price (₱)',       inputType: 'radio',    groupType: 'flavor' },
        addon:  { label: 'Add-ons', priceNote: 'Additional price (₱)', inputType: 'checkbox', groupType: 'addon'  }
    };

    function makeOption(groupIndex, type) {
        const cfg = typeConfig[type];
        const wrap = document.createElement('div');
        wrap.className = 'option-item';
        wrap.style.cssText = 'display:grid;grid-template-columns:48px 1fr 120px auto;gap:8px;align-items:center;margin-bottom:10px;padding:10px;border:1px dashed #ddd;border-radius:8px;background:#fff';

        wrap.innerHTML = `
            <div class="img-thumb" style="width:44px;height:44px;border-radius:8px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;cursor:pointer;background:#fafafa;overflow:hidden;font-size:18px" title="Click to upload image">📷</div>
            <input type="hidden" name="option_image[${groupIndex}][]" value="">
            <div>
                <input type="text" name="option_name[${groupIndex}][]" placeholder="Option name" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;font-size:13px">
            </div>
            <div>
                <input type="number" step="0.01" min="0" name="option_price[${groupIndex}][]" placeholder="0.00" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;font-size:13px">
                <div style="font-size:11px;color:#999;margin-top:2px">${cfg.priceNote}</div>
            </div>
            <button type="button" style="background:#e74c3c;color:white;border:none;border-radius:6px;padding:6px 10px;cursor:pointer;font-size:13px">✕</button>
        `;

        // Image upload click
        const thumb = wrap.querySelector('.img-thumb');
        const hiddenImg = wrap.querySelector('input[type=hidden]');
        thumb.addEventListener('click', function () {
            const inp = document.createElement('input');
            inp.type = 'file'; inp.accept = 'image/*';
            inp.onchange = function (e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function (ev) {
                    thumb.innerHTML = `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover">`;
                    hiddenImg.value = ev.target.result; // base64 preview; handle upload server-side if needed
                };
                reader.readAsDataURL(file);
                // Replace hidden with actual file input for form submission
                const fileInp = document.createElement('input');
                fileInp.type = 'file'; fileInp.name = `option_image_file[${groupIndex}][]`;
                fileInp.style.display = 'none';
                fileInp.files = inp.files;
                wrap.appendChild(fileInp);
            };
            inp.click();
        });

        // Remove option
        wrap.querySelector('button').addEventListener('click', () => wrap.remove());

        return wrap;
    }

    function getNextGroupIndex() {
        let max = -1;
        container.querySelectorAll('.customization-card').forEach(c => {
            const i = parseInt(c.dataset.index, 10);
            if (!isNaN(i) && i > max) max = i;
        });
        return max + 1;
    }

    function makeCard(type, groupIndex) {
        const cfg = typeConfig[type];
        const card = document.createElement('div');
        card.className = 'customization-card';
        card.dataset.index = groupIndex;
        card.dataset.type  = type;

        const badgeColors = { size: '#d4e9ff;color:#1a5276', flavor: '#d4f5e2;color:#145a32', addon: '#fde8c8;color:#784212' };
        const [bgc, tc] = badgeColors[type].split(';color:');

        card.style.cssText = 'border:1px solid #ddd;border-radius:12px;padding:16px;margin-bottom:14px;background:#fcfcfc';
        card.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div style="display:flex;align-items:center;gap:10px">
                    <strong style="font-size:15px">${cfg.label}</strong>
                    <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:${bgc};color:#${tc};font-weight:600">
                        ${type === 'addon' ? 'multiple choice' : 'single choice'} &bull; ${type === 'addon' ? 'extra charge' : 'set price'}
                    </span>
                </div>
                <button type="button" class="btn-remove-group" style="background:none;border:1px solid #ddd;border-radius:6px;padding:5px 10px;cursor:pointer;color:#e74c3c;font-size:12px">Remove</button>
            </div>
            <input type="hidden" name="group_name[${groupIndex}]" value="${cfg.label}">
            <input type="hidden" name="group_type[${groupIndex}]" value="${cfg.groupType}">
            <input type="hidden" name="group_required[${groupIndex}]" value="${type !== 'addon' ? 1 : 0}">
            <div style="display:grid;grid-template-columns:48px 1fr 120px auto;gap:8px;padding:0 0 6px;font-size:11px;color:#999">
                <div>Photo</div><div>Name</div><div>${cfg.priceNote}</div><div></div>
            </div>
            <div class="options-list"></div>
            <button type="button" class="btn-add-option" style="width:100%;border:1px dashed #ccc;background:none;border-radius:8px;padding:8px;cursor:pointer;color:#666;font-size:13px;margin-top:4px">+ Add option</button>
        `;

        card.querySelector('.btn-remove-group').addEventListener('click', () => card.remove());
        card.querySelector('.btn-add-option').addEventListener('click', () => {
            card.querySelector('.options-list').appendChild(makeOption(groupIndex, type));
        });

        // Add one default option row
        card.querySelector('.options-list').appendChild(makeOption(groupIndex, type));

        return card;
    }

    addBtn.addEventListener('click', function () {
        const sel = document.getElementById('new-cust-type');
        if (!sel.value) { sel.focus(); return; }
        const idx = getNextGroupIndex();
        container.appendChild(makeCard(sel.value, idx));
        sel.value = '';
    });

    // ── For products_edit.php: pre-populate existing groups ──────────────
    // (Only include the block below in products_edit.php, not products_add.php)
    <?php if (!empty($existing_groups)): ?>
    <?php $gIdx = 0; foreach ($existing_groups as $group): ?>
    (function() {
        const type = <?= json_encode(in_array($group['group_type'], ['size','flavor','addon']) ? $group['group_type'] : 'addon') ?>;
        const idx  = <?= $gIdx ?>;
        const card = makeCard(type, idx);
        // Remove the default blank option row that makeCard adds
        card.querySelector('.options-list').innerHTML = '';

        <?php foreach ($group['options'] as $opt): ?>
        (function(){
            const row = makeOption(idx, type);
            row.querySelector('input[type=text]').value = <?= json_encode($opt['name']) ?>;
            row.querySelector('input[type=number]').value = <?= json_encode($opt['additional_price']) ?>;
            <?php if (!empty($opt['image'])): ?>
            const thumb = row.querySelector('.img-thumb');
            thumb.innerHTML = `<img src="/casa_gunita/assets/images/<?= htmlspecialchars($opt['image']) ?>" style="width:100%;height:100%;object-fit:cover">`;
            <?php endif; ?>
            card.querySelector('.options-list').appendChild(row);
        })();
        <?php endforeach; ?>

        container.appendChild(card);
    })();
    <?php $gIdx++; endforeach; ?>
    <?php endif; ?>
});
</script>
</body>
</html>