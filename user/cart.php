<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

requireCustomer();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id          = (int)$_POST['product_id'];
        $quantity    = max(1, (int)$_POST['quantity']);
        $editCartKey = trim($_POST['edit_cart_key'] ?? $_POST['cart_key'] ?? '');
        $isAjax      = isset($_POST['ajax']) && $_POST['ajax'] == '1';

        if ($editCartKey !== '' && isset($_SESSION['cart'][$editCartKey])) {
            $quantity = $_SESSION['cart'][$editCartKey]['quantity'];
            unset($_SESSION['cart'][$editCartKey]);
        }

        $stmt = mysqli_prepare($conn, "SELECT product_id, name, price, category_id FROM products WHERE product_id = ? AND is_available = 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);

        if (!$product) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false]);
                exit();
            }
            header('Location: menu.php');
            exit();
        }

        $basePrice = (float)$product['price'];
        $selectedOptionIds = [];
        if (!empty($_POST['option_ids']) && is_array($_POST['option_ids'])) {
            foreach ($_POST['option_ids'] as $groupOptions) {
                if (is_array($groupOptions)) {
                    foreach ($groupOptions as $optId) {
                        $selectedOptionIds[] = (int)$optId;
                    }
                } else {
                    $selectedOptionIds[] = (int)$groupOptions;
                }
            }
        }
        $selectedOptionIds = array_values(array_filter(array_unique($selectedOptionIds)));

        $options = [];
        $optionsTotal = 0.0;
        $replacementPrice = null;
        if (!empty($selectedOptionIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedOptionIds), '?'));
            $types = str_repeat('i', count($selectedOptionIds));
            $sql = "SELECT o.option_id, o.name, o.additional_price, g.name AS group_name, g.group_type, g.pricing_type
                    FROM product_customization_options o
                    JOIN product_customization_groups g ON o.group_id = g.group_id
                    WHERE o.option_id IN ($placeholders)";
            $stmt = mysqli_prepare($conn, $sql);
            $bindParams = [];
            foreach ($selectedOptionIds as $index => $value) {
                $bindParams[$index] = &$selectedOptionIds[$index];
            }
            array_unshift($bindParams, $types);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bindParams));
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($option = mysqli_fetch_assoc($result)) {
                $optionPrice = (float)$option['additional_price'];
                $options[] = [
                    'option_id'        => (int)$option['option_id'],
                    'group_name'       => $option['group_name'],
                    'group_type'       => $option['group_type'],
                    'pricing_type'     => $option['pricing_type'] ?? 'set_price',
                    'name'             => $option['name'],
                    'additional_price' => $optionPrice
                ];
                if ($option['group_type'] === 'addon' || ($option['pricing_type'] ?? 'set_price') === 'extra_charge') {
                    $optionsTotal += $optionPrice;
                } elseif ($optionPrice > 0) {
                    $replacementPrice = $replacementPrice === null ? $optionPrice : max($replacementPrice, $optionPrice);
                }
            }
        }

        $finalPrice = ($replacementPrice !== null ? $replacementPrice : $basePrice) + $optionsTotal;
        $itemKey = $id . '_' . (empty($selectedOptionIds) ? 'default' : md5(json_encode($selectedOptionIds)));

        if (isset($_SESSION['cart'][$itemKey])) {
            $_SESSION['cart'][$itemKey]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$itemKey] = [
                'product_id' => $id,
                'name'       => $product['name'],
                'base_price' => $basePrice,
                'price'      => $finalPrice,
                'quantity'   => $quantity,
                'options'    => $options
            ];
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => getCartItemCount($_SESSION['cart'])]);
            exit();
        }

        $categoryId = (int)$product['category_id'];
        header('Location: menu.php?category_id=' . $categoryId);
        exit();
    }

    if ($action === 'update') {
        $key = $_POST['cart_key'] ?? $_POST['product_id'];
        $qty = max(0, (int)$_POST['quantity']);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$key]);
        } elseif (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
        header('Location: cart.php');
        exit();
    }

    if ($action === 'remove') {
        $key = $_POST['cart_key'] ?? $_POST['product_id'];
        unset($_SESSION['cart'][$key]);
        header('Location: cart.php');
        exit();
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: cart.php');
        exit();
    }
}

function getCartTotalAmount($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="cart.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=EB+Garamond:wght@400;500&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </div>
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu..." id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
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
<div class="container">
    <h1 class="page-title">Your Cart</h1>

    <div class="cart-summary">
        <div class="panel">
            <h3>Items in Cart</h3>
            <p><strong id="cart-item-count"><?= getCartItemCount($_SESSION['cart']) ?></strong></p>
        </div>
        <div class="panel">
            <h3>Estimated Total</h3>
            <p><strong id="cart-total-value"><?= formatPrice(getCartTotalAmount($_SESSION['cart'])) ?></strong></p>
        </div>
    </div>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-cart">
            <h2>Your cart is empty</h2>
            <p>Browse the menu and add meals to your cart.</p>
            <p><a href="menu.php">Return to menu →</a></p>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['name']) ?>
                        <?php if (!empty($item['options']) && is_array($item['options'])): ?>
                            <div class="item-options">
                                <?php foreach ($item['options'] as $option): ?>
                                    <div>
                                        <?= htmlspecialchars($option['group_name'] . ': ' . $option['name']) ?>
                                        <?php if (!empty($option['additional_price'])): ?>
                                            <?php if (isset($option['group_type']) && $option['group_type'] === 'addon'): ?>
                                                (+<?= formatPrice($option['additional_price']) ?>)
                                            <?php else: ?>
                                                (<?= formatPrice($option['additional_price']) ?>)
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= formatPrice($item['price']) ?></td>
                    <td>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($key) ?>">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? $key) ?>">
                            <input class="quantity-input" type="number" name="quantity"
                                   value="<?= htmlspecialchars($item['quantity']) ?>"
                                   min="0" max="99"
                                   data-price="<?= htmlspecialchars($item['price']) ?>"
                                   data-row="<?= htmlspecialchars($key) ?>">
                        </form>
                    </td>
                    <td class="subtotal-cell" data-row="<?= htmlspecialchars($key) ?>">
                        <?= formatPrice($item['price'] * $item['quantity']) ?>
                    </td>
                    <td>
                        <div class="cart-actions">
                            <a href="customize.php?product_id=<?= htmlspecialchars($item['product_id']) ?>&cart_key=<?= urlencode($key) ?>" class="secondary-button edit-button">Edit</a>
                            <form method="POST" style="margin:0; display:inline-flex;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_key" value="<?= htmlspecialchars($key) ?>">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? $key) ?>">
                                <button type="submit" class="secondary-button remove-button">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="action-row">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-secondary">Clear Cart</button>
            </form>
            <a href="checkout.php" class="btn btn-primary">Proceed to Checkout →</a>
        </div>
    <?php endif; ?>
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

    // Cart totals
    function formatCurrency(value) {
        return '₱' + value.toFixed(2);
    }

    function updateCartTotals() {
        let total = 0;
        let itemCount = 0;
        document.querySelectorAll('.subtotal-cell').forEach(function(cell) {
            const row = cell.dataset.row;
            const qtyInput = document.querySelector('.quantity-input[data-row="' + row + '"]');
            if (!qtyInput) return;
            const price = parseFloat(qtyInput.dataset.price) || 0;
            const qty = Math.max(0, parseInt(qtyInput.value) || 0);
            cell.textContent = formatCurrency(price * qty);
            total += price * qty;
            itemCount += qty;
        });
        const cartTotal = document.getElementById('cart-total-value');
        const cartCount = document.getElementById('cart-item-count');
        if (cartTotal) cartTotal.textContent = formatCurrency(total);
        if (cartCount) cartCount.textContent = itemCount;
    }

    document.querySelectorAll('.quantity-input').forEach(function(input) {
        let timer;
        input.addEventListener('input', function() {
            updateCartTotals();
            const form = input.closest('form');
            if (!form) return;
            clearTimeout(timer);
            timer = setTimeout(function() { form.submit(); }, 500);
        });
    });

    updateCartTotals();
</script>

<script src="search.js"></script>

</body>
</html>