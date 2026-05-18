<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$user_id = $_SESSION['user_id'];

// Fetch all orders by this customer
$orders = mysqli_prepare($conn,
    "SELECT * FROM orders 
     WHERE user_id = ? 
     ORDER BY created_at DESC");
mysqli_stmt_bind_param($orders, 'i', $user_id);
mysqli_stmt_execute($orders);
$result = mysqli_stmt_get_result($orders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="orderstatus.css">
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

<div class="container">
    <h1 class="page-title">My Orders</h1>

    <?php if (mysqli_num_rows($result) === 0): ?>
        <p class="empty-msg">You have no orders yet. <a href="menu.php">Order now!</a></p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="order-table">
        <thead>
        <tr>
            <th>Order #</th>
            <th>Total</th>
            <th>Type</th>
            <th>Status</th>
            <th>Date</th>
            <th>Receipt</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($order = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td>#<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= formatPrice($order['total_amount']) ?></td>
            <td><?= $order['order_type'] === 'takeout' ? 'Pick-Up' : ucfirst($order['order_type']) ?></td>
            <td>
                <span class="badge <?= $order['status'] ?>">
                    <?= strtoupper($order['status']) ?>
                </span>
            </td>
            <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
            <td>
                <a class="view-receipt" href="receipt.php?order_id=<?= $order['order_id'] ?>">
                    View Receipt
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
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

<script src="search.js"></script>

<?php include_once '../includes/order_status_overlay.php'; ?>

</body>
</html>