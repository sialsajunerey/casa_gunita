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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="checkout.css">
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
        <a href="about.php">About</a>
    </div>
    <div class="nav-icons">
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= count($_SESSION['cart']) ?></span>
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
    <h1 class="page-title">Review Your Order</h1>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="checkout-grid">

        <!-- Order Summary -->
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

        <!-- Order Details -->
        <div class="panel">
            <h2>Order Details</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-field">
                        <label for="order_type">Order Type</label>
                        <select name="order_type" id="order_type" required>
                            <option value="" disabled selected>Select</option>
                            <option value="takeout">Takeout</option>
                            <option value="delivery">Delivery</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="" disabled selected>Select</option>
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

<script>
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