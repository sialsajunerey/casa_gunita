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

        $stmt = mysqli_prepare($conn, "SELECT name, price FROM products WHERE product_id = ? AND is_available = 1");
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

        $name  = $product['name'];
        $price = (float)$product['price'];

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$id] = [
                'name'     => $name,
                'price'    => $price,
                'quantity' => $quantity
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
        $id  = (int)$_POST['product_id'];
        $qty = max(0, (int)$_POST['quantity']);

        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } elseif (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['quantity'] = $qty;
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
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar { background: #8B0000; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #fff; text-decoration: none; margin-left: 18px; }
        .navbar a:hover { text-decoration: underline; }
        .container { padding: 30px; max-width: 1100px; margin: 0 auto; }
        .page-title { margin: 0 0 16px; color: #333; }
        .cart-summary { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .cart-summary .panel { background: #fff; padding: 18px 22px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); flex: 1; min-width: 220px; }
        .cart-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 10px; overflow: hidden; }
        .cart-table th, .cart-table td { padding: 16px 14px; border-bottom: 1px solid #eee; text-align: left; }
        .cart-table th { background: #fafafa; color: #555; }
        .cart-table tr:last-child td { border-bottom: none; }
        .cart-table button { background: #8B0000; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; }
        .cart-table button:hover { background: #a10000; }
        .secondary-button { background: #555; }
        .action-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .action-row a button { background: #8B0000; }
        .empty-cart { background: #fff; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .empty-cart a { color: #8B0000; text-decoration: none; font-weight: bold; }
        .quantity-input { width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
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
                    <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= formatPrice($item['price']) ?></td>
                            <td>
                                <form method="POST" style="display:flex; gap:8px; align-items:center; margin:0;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($id) ?>">
                                    <input class="quantity-input" type="number" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="0" max="99" data-price="<?= htmlspecialchars($item['price']) ?>" data-row="<?= htmlspecialchars($id) ?>">
                                </form>
                            </td>
                            <td class="subtotal-cell" data-row="<?= htmlspecialchars($id) ?>"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($id) ?>">
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
                    <button type="submit" class="secondary-button">Clear Cart</button>
                </form>
                <a href="checkout.php"><button type="button">Proceed to Checkout →</button></a>
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