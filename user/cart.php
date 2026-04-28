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
        $id       = (int)$_POST['product_id'];
        $quantity = max(1, (int)$_POST['quantity']);
        $isAjax   = isset($_POST['ajax']) && $_POST['ajax'] == '1';

        $stmt = mysqli_prepare($conn, "SELECT product_id, name, price FROM products WHERE product_id = ? AND is_available = 1");
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
            $sql = "SELECT o.option_id, o.name, o.additional_price, g.name AS group_name, g.group_type
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
                    'option_id' => (int)$option['option_id'],
                    'group_name' => $option['group_name'],
                    'group_type' => $option['group_type'],
                    'name' => $option['name'],
                    'additional_price' => $optionPrice
                ];

                if ($option['group_type'] === 'addon') {
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
                'name' => $product['name'],
                'base_price' => $basePrice,
                'price' => $finalPrice,
                'quantity' => $quantity,
                'options' => $options
            ];
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'count' => getCartItemCount($_SESSION['cart'])
            ]);
            exit();
        }

        header('Location: cart.php');
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
        $id = $_POST['product_id'];
        unset($_SESSION['cart'][$id]);

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
<html>
<head>
    <title>Cart — Casa Gunita</title>
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
            --radius: 16px;
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
        .navbar a:hover { opacity: .88; }
        .container {
            padding: 32px 24px;
            max-width: 1180px;
            margin: 0 auto;
        }
        .page-title {
            margin: 0 0 20px;
            color: var(--crimson);
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
        }
        .cart-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .cart-summary .panel {
            background: var(--surface);
            padding: 22px 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            min-width: 220px;
        }
        .cart-summary .panel h3 { margin: 0 0 8px; font-size: 1rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .cart-summary .panel p { margin: 0; font-size: 1.6rem; font-weight: 700; color: var(--ink); }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            background: var(--surface);
        }
        .cart-table th, .cart-table td {
            padding: 18px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }
        .cart-table th {
            background: var(--crimson-d);
            color: var(--gold);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: .08em;
        }
        .cart-table tr:last-child td { border-bottom: none; }
        .cart-table tr:nth-child(even) { background: #fbf5e8; }
        .item-options { margin-top: 10px; font-size: 0.95rem; color: var(--muted); line-height: 1.5; }
        .item-options div { margin-top: 6px; }
        .quantity-input {
            width: 90px;
            padding: 10px 12px;
            border: 1px solid #d6d2d9;
            border-radius: 12px;
            font-family: inherit;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            transition: transform .15s ease, opacity .15s ease;
            text-decoration: none;
        }
        .btn-primary { background: var(--crimson); color: #fff; }
        .btn-primary:hover { opacity: .95; transform: translateY(-1px); }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-secondary:hover { opacity: .92; }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }
        .empty-cart {
            background: var(--surface);
            padding: 36px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow);
        }
        .empty-cart a { color: var(--crimson); font-weight: 700; }
        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0; }
        @media (max-width: 820px) {
            .cart-table th, .cart-table td { padding: 14px 12px; }
            .page-title { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>Casa Gunita</strong> — Cart</div>
        <div>
            <a href="menu.php">Menu</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Your Cart</h1>

        <div class="cart-summary">
            <div class="panel">
                <h3>🛒 Items in Cart</h3>
                <p><strong id="cart-item-count"><?= getCartItemCount($_SESSION['cart']) ?></strong></p>
            </div>
            <div class="panel">
                <h3>Estimated total</h3>
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
                                                    (<?= isset($option['group_type']) && $option['group_type'] === 'addon' ? '+ ' : '' ?><?= formatPrice($option['additional_price']) ?>)
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
                                    <input class="quantity-input" type="number" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="0" max="99" data-price="<?= htmlspecialchars($item['price']) ?>" data-row="<?= htmlspecialchars($key) ?>">
                                </form>
                            </td>
                            <td class="subtotal-cell" data-row="<?= htmlspecialchars($key) ?>"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_key" value="<?= htmlspecialchars($key) ?>">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id'] ?? $key) ?>">
                                    <button type="submit" class="secondary-button">Remove</button>
                                </form>
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
                const subtotal = price * qty;
                cell.textContent = formatCurrency(subtotal);

                total += subtotal;
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
                timer = setTimeout(function() {
                    form.submit();
                }, 500);
            });
        });

        updateCartTotals();
    </script>
</body>
</html>