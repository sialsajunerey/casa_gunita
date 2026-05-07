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
    $house_number   = sanitize($_POST['house_number'] ?? '');
    $street         = sanitize($_POST['street'] ?? '');
    $barangay       = sanitize($_POST['barangay'] ?? '');
    $city           = sanitize($_POST['city'] ?? '');
    $user_id        = (int)$_SESSION['user_id'];
    $cart           = $_SESSION['cart'];
    $total          = getCartTotal($cart);

    if ($order_type === 'delivery' && ($house_number === '' || $street === '' || $barangay === '' || $city === '')) {
        $error = 'Please enter your delivery address.';
    }

    if ($error === '') {
        $columnInfo = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'order_type'");
    if ($columnInfo && $row = mysqli_fetch_assoc($columnInfo)) {
        if (strpos($row['Type'], "'delivery'") === false) {
            mysqli_query($conn, "ALTER TABLE orders MODIFY order_type ENUM('dine-in','takeout','delivery') NOT NULL");
        }
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO orders (user_id, total_amount, status, order_type, notes, house_number, street, barangay, city) 
         VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'idssssss', $user_id, $total, $order_type, $notes, $house_number, $street, $barangay, $city);

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
        <input type="text" class="nav-search" placeholder="Search menu..." id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
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
                            <td>
                                <?= htmlspecialchars($item['name']) ?>
                                <?php if (!empty($item['options'])): ?>
                                    <div style="font-size:12px;color:#666;margin-top:4px;">
                                        <?php
                                        $grouped = [];
                                        foreach ($item['options'] as $opt) {
                                            $group = htmlspecialchars($opt['group_name'] ?? ($opt['group_type'] === 'addon' ? 'Add-ons' : ($opt['group_type'] === 'size' ? 'Size' : 'Flavor')), ENT_QUOTES, 'UTF-8');
                                            $label = htmlspecialchars($opt['name'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $price = '';
                                            if (isset($opt['additional_price']) && $opt['additional_price'] > 0) {
                                                if (isset($opt['group_type']) && $opt['group_type'] === 'addon') {
                                                    $price = ' (+' . formatPrice($opt['additional_price']) . ')';
                                                } else {
                                                    $price = ' (' . formatPrice($opt['additional_price']) . ')';
                                                }
                                            }
                                            $grouped[$group][] = $label . $price;
                                        }
                                        foreach ($grouped as $group => $items_list):
                                        ?>
                                            <div><strong><?= $group ?>:</strong> <?= htmlspecialchars(implode(', ', $items_list), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
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
                            <option value="" disabled <?= empty($order_type) ? 'selected' : '' ?>>Select</option>
                            <option value="takeout" <?= isset($order_type) && $order_type === 'takeout' ? 'selected' : '' ?>>Takeout</option>
                            <option value="delivery" <?= isset($order_type) && $order_type === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="" disabled <?= empty($payment_method) ? 'selected' : '' ?>>Select</option>
                            <option value="cash" <?= isset($payment_method) && $payment_method === 'cash' ? 'selected' : '' ?>>COD</option>
                            <option value="gcash" <?= isset($payment_method) && $payment_method === 'gcash' ? 'selected' : '' ?>>E-Payment</option>
                        </select>
                    </div>
                </div>

                <div id="deliveryAddressFields" class="delivery-address-fields" style="display: <?= isset($order_type) && $order_type === 'delivery' ? 'block' : 'none' ?>;">
                    <div class="form-row">
                        <div class="form-field">
                            <label for="house_number">House / Unit No.</label>
                            <input type="text" name="house_number" id="house_number" value="<?= htmlspecialchars($house_number ?? '') ?>">
                        </div>
                        <div class="form-field">
                            <label for="street">Street</label>
                            <input type="text" name="street" id="street" value="<?= htmlspecialchars($street ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="barangay">Barangay</label>
                            <input type="text" name="barangay" id="barangay" value="<?= htmlspecialchars($barangay ?? '') ?>">
                        </div>
                        <div class="form-field">
                            <label for="city">City</label>
                            <input type="text" name="city" id="city" value="<?= htmlspecialchars($city ?? '') ?>">
                        </div>
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

    const orderTypeSelect = document.getElementById('order_type');
    const deliveryAddressFields = document.getElementById('deliveryAddressFields');
    const addressInputs = ['house_number', 'street', 'barangay', 'city'].map(id => document.getElementById(id));

    function updateAddressFields() {
        const isDelivery = orderTypeSelect.value === 'delivery';
        deliveryAddressFields.style.display = isDelivery ? 'block' : 'none';
        addressInputs.forEach(input => {
            input.required = isDelivery;
        });
    }

    orderTypeSelect.addEventListener('change', updateAddressFields);
    updateAddressFields();
</script>

<script src="search.js"></script>

</body>
</html>