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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --crimson: #210303;
            --crimson-d: #130301;
            --gold: #e8d191;
            --ink: #130301;
            --muted: #674328;
            --line: rgba(33,3,3,.1);
            --surface: #fff8eb;
            --bg: #f4f2ea;
            --radius: 18px;
            --shadow: 0 18px 50px rgba(33,3,3,.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        a { color: inherit; text-decoration: none; }
        .navbar {
            background: var(--crimson);
            color: #fff;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar a { color: rgba(255,255,255,.92); margin-left: 18px; font-weight: 600; }
        .navbar a:hover { opacity: .9; }
        .container { max-width: 980px; margin: 0 auto; padding: 32px 24px; }
        .card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { padding: 28px 30px 14px; }
        .product-top { display: grid; grid-template-columns: minmax(260px, 1fr) 1.2fr; gap: 24px; align-items: start; }
        .product-image { width: 100%; min-height: 280px; border-radius: 20px; overflow: hidden; background: #fff5ed; display: flex; align-items: center; justify-content: center; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-meta { display: flex; flex-direction: column; gap: 14px; }
        .product-meta h1 { margin: 0; font-size: 2.2rem; color: var(--crimson); font-family: 'Playfair Display', serif; }
        .product-meta p { margin: 0; color: var(--muted); line-height: 1.7; }
        .product-price { font-size: 1.4rem; font-weight: 700; color: var(--crimson); }
        .customization-section { background: #fff; padding: 28px 30px 30px; }
        .customization-group { margin-bottom: 26px; }
        .customization-group h3 { margin: 0 0 14px; font-size: 1.1rem; color: var(--ink); }
        .option-list { display: grid; gap: 14px; }
        .option-card { border: 1px solid #e7dfd2; border-radius: 18px; padding: 16px; display: flex; gap: 14px; align-items: center; background: #faf7f2; cursor: pointer; transition: transform .15s ease, border-color .15s ease; }
        .option-card:hover { transform: translateY(-1px); border-color: var(--crimson); }
        .option-card input { margin-right: 14px; accent-color: var(--crimson); }
        .option-content { flex: 1; }
        .option-name { font-weight: 700; color: var(--ink); margin: 0 0 6px; }
        .option-price { color: var(--crimson); font-weight: 700; }
        .option-image { width: 74px; height: 74px; border-radius: 16px; overflow: hidden; background: #fff; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .option-image img { width: 100%; height: 100%; object-fit: cover; }
        .submit-panel { padding: 22px 30px 32px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 18px; align-items: center; background: #fdf8f2; border-top: 1px solid #e7dfd2; }
        .submit-panel button { background: var(--crimson); color: #fff; border: none; padding: 14px 24px; border-radius: 16px; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .submit-panel button:hover { background: #4b0101; }
        .helper-text { color: var(--muted); font-size: 0.95rem; margin-top: 10px; }
        @media (max-width: 900px) { .product-top { grid-template-columns: 1fr; } }
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
                                        <p class="option-price"><?php if ($group['group_type'] === 'addon'): ?>
                                                <?= $option['additional_price'] > 0 ? '+ ' . formatPrice($option['additional_price']) : 'No extra charge' ?>
                                            <?php else: ?>
                                                <?= $option['additional_price'] > 0 ? formatPrice($option['additional_price']) : 'Included' ?>
                                            <?php endif; ?></p>
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
