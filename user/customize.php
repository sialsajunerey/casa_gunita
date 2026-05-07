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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="customize.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=EB+Garamond:wght@400;500&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </div>
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search">
    </div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="index.php#about">About</a>
    </div>
    <div class="nav-icons">
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && getCartItemCount($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= getCartItemCount($_SESSION['cart']) ?></span>
            <?php endif; ?>
        </a>
        <div class="account-wrap">
            <button class="nav-icon-btn" id="accountBtn" aria-label="Account">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </button>
            <div class="account-dropdown" id="accountDropdown">
                <a href="account.php">Account Information</a>
                <a href="order_status.php">My Orders</a>
                <hr>
                <a href="logout.php">Log Out</a>
            </div>
        </div>
    </div>
</nav>

<!-- ===== CONTENT ===== -->
<div class="customize-content">
    <div class="customize-card">

        <!-- Product Top -->
        <div class="product-top">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="../assets/images/<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="product-image-placeholder" style="display:none;">No image available</div>
                <?php else: ?>
                    <div class="product-image-placeholder">No image available</div>
                <?php endif; ?>
            </div>
            <div class="product-meta">
                <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="product-desc"><?= nl2br(htmlspecialchars($product['description'] ?? 'Choose your options before adding to cart.')) ?></p>
                <div class="product-price">Price: <?= formatPrice($product['price']) ?></div>
                <?php if (empty($groups)): ?>
                    <p class="helper-text">This item has no extra customization options. Click Add to Cart to continue.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customization Form -->
        <form method="POST" action="cart.php" class="customization-section">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
            <input type="hidden" name="quantity" value="1">

            <?php foreach ($groups as $group): ?>
                <div class="customization-group">
                    <h3 class="group-title">
                        <?= htmlspecialchars($group['name']) ?>
                        <?= $group['is_required'] ? '<span class="required-star">*</span>' : '' ?>
                    </h3>
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
                                    <p class="option-price">
                                        <?php if ($group['group_type'] === 'addon'): ?>
                                            <?= $option['additional_price'] > 0 ? '+ ' . formatPrice($option['additional_price']) : 'No extra charge' ?>
                                        <?php else: ?>
                                            <?= $option['additional_price'] > 0 ? formatPrice($option['additional_price']) : 'Included' ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if (!empty($option['image'])): ?>
                                    <div class="option-image">
                                        <img src="../assets/images/<?= htmlspecialchars($option['image']) ?>"
                                             alt="<?= htmlspecialchars($option['name']) ?>"
                                             onerror="this.style.display='none';">
                                    </div>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="submit-panel">
                <p class="submit-note"><strong>Note:</strong> Required groups are marked with an asterisk.</p>
                <button type="submit" class="btn-add-cart">Add to Cart</button>
            </div>
        </form>

    </div>
</div>

<script>
    // Account dropdown
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    accountBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });
    document.addEventListener('click', function() {
        accountDropdown.classList.remove('open');
    });
</script>

</body>
</html>