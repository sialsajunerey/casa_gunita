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
            $error = "Failed to add product. Please ensure the selected category still exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product — Casa Gunita Admin</title>
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
            border: 1px solid #ddd; border-radius: 5px;
            font-size: 14px;
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
    <h2 style="margin:0">🍽️ Casa Gunita — Add Product</h2>
    <div>
        <a href="products.php">← Back to Products</a>
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>Add New Product</h3>

    <?php if ($error):   ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label>Dish Name</label>
            <input type="text" name="name" placeholder="e.g. Adobong Manok"
                   value="<?= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Short description of the dish"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <label>Price (₱)</label>
            <input type="number" name="price" step="0.01"
                   value="<?= htmlspecialchars($price, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category_id">
                <?php if (empty($categories)): ?>
                    <option value="0">No categories available</option>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>" <?= $category_id === (int)$cat['category_id'] ? 'selected' : '' ?> >
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php if (empty($categories)): ?>
                <p style="color:#e74c3c; font-size:14px; margin-top:8px;">Add categories first under the categories admin page before adding products.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Stock Quantity</label>
            <input type="number" name="stock_quantity"
                   value="<?= htmlspecialchars($stock, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Product Image (optional)</label>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="form-group">
            <label>Customization Groups</label>
            <div id="customization-groups">
                <?php if (!empty($posted_group_names) && is_array($posted_group_names)): ?>
                    <?php foreach ($posted_group_names as $groupIndex => $groupNameValue): ?>
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
                                    <option value="single" <?= ($posted_group_types[$groupIndex] ?? '') === 'single' ? 'selected' : '' ?>>Single choice</option>
                                    <option value="addon" <?= ($posted_group_types[$groupIndex] ?? '') === 'addon' ? 'selected' : '' ?>>Add-on / multiple choice</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="group_required[<?= (int)$groupIndex ?>]" value="1" <?= isset($posted_group_required[$groupIndex]) ? 'checked' : '' ?>>
                                    Required selection
                                </label>
                            </div>
                            <div class="options-list">
                                <?php $optionNames = $posted_option_names[$groupIndex] ?? []; $optionPrices = $posted_option_prices[$groupIndex] ?? []; ?>
                                <?php if (!empty($optionNames) && is_array($optionNames)): ?>
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
                <input type="checkbox" name="is_available" <?= $is_available ? 'checked' : '' ?>>
                Available for ordering
            </label>
        </div>

        <button type="submit" class="btn-submit" <?= empty($categories) ? 'disabled' : '' ?>>Add Product</button>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('customization-groups');
                const addGroupButton = document.getElementById('add-custom-group');

                function createGroup(groupIndex, name = '', type = 'single', required = false) {
                    const group = document.createElement('div');
                    group.className = 'customization-card';
                    group.dataset.index = groupIndex;
                    group.innerHTML = `
                        <div class="group-head">
                            <strong>Group #${groupIndex + 1}</strong>
                            <button type="button" class="btn-remove-group">Remove Group</button>
                        </div>
                        <div class="form-group">
                            <label>Group Name</label>
                            <input type="text" name="group_name[${groupIndex}]" value="${name}" required>
                        </div>
                        <div class="form-group">
                            <label>Group Type</label>
                            <select name="group_type[${groupIndex}]">
                                <option value="single" ${type === 'single' ? 'selected' : ''}>Single choice</option>
                                <option value="addon" ${type === 'addon' ? 'selected' : ''}>Add-on / multiple choice</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="group_required[${groupIndex}]" value="1" ${required ? 'checked' : ''}>
                                Required selection
                            </label>
                        </div>
                        <div class="options-list"></div>
                        <button type="button" class="btn-add-option">Add Option</button>
                    `;
                    group.querySelector('.btn-remove-group').addEventListener('click', function() {
                        group.remove();
                    });
                    group.querySelector('.btn-add-option').addEventListener('click', function() {
                        addOptionToGroup(group, '', '0.00');
                    });
                    return group;
                }

                function addOptionToGroup(group, name = '', price = '0.00') {
                    const optionIndex = group.querySelectorAll('.option-item').length;
                    const groupIndex = group.dataset.index;
                    const optionsList = group.querySelector('.options-list');
                    const option = document.createElement('div');
                    option.className = 'option-item';
                    option.innerHTML = `
                        <div class="form-group">
                            <label>Option Name</label>
                            <input type="text" name="option_name[${groupIndex}][]" value="${name}" required>
                        </div>
                        <div class="form-group">
                            <label>Additional Price</label>
                            <input type="number" step="0.01" name="option_price[${groupIndex}][]" value="${price}">
                        </div>
                        <button type="button" class="btn-remove-option">Remove Option</button>
                    `;
                    option.querySelector('.btn-remove-option').addEventListener('click', function() {
                        option.remove();
                    });
                    optionsList.appendChild(option);
                }

                function getNextGroupIndex() {
                    let maxIndex = -1;
                    container.querySelectorAll('.customization-card').forEach(function(group) {
                        const index = parseInt(group.dataset.index, 10);
                        if (!Number.isNaN(index) && index > maxIndex) {
                            maxIndex = index;
                        }
                    });
                    return maxIndex + 1;
                }

                addGroupButton.addEventListener('click', function() {
                    const groupIndex = getNextGroupIndex();
                    const newGroup = createGroup(groupIndex);
                    container.appendChild(newGroup);
                    addOptionToGroup(newGroup);
                });

                container.querySelectorAll('.customization-card').forEach(function(group) {
                    const addOption = group.querySelector('.btn-add-option');
                    if (addOption) {
                        addOption.addEventListener('click', function() {
                            addOptionToGroup(group);
                        });
                    }
                    group.querySelectorAll('.btn-remove-option').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            btn.closest('.option-item').remove();
                        });
                    });
                });
            });
        </script>
        <?php if (empty($categories)): ?>
            <p style="color:#e74c3c; font-size:14px; margin-top:10px;">Cannot add products until categories exist. Run the category seed SQL or add categories manually.</p>
        <?php endif; ?>
    </form>
</div>

</body>
</html>