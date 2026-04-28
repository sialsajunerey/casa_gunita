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
    $order_type     = $_POST['order_type'];
    $payment_method = isset($_POST['payment_method']) && $_POST['payment_method'] === 'gcash' ? 'gcash' : 'cash';
    $notes          = sanitize($_POST['notes']);
    $user_id        = (int)$_SESSION['user_id'];
    $cart           = $_SESSION['cart'];
    $total          = getCartTotal($cart);

    $columnInfo = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'order_type'");
    if ($columnInfo && $row = mysqli_fetch_assoc($columnInfo)) {
        if (strpos($row['Type'], "'delivery'") === false) {
            mysqli_query($conn, "ALTER TABLE orders MODIFY order_type ENUM('dine-in','takeout','delivery') NOT NULL");
        }
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO orders (user_id, total_amount, status, order_type, notes) 
         VALUES (?, ?, 'pending', ?, ?)");
    mysqli_stmt_bind_param($stmt, 'idss', $user_id, $total, $order_type, $notes);

    if (mysqli_stmt_execute($stmt)) {
        $order_id = mysqli_insert_id($conn);

        $columnExists = mysqli_query($conn, "SHOW COLUMNS FROM order_items LIKE 'options'");
        if ($columnExists && mysqli_num_rows($columnExists) === 0) {
            mysqli_query($conn, "ALTER TABLE order_items ADD COLUMN options TEXT NULL");
        }

        foreach ($cart as $product_id => $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $optionsJson = json_encode($item['options'] ?? []);

            $item_stmt = mysqli_prepare($conn,
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, options)
                 VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($item_stmt, 'iiidds',
                $order_id, $product_id, $item['quantity'], $item['price'], $subtotal, $optionsJson);
            mysqli_stmt_execute($item_stmt);

            reduceStock($conn, $product_id, $item['quantity']);
        }

        $tx_stmt = mysqli_prepare($conn,
            "INSERT INTO transactions (order_id, user_id, amount_paid, payment_method)
             VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($tx_stmt, 'iids',
            $order_id, $user_id, $total, $payment_method);
        mysqli_stmt_execute($tx_stmt);

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
        .container {
            padding: 32px 24px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .page-title {
            margin: 0 0 20px;
            color: var(--crimson);
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 26px;
        }
        .panel {
            background: var(--surface);
            padding: 26px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .panel h2 {
            margin-top: 0;
            color: var(--crimson);
            font-family: 'Playfair Display', serif;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table th, .summary-table td {
            padding: 16px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }
        .summary-table th {
            background: var(--crimson-d);
            color: var(--gold);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .summary-table tr:last-child td { border-bottom: none; }
        .summary-table .total-row td { font-size: 18px; font-weight: 700; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-field { margin-bottom: 18px; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--ink); }
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d6d2d9;
            border-radius: 12px;
            background: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--ink);
        }
        .form-field textarea { resize: vertical; min-height: 140px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 22px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            transition: transform .15s ease, opacity .15s ease;
            text-decoration: none;
        }
        .btn-primary { background: var(--crimson); color: #fff; }
        .btn-primary:hover { opacity: .95; transform: translateY(-1px); }
        .btn-secondary { display: inline-flex; background: transparent; color: var(--crimson); border: 2px solid var(--crimson); }
        .btn-secondary:hover { opacity: .92; }
        .error-box { background: #fee2e2; color: #b91c1c; padding: 18px 20px; border-radius: 14px; margin-bottom: 20px; }
        @media (max-width: 860px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
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
                <a class="btn btn-secondary" href="cart.php">← Back to Cart</a>
            </div>

            <div class="panel">
                <h2>Order Details</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-field">
                            <label for="order_type">Order Type</label>
                            <select name="order_type" id="order_type" required>
                                <option value="takeout">Takeout</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="payment_method">Payment Method</label>
                            <select name="payment_method" id="payment_method" required>
                                <option value="cash">COD</option>
                                <option value="gcash">Epayment</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="notes">Special Notes (optional)</label>
                        <textarea name="notes" id="notes" placeholder="Allergies, special requests..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Place Order</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>