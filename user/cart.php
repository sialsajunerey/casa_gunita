<?php
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/auth_modal_handler.php';

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

        if ($editCartKey !== '') {
            header('Location: cart.php');
            exit();
        }

        $categoryId = (int)$product['category_id'];
        header('Location: menu.php?category_id=' . $categoryId);
        exit();
    }

    if ($action === 'update') {
        $key = $_POST['cart_key'] ?? $_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        if (isset($_SESSION['cart'][$key])) {
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
    <link rel="stylesheet" href="cart.css?v=1.4">
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
        <?php if (isset($_SESSION['user_id'])): ?>
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
        <?php else: ?>
            <button class="nav-auth-btn" onclick="openAuthModal('login')">Login</button>
            <button class="nav-auth-btn reg" onclick="openAuthModal('register')">Register</button>
        <?php endif; ?>
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
            <h3>Total price</h3>
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
                    <th>Subtotal</th>
                    <th>Quantity</th>
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
                                            (+<?= formatPrice($option['additional_price']) ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="subtotal-cell" data-row="<?= htmlspecialchars($key) ?>">
                        <?= formatPrice($item['price'] * $item['quantity']) ?>
                    </td>
                    <td>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($key) ?>">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? $key) ?>">
                            <div class="quantity-box">
                                <button type="button" class="qty-btn qty-minus" onclick="decreaseQty(this)">−</button>
                                <input class="quantity-input" type="number" name="quantity"
                                       value="<?= htmlspecialchars($item['quantity']) ?>"
                                       min="1" max="99"
                                       data-price="<?= htmlspecialchars($item['price']) ?>"
                                       data-row="<?= htmlspecialchars($key) ?>">
                                <button type="button" class="qty-btn qty-plus" onclick="increaseQty(this)">+</button>
                            </div>
                        </form>
                    </td>
                    <td>
                        <div class="cart-actions">
                            <a href="customize.php?product_id=<?= htmlspecialchars($item['product_id']) ?>&cart_key=<?= urlencode($key) ?>" class="secondary-button btn-edit">Edit</a>
                            <form method="POST" style="margin:0; display:inline-flex;">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_key" value="<?= htmlspecialchars($key) ?>">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? $key) ?>">
                                <button type="submit" class="secondary-button btn-remove">Remove</button>
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
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="checkout.php" class="btn btn-primary">Proceed to Checkout →</a>
            <?php else: ?>
                <button type="button" class="btn btn-primary" onclick="openAuthModal('login', 'checkout.php')">Proceed to Checkout →</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== AUTH MODAL OVERLAY ===== -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal-card">
        <button class="auth-modal-close" onclick="closeAuthModal()">✕</button>
        <div id="loginView">
            <h1 class="auth-modal-title">Log In</h1>
            <p class="auth-modal-subtitle">Welcome back. Enter your details to continue.</p>

            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'login'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="login">
                <input type="hidden" name="redirect_to" class="auth-redirect-input" value="">
                <div class="auth-modal-field"><input type="email" name="email" placeholder="Email" required></div>
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <button type="submit" class="auth-modal-btn">Login</button>
            </form>
            <p class="auth-modal-footer">No account yet? <a href="javascript:void(0)" onclick="showAuthView('register')">Register</a></p>
        </div>
        <div id="registerView" style="display:none;">
            <h1 class="auth-modal-title">Sign Up</h1>
            <p class="auth-modal-subtitle">Join us for authentic Filipino favorites.</p>

            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'register'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="register">
                <input type="hidden" name="redirect_to" class="auth-redirect-input" value="">
                <div class="auth-modal-field"><input type="text" name="first_name" placeholder="First Name" required pattern="[A-Za-z.\-]+" title="Only letters, dots, and hyphens allowed"></div>
                <div class="auth-modal-field"><input type="text" name="last_name" placeholder="Last Name" required pattern="[A-Za-z.\-]+" title="Only letters, dots, and hyphens allowed"></div>
                <div class="auth-modal-field"><input type="email" name="email" placeholder="Email" required></div>
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <div class="auth-modal-field password-field"><input type="password" name="confirm_password" placeholder="Confirm Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <button type="submit" class="auth-modal-btn">Register</button>
            </form>
            <p class="auth-modal-footer">Already have an account? <a href="javascript:void(0)" onclick="showAuthView('login')">Login</a></p>
        </div>
    </div>
</div>

<script>
    function openAuthModal(view, redirectTo = '') {
        document.getElementById('authModal').classList.add('active');
        showAuthView(view);
        document.querySelectorAll('.auth-redirect-input').forEach(input => {
            input.value = redirectTo;
        });
    }
    function closeAuthModal() {
        document.getElementById('authModal').classList.remove('active');
    }
    function showAuthView(view) {
        document.getElementById('loginView').style.display = (view === 'login') ? 'block' : 'none';
        document.getElementById('registerView').style.display = (view === 'register') ? 'block' : 'none';
    }
    function initAuthPasswordToggles() {
        document.querySelectorAll('.auth-modal-field.password-field .password-toggle').forEach(button => {
            const field = button.closest('.auth-modal-field.password-field');
            const input = field ? field.querySelector('input') : null;
            if (!input) return;

        const eyeOpen = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle><line x1="2" y1="2" x2="22" y2="22"></line></svg>';

        button.innerHTML = input.type === 'password' ? eyeOpen : eyeClosed;

            button.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
            button.innerHTML = show ? eyeClosed : eyeOpen;
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });
    }
    initAuthPasswordToggles();
    window.onclick = function(event) {
        if (event.target == document.getElementById('authModal')) closeAuthModal();
    }

    <?php if ($auth_error): ?>
    document.addEventListener('DOMContentLoaded', () => {
        openAuthModal('<?= htmlspecialchars($_POST['auth_type']) ?>', '<?= htmlspecialchars($_POST['redirect_to'] ?? '') ?>');
    });
    <?php endif; ?>

    // Account dropdown
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accountDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            accountDropdown.classList.remove('open');
        });
    }

    // Cart totals
    function formatCurrency(value) {
        return '₱' + value.toFixed(2);
    }

    function decreaseQty(btn) {
        const input = btn.nextElementSibling;
        const val = Math.max(1, parseInt(input.value) - 1);
        input.value = val;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function increaseQty(btn) {
        const input = btn.previousElementSibling;
        const val = Math.min(99, parseInt(input.value) + 1);
        input.value = val;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function updateCartTotals() {
        let total = 0;
        let itemCount = 0;
        document.querySelectorAll('.subtotal-cell').forEach(function(cell) {
            const row = cell.dataset.row;
            const qtyInput = document.querySelector('.quantity-input[data-row="' + row + '"]');
            if (!qtyInput) return;
            const price = parseFloat(qtyInput.dataset.price) || 0;
            const qty = Math.max(1, parseInt(qtyInput.value) || 1);
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
            if (this.value !== "" && this.value < 1) this.value = 1;
            updateCartTotals();
            const form = input.closest('form');
            if (!form || input.value < 1) return;
            clearTimeout(timer);
            timer = setTimeout(function() { form.submit(); }, 500);
        });
    });

    updateCartTotals();
</script>

<script src="search.js"></script>
<script src="order-status-overlay.js"></script>

<?php include_once '../includes/order_status_overlay.php'; ?>

</body>
</html>