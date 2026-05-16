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

// Check if user has an active order (pending, preparing, or ready)
$user_id = (int)$_SESSION['user_id'];
$activeOrderStmt = mysqli_prepare($conn, "SELECT order_id, status, total_amount FROM orders WHERE user_id = ? AND status IN ('pending', 'preparing', 'ready') ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($activeOrderStmt, 'i', $user_id);
mysqli_stmt_execute($activeOrderStmt);
$activeOrderResult = mysqli_stmt_get_result($activeOrderStmt);
$activeOrder = mysqli_fetch_assoc($activeOrderResult);
$hasActiveOrder = !empty($activeOrder);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type     = $_POST['order_type'];
    $payment_method = isset($_POST['payment_method']) && $_POST['payment_method'] === 'E-Payment' ? 'E-Payment' : 'COD';
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
                mysqli_query($conn, "ALTER TABLE orders MODIFY order_type ENUM('takeout','delivery') NOT NULL");
            }
        }

        $items = [];
        foreach ($cart as $product_id => $item) {
            $items[] = [
                'product_id' => (int)$product_id,
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['price'],
                'options' => $item['options'] ?? []
            ];
        }

        $itemsJson = json_encode($items);

        $stmt = mysqli_prepare($conn,
            "CALL sp_create_order(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issssssss',
            $user_id,
            $order_type,
            $payment_method,
            $notes,
            $house_number,
            $street,
            $barangay,
            $city,
            $itemsJson);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($row && isset($row['order_id'])) {
                $order_id = $row['order_id'];
                $_SESSION['cart'] = [];
                header('Location: receipt.php?order_id=' . $order_id);
                exit();
            }
        }

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
    <link rel="stylesheet" href="order-status-overlay.css">
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
                                                $price = ' (+' . formatPrice($opt['additional_price']) . ')';
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
                            <td><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 20px; text-align: right; font-size: 16px; font-weight: bold;">
                Total: <?= formatPrice(getCartTotal($_SESSION['cart'])) ?>
            </div>
            <a class="btn btn-secondary" href="cart.php" style="margin-top: 15px;">← Back to Cart</a>
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
                            <option value="takeout" <?= isset($order_type) && $order_type === 'takeout' ? 'selected' : '' ?>>Pick-Up</option>
                            <option value="delivery" <?= isset($order_type) && $order_type === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="" disabled <?= empty($payment_method) ? 'selected' : '' ?>>Select</option>
                            <option value="COD" <?= isset($payment_method) && $payment_method === 'COD' ? 'selected' : '' ?>>COD</option>
                            <option value="E-Payment" <?= isset($payment_method) && $payment_method === 'E-Payment' ? 'selected' : '' ?>>E-Payment</option>
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

    // Handle active order modal
    const hasActiveOrder = <?= json_encode($hasActiveOrder) ?>;
    const activeOrder = <?= $hasActiveOrder ? json_encode($activeOrder) : 'null' ?>;
    
    if (hasActiveOrder && activeOrder) {
        document.addEventListener('DOMContentLoaded', function() {
            showActiveOrderModal(activeOrder);
        });
        
        document.querySelector('form').addEventListener('submit', function(e) {
            if (hasActiveOrder) {
                e.preventDefault();
                showActiveOrderModal(activeOrder);
                return false;
            }
        });
    }
    
    function showActiveOrderModal(order) {
        const modal = document.getElementById('activeOrderModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeActiveOrderModal() {
        const modal = document.getElementById('activeOrderModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeActiveOrderModal();
    });
</script>

<!-- Active Order Modal -->
<?php if ($hasActiveOrder && $activeOrder): ?>
<div class="active-order-modal-overlay" id="activeOrderModal">
    <div class="active-order-modal">
        
        <div class="modal-header">
    <div class="modal-header-icon">⚠</div><br>
    <h2>Active Order in Progress</h2>
</div>
<div class="modal-body">
    <p class="modal-message">You have an active order that needs to be completed before placing a new one.</p>
    <div class="order-details">
        <div class="detail-row">
            <span class="detail-label">Order #</span>
            <span class="detail-value"><?= str_pad((int)$activeOrder['order_id'], 5, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value status-<?= htmlspecialchars($activeOrder['status'], ENT_QUOTES, 'UTF-8') ?>">
                <?= ucfirst(htmlspecialchars($activeOrder['status'], ENT_QUOTES, 'UTF-8')) ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Amount</span>
            <span class="detail-value">₱<?= number_format((float)$activeOrder['total_amount'], 2) ?></span>
        </div>
    </div>
</div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeActiveOrderModal()">Continue Browsing</button>
            <a href="order_status.php" class="btn btn-primary">View Order Status →</a>
        </div>
    </div>
</div>

<style>
/* ── Active Order Modal ── */
.active-order-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.active-order-modal-overlay.active {
    display: flex;
}

.active-order-modal {
    background: rgba(21, 1, 1, 0.97);
    border-radius: 4px;
    max-width: 440px;
    width: 100%;
    overflow: hidden;
    box-shadow:
        0 24px 64px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(232, 209, 145, 0.06) inset;
    animation: aomSlideUp 0.35s ease;
}

@keyframes aomSlideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Header ── */
.modal-header {
    padding: 28px 32px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.modal-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    background: rgba(232, 209, 145, 0.08);
}

.modal-header h2 {
    margin: 0;
    font-family: 'Cinzel', serif;
    font-size: 1rem;
    font-weight: 600;
    color: #e8d191;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

/* ── Body ── */
.modal-body {
    padding: 28px 32px;
}

.modal-message {
    font-family: 'Public Sans', sans-serif;
    color: rgba(220, 228, 207, 0.75);
    font-size: 0.9rem;
    margin: 0 0 24px;
    line-height: 1.7;
    text-align: center;
}

/* ── Order Details Card ── */
.order-details {
    background: rgba(232, 209, 145, 0.04);
    border: 1px solid rgba(232, 209, 145, 0.12);
    border-radius: 4px;
    overflow: hidden;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    border-bottom: 1px solid rgba(232, 209, 145, 0.07);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-family: 'Public Sans', sans-serif;
    color: rgba(220, 228, 207, 0.5);
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.detail-value {
    font-family: 'EB Garamond', serif;
    color: #dce4cf;
    font-size: 1rem;
    font-weight: 500;
}

.detail-value.status-pending   { color: #e8d191; }
.detail-value.status-preparing { color: #93c5fd; }
.detail-value.status-ready     { color: #90d47f; }

/* ── Footer ── */
.modal-footer {
    display: flex;
    gap: 12px;
    padding: 20px 32px 28px;
    border-top: 1px solid rgba(232, 209, 145, 0.1);
}

.modal-footer .btn {
    flex: 1;
    padding: 13px 16px;
    font-family: 'Public Sans', sans-serif;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    text-align: center;
    cursor: pointer;
    border-radius: 0;
    transition: background 0.25s ease, color 0.25s ease, border-color 0.25s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.modal-footer .btn-secondary {
    background: transparent !important;
    border: 1px solid rgba(232, 209, 145, 0.3);
    color: rgba(220, 228, 207, 0.7);
}
.modal-footer .btn-secondary:hover {
    background: rgba(232, 209, 145, 0.08) !important;
    border-color: rgba(232, 209, 145, 0.6);
    color: #e8d191;
}

.modal-footer .btn-primary {
    background: transparent !important;
    border: 1px solid rgba(232, 209, 145, 0.6);
    color: #e8d191;
}
.modal-footer .btn-primary:hover {
    background: #e8d191 !important;
    color: #120000 !important;
}

@media (max-width: 480px) {
    .active-order-modal { max-width: 100%; border-radius: 4px 4px 0 0; }
    .active-order-modal-overlay { align-items: flex-end; padding: 0; }
    .modal-header { padding: 22px 20px 16px; }
    .modal-body { padding: 20px; }
    .modal-footer { flex-direction: column; padding: 16px 20px 24px; }
}
</style>
<?php endif; ?>

<script src="order-status-overlay.js"></script>
<script src="search.js"></script>

</body>
</html>