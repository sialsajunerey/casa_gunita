<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
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
    <link rel="stylesheet" href="landingpage.css?v=<?= filemtime('landingpage.css') ?>">
    <link rel="stylesheet" href="orderstatus.css?v=<?= filemtime('orderstatus.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=EB+Garamond:wght@400;500&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
    <style>
        /* Inline hamburger styles as fallback */
        .nav-hamburger {
            display: none;
            background: none;
            border: none;
            color: #e8d191;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            transition: background 0.2s;
            flex-shrink: 0;
            z-index: 101;
        }
        .nav-hamburger:hover { background: rgba(232, 209, 145, 0.1); }
        
        .hamburger-icon {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            width: 24px;
            height: 24px;
        }
        
        .hamburger-icon span {
            display: block;
            height: 2px;
            background: #e8d191;
            border-radius: 2px;
            transition: transform 0.3s ease, opacity 0.3s ease;
            transform-origin: center;
            width: 100%;
        }
        
        .hamburger-icon span:nth-child(2) { width: 70%; }
        .nav-hamburger.open .hamburger-icon span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .nav-hamburger.open .hamburger-icon span:nth-child(2) { opacity: 0; }
        .nav-hamburger.open .hamburger-icon span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        .nav-drawer-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        .nav-drawer-overlay.visible {
            display: block;
            opacity: 1;
            pointer-events: all;
        }

        .nav-drawer {
            display: none;
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            background: rgba(21, 1, 1, 0.97);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(232, 209, 145, 0.18);
            z-index: 99;
            flex-direction: column;
            padding: 16px 24px 24px;
            gap: 4px;
            transform: translateY(-100%);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .nav-drawer.open {
            display: flex;
            transform: translateY(0);
            opacity: 1;
        }
        .nav-drawer a {
            color: #dce4cf;
            font-family: 'Public Sans', sans-serif;
            font-size: 14px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            padding: 12px 0;
            border-bottom: 1px solid rgba(232, 209, 145, 0.08);
            opacity: 0.85;
            transition: opacity 0.2s, color 0.2s;
        }
        .nav-drawer a:last-child { border-bottom: none; }
        .nav-drawer a:hover { opacity: 1; color: #e8d191; }

        @media (max-width: 900px) {
            .nav-links { display: none; }
            .nav-hamburger { display: flex !important; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<<nav class="navbar">
    <div class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </div>
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu..." id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
    </div>
    
    <!-- Desktop Navigation -->
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
    
    <!-- Mobile Hamburger Button -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Toggle menu">
        <div class="hamburger-icon">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>
</nav>

<!-- Mobile Navigation Drawer -->
<div class="nav-drawer-overlay" id="navDrawerOverlay"></div>
<div class="nav-drawer" id="navDrawer">
    <a href="index.php">Home</a>
    <a href="menu.php">Menu</a>
    <a href="index.php#about">About</a>
    <a href="index.php#contact">Contact</a>
    <a href="index.php#featured">Featured Dishes</a>
</div>

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
// ══════════════════════════════════════
// MOBILE NAVIGATION DRAWER
// ══════════════════════════════════════
const navHamburger = document.getElementById('navHamburger');
const navDrawer = document.getElementById('navDrawer');
const navDrawerOverlay = document.getElementById('navDrawerOverlay');

function openNavDrawer() {
    navHamburger.classList.add('open');
    navDrawer.classList.add('open');
    navDrawerOverlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeNavDrawer() {
    navHamburger.classList.remove('open');
    navDrawer.classList.remove('open');
    navDrawerOverlay.classList.remove('visible');
    document.body.style.overflow = '';
}

function toggleNavDrawer() {
    if (navDrawer.classList.contains('open')) {
        closeNavDrawer();
    } else {
        openNavDrawer();
    }
}

navHamburger.addEventListener('click', function(e) {
    e.stopPropagation();
    toggleNavDrawer();
});

navDrawerOverlay.addEventListener('click', closeNavDrawer);

// Close drawer on nav link click
document.querySelectorAll('.nav-drawer a').forEach(link => {
    link.addEventListener('click', () => {
        closeNavDrawer();
    });
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNavDrawer();
    }
});

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
</script>

<script src="search.js"></script>

<?php include_once '../includes/order_status_overlay.php'; ?>

</body>
</html>