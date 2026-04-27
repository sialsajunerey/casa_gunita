<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    header('Location: menu.php');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT product_id, name, description, price, image FROM products WHERE product_id = ? AND is_available = 1");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$productResult = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($productResult);

if (!$product) {
    header('Location: menu.php');
    exit();
}

$groups = [];
$groupStmt = mysqli_prepare($conn, "SELECT group_id, name, group_type, is_required FROM product_customization_groups WHERE product_id = ? ORDER BY display_order, group_id");
mysqli_stmt_bind_param($groupStmt, 'i', $product_id);
mysqli_stmt_execute($groupStmt);
$groupResult = mysqli_stmt_get_result($groupStmt);
while ($group = mysqli_fetch_assoc($groupResult)) {
    $groups[$group['group_id']] = $group;
    $groups[$group['group_id']]['options'] = [];
}

if (!empty($groups)) {
    $groupIds = array_keys($groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT option_id, group_id, name, additional_price, image FROM product_customization_options WHERE group_id IN ($placeholders) ORDER BY display_order, option_id";
    $stmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('i', count($groupIds));
    $refs = [];
    foreach ($groupIds as $index => $groupId) {
        $refs[$index] = &$groupIds[$index];
    }
    array_unshift($refs, $types);
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));
    mysqli_stmt_execute($stmt);
    $optionResult = mysqli_stmt_get_result($stmt);
    while ($option = mysqli_fetch_assoc($optionResult)) {
        $groups[$option['group_id']]['options'][] = $option;
    }
}

function buildOptionLabel($option) {
    $label = htmlspecialchars($option['name']);
    if ((float)$option['additional_price'] > 0) {
        $label .= ' (' . htmlspecialchars('+' . number_format($option['additional_price'], 2)) . ')';
    }
    return $label;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customize — Casa Gunita</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar { background: #8B0000; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #fff; text-decoration: none; margin-left: 18px; }
        .navbar a:hover { text-decoration: underline; }
        .container { max-width: 980px; margin: 0 auto; padding: 30px 20px; }
        .card { background: #fff; border-radius: 20px; box-shadow: 0 12px 32px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { padding: 28px 30px 14px; }
        .product-top { display: grid; grid-template-columns: 300px 1fr; gap: 24px; align-items: start; }
        .product-image { width: 100%; min-height: 260px; border-radius: 20px; overflow: hidden; background: #fafafa; display: flex; align-items: center; justify-content: center; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-meta { display: flex; flex-direction: column; gap: 12px; }
        .product-meta h1 { margin: 0; font-size: 28px; color: #222; }
        .product-meta p { margin: 0; color: #555; line-height: 1.6; }
        .product-price { font-size: 24px; font-weight: 700; color: #8B0000; }
        .customization-section { background: #fff; padding: 24px 30px 30px; }
        .customization-group { margin-bottom: 24px; }
        .customization-group h3 { margin: 0 0 14px; font-size: 18px; color: #333; }
        .option-list { display: grid; gap: 14px; }
        .option-card { border: 1px solid #e6e6e6; border-radius: 16px; padding: 16px; display: flex; gap: 14px; align-items: center; background: #fafafa; cursor: pointer; }
        .option-card input { margin-right: 14px; }
        .option-content { flex: 1; }
        .option-name { font-weight: 600; color: #222; margin: 0 0 6px; }
        .option-price { color: #8B0000; font-weight: 700; }
        .option-image { width: 72px; height: 72px; border-radius: 14px; overflow: hidden; background: #fff; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .option-image img { width: 100%; height: 100%; object-fit: cover; }
        .submit-panel { padding: 20px 30px 32px; display: flex; justify-content: space-between; gap: 16px; align-items: center; background: #fafafa; border-top: 1px solid #eee; }
        .submit-panel button { background: #8B0000; color: #fff; border: none; padding: 14px 24px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .submit-panel button:hover { background: #a10000; }
        .helper-text { color: #555; font-size: 14px; margin-top: 10px; }
        @media (max-width: 820px) {
            .product-top { grid-template-columns: 1fr; }
            .product-image { min-height: 220px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>Casa Gunita</strong> — Customize</div>
        <div>
            <a href="menu.php">Menu</a>
            <a href="cart.php">Cart</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="product-top">
                    <div class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="../assets/images/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <span>No product image</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-meta">
                        <h1><?= htmlspecialchars($product['name']) ?></h1>
                        <p><?= nl2br(htmlspecialchars($product['description'] ?? 'Choose your options before adding to cart.')) ?></p>
                        <div class="product-price">Base: <?= formatPrice($product['price']) ?></div>
                        <?php if (empty($groups)): ?>
                            <p class="helper-text">This item has no extra customization options. Click Add to Cart to continue.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form method="POST" action="cart.php" class="customization-section">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
                <input type="hidden" name="quantity" value="1">

                <?php foreach ($groups as $group): ?>
                    <div class="customization-group">
                        <h3><?= htmlspecialchars($group['name']) ?><?= $group['is_required'] ? ' *' : '' ?></h3>
                        <div class="option-list">
                            <?php foreach ($group['options'] as $option): ?>
                                <?php $inputName = 'option_ids[' . $group['group_id'] . ']' . ($group['group_type'] === 'addon' ? '[]' : ''); ?>
                                <label class="option-card">
                                    <input
                                        type="<?= $group['group_type'] === 'addon' ? 'checkbox' : 'radio' ?>"
                                        name="<?= $inputName ?>"
                                        value="<?= htmlspecialchars($option['option_id']) ?>"
                                        <?= $group['group_type'] !== 'addon' ? 'required' : '' ?>
                                    >
                                    <div class="option-content">
                                        <p class="option-name"><?= htmlspecialchars($option['name']) ?></p>
                                        <p class="option-price"><?= $option['additional_price'] > 0 ? '+ ' . formatPrice($option['additional_price']) : 'No extra charge' ?></p>
                                    </div>
                                    <?php if (!empty($option['image'])): ?>
                                        <div class="option-image">
                                            <img src="../assets/images/<?= htmlspecialchars($option['image']) ?>" alt="<?= htmlspecialchars($option['name']) ?>">
                                        </div>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="submit-panel">
                    <div>
                        <strong>Note:</strong> Required groups are marked with an asterisk.
                    </div>
                    <button type="submit">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
