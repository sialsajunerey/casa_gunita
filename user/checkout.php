<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: menu.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type = $_POST['order_type'];
    $notes      = sanitize($_POST['notes']);
    $user_id    = $_SESSION['user_id'];
    $cart       = $_SESSION['cart'];
    $total      = getCartTotal($cart);

    $stmt = mysqli_prepare($conn,
        "INSERT INTO orders (user_id, total_amount, status, order_type, notes) 
         VALUES (?, ?, 'pending', ?, ?)");
    mysqli_stmt_bind_param($stmt, 'idss', $user_id, $total, $order_type, $notes);

    if (mysqli_stmt_execute($stmt)) {
        $order_id = mysqli_insert_id($conn);

        foreach ($cart as $product_id => $item) {
            $subtotal = $item['price'] * $item['quantity'];

            $item_stmt = mysqli_prepare($conn,
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($item_stmt, 'iiidd',
                $order_id, $product_id, $item['quantity'], $item['price'], $subtotal);
            mysqli_stmt_execute($item_stmt);

            reduceStock($conn, $product_id, $item['quantity']);
        }

        $_SESSION['cart'] = [];
        header('Location: receipt.php?order_id=' . $order_id);
        exit();
    } else {
        $error = 'Order failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout — Casa Gunita</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar { background: #8B0000; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #fff; text-decoration: none; margin-left: 18px; }
        .navbar a:hover { text-decoration: underline; }
        .container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        .page-title { margin: 0 0 20px; color: #333; }
        .checkout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .panel { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 2px 14px rgba(0,0,0,0.08); }
        .panel h2 { margin-top: 0; color: #8B0000; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table th, .summary-table td { padding: 14px 12px; border-bottom: 1px solid #eee; text-align: left; }
        .summary-table th { background: #fafafa; color: #555; }
        .summary-table tr:last-child td { border-bottom: none; }
        .summary-table .total-row td { font-size: 18px; font-weight: bold; }
        .form-field { margin-bottom: 18px; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-field textarea { resize: vertical; min-height: 140px; }
        .button-primary { background: #8B0000; color: #fff; border: none; border-radius: 8px; padding: 14px 20px; cursor: pointer; font-size: 16px; }
        .button-primary:hover { background: #a10000; }
        .button-secondary { display: inline-block; margin-top: 18px; color: #8B0000; font-weight: bold; text-decoration: none; }
        .error-box { background: #ffe6e6; color: #a10000; padding: 16px 18px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="navbar">
        <div><strong>Casa Gunita</strong> — Checkout</div>
        <div>
            <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="menu.php">Menu</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Checkout</h1>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="panel">
                <h2>Order Summary</h2>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td><?= formatPrice($item['price']) ?></td>
                                <td><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3">Total</td>
                            <td><?= formatPrice(getCartTotal($_SESSION['cart'])) ?></td>
                        </tr>
                    </tbody>
                </table>
                <a class="button-secondary" href="cart.php">← Back to Cart</a>
            </div>

            <div class="panel">
                <h2>Order Details</h2>
                <form method="POST">
                    <div class="form-field">
                        <label for="order_type">Order Type</label>
                        <select name="order_type" id="order_type" required>
                            <option value="dine-in">Dine-in</option>
                            <option value="takeout">Takeout</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="notes">Special Notes (optional)</label>
                        <textarea name="notes" id="notes" placeholder="Allergies, special requests..."></textarea>
                    </div>

                    <button type="submit" class="button-primary">Place Order</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>