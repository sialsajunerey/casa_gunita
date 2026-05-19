<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$user_id = $_SESSION['user_id'];
$password_error = '';
$password_success = '';
$page_success = '';

if (isset($_SESSION['password_success'])) {
    $page_success = $_SESSION['password_success'];
    unset($_SESSION['password_success']);
}

/* ── Fetch user info ── */
$stmt = mysqli_prepare($conn,
    "SELECT first_name, last_name, email, password FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current   = $_POST['current_password'] ?? '';
    $new       = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_new_password'] ?? '';
    $policy    = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w]).{8,64}$/';

    if (!$user || !password_verify($current, $user['password'])) {
        $password_error = 'Current password is incorrect.';
        $logStmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type, event_time) VALUES (?, 'password_change_failed', NOW())");
        mysqli_stmt_bind_param($logStmt, 'i', $user_id);
        mysqli_stmt_execute($logStmt);
    } elseif ($new !== $confirm) {
        $password_error = 'New passwords do not match.';
        $logStmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type, event_time) VALUES (?, 'password_change_failed', NOW())");
        mysqli_stmt_bind_param($logStmt, 'i', $user_id);
        mysqli_stmt_execute($logStmt);
    } elseif (!preg_match($policy, $new)) {
        $password_error = 'New password must be 8-64 characters and include uppercase, lowercase, number, and symbol.';
        $logStmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type, event_time) VALUES (?, 'password_change_failed', NOW())");
        mysqli_stmt_bind_param($logStmt, 'i', $user_id);
        mysqli_stmt_execute($logStmt);
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updateStmt, 'si', $hash, $user_id);
        if (mysqli_stmt_execute($updateStmt)) {
            $logStmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type, event_time) VALUES (?, 'password_change_success', NOW())");
            mysqli_stmt_bind_param($logStmt, 'i', $user_id);
            mysqli_stmt_execute($logStmt);

            $_SESSION['password_success'] = 'You\'ve successfully changed your password.';
            header('Location: account.php');
            exit();
        } else {
            $logStmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type, event_time) VALUES (?, 'password_change_failed', NOW())");
            mysqli_stmt_bind_param($logStmt, 'i', $user_id);
            mysqli_stmt_execute($logStmt);

            $password_error = 'Unable to update password. Please try again.';
        }
    }
}

$first_name = htmlspecialchars($user['first_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$last_name  = htmlspecialchars($user['last_name']  ?? '', ENT_QUOTES, 'UTF-8');
$email      = htmlspecialchars($user['email']      ?? '', ENT_QUOTES, 'UTF-8');
$initial    = strtoupper(substr($first_name ?: 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Information — Casa Gunita</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Cinzel:wght@400;600&family=EB+Garamond:wght@400;500&family=Public+Sans:wght@300;400;500;600&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="landingpage.css?v=<?= filemtime('landingpage.css') ?>">
    <link rel="stylesheet" href="account.css?v=<?= filemtime('account.css') ?>">
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

<!-- ═══════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════ -->
<<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </a>

    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu…" id="navSearch">
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
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8">
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

<!-- ═══════════════════════════════════════════
     PAGE WRAPPER
════════════════════════════════════════════ -->
<<main class="acct-page">
    <div class="acct-layout">

        <!-- ── SIDEBAR ── -->
        <aside class="acct-sidebar">
            <div class="acct-sidebar-top">
                <div class="acct-avatar"><?= $initial ?></div>
                <div class="acct-avatar-info">
                    <strong><?= $first_name ?></strong>
                </div>
            </div>

           <nav class="acct-sidebar-nav">
    <a href="account.php" class="acct-nav-link active">
        <svg viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        Account Information
    </a>
</nav>
        </aside>

        <!-- ── CONTENT ── -->
        <section class="acct-content">

            <div class="acct-content-header">
                <h1>Account Information</h1>
                <p>Your personal details are shown below.</p>
            </div>

            <?php if ($page_success): ?>
                <div class="acct-notice success">
                    <?= htmlspecialchars($page_success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Personal Info Card -->
            <div class="acct-card">
                <div class="acct-card-head">
                    <h2>Personal Information</h2>
                </div>
                <div class="acct-card-body">

                    <div class="acct-field-group">
                        <label>First Name</label>
                        <input type="text" value="<?= $first_name ?>" readonly>
                    </div>

                    <div class="acct-field-divider"></div>

                    <div class="acct-field-group">
                        <label>Last Name</label>
                        <input type="text" value="<?= $last_name ?>" readonly>
                    </div>

                    <div class="acct-field-divider"></div>

                    <div class="acct-field-group">
                        <label>Email Address</label>
                        <input type="email" value="<?= $email ?>" readonly>
                    </div>

                    <div class="acct-field-divider"></div>

                    <div class="acct-field-group">
                        <button type="button" class="acct-change-pw-btn" id="changePwBtn">
                            Change Password
                        </button>
                    </div>

                </div>
            </div>

        </section>
    </div>
</main>

<div id="changePasswordOverlay" class="acct-overlay" aria-hidden="true">
    <div class="acct-overlay-panel">
        <button type="button" class="acct-overlay-close" id="closeChangePwOverlay" aria-label="Close">×</button>
        <h2>Change Password</h2>
        <p class="overlay-subtext">Enter your current password and choose a new one with uppercase, lowercase, number, and symbol.</p>
        <?php if ($password_error || $password_success): ?>
            <div class="acct-notice <?= $password_error ? 'error' : 'success' ?>">
                <?= htmlspecialchars($password_error ?: $password_success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="acct-overlay-form">
            <input type="hidden" name="change_password" value="1">
            <div class="acct-field-group acct-pw-wrap">
                <label>Current Password</label>
                <div class="acct-pw-wrap">
                    <input type="password" name="current_password" required autocomplete="current-password">
                    <button type="button" class="acct-pw-toggle" aria-label="Show password">
                        <svg viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="acct-field-divider"></div>
            <div class="acct-field-group acct-pw-wrap">
                <label>New Password</label>
                <div class="acct-pw-wrap">
                    <input type="password" name="new_password" required autocomplete="new-password" placeholder="New password">
                    <button type="button" class="acct-pw-toggle" aria-label="Show password">
                        <svg viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="acct-field-divider"></div>
            <div class="acct-field-group acct-pw-wrap">
                <label>Confirm New Password</label>
                <div class="acct-pw-wrap">
                    <input type="password" name="confirm_new_password" required autocomplete="new-password" placeholder="Confirm new password">
                    <button type="button" class="acct-pw-toggle" aria-label="Show password">
                        <svg viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="acct-field-divider"></div>
            <div class="acct-pw-actions">
                <button type="submit" class="acct-save-pw-btn">Confirm</button>
                <button type="button" class="acct-cancel-pw-btn" id="cancelChangePw">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="search.js"></script>
<script>
/* ══════════════════════════════════════
   MOBILE NAVIGATION DRAWER
══════════════════════════════════════ */
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

/* ── Account dropdown ── */
const accountBtn      = document.getElementById('accountBtn');
const accountDropdown = document.getElementById('accountDropdown');
if (accountBtn && accountDropdown) {
    accountBtn.addEventListener('click', e => {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });
    document.addEventListener('click', () => accountDropdown.classList.remove('open'));
}

/* ── Change Password overlay ── */
const changePwBtn = document.getElementById('changePwBtn');
const overlay = document.getElementById('changePasswordOverlay');
const closeOverlay = document.getElementById('closeChangePwOverlay');
const cancelChangePw = document.getElementById('cancelChangePw');

function openChangeOverlay() {
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}
function closeChangeOverlay() {
    const notice = overlay.querySelector('.acct-notice');
    if (notice) {
        notice.remove();
    }
    const form = overlay.querySelector('form');
    if (form) {
        form.reset();
    }
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
}

if (changePwBtn) {
    changePwBtn.addEventListener('click', openChangeOverlay);
}
if (closeOverlay) {
    closeOverlay.addEventListener('click', closeChangeOverlay);
}
if (cancelChangePw) {
    cancelChangePw.addEventListener('click', closeChangeOverlay);
}

const passwordToggleButtons = document.querySelectorAll('.acct-pw-toggle');
passwordToggleButtons.forEach(button => {
    const eyeOpen = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeClosed = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle><line x1="2" y1="2" x2="22" y2="22"></line></svg>';
    button.innerHTML = eyeOpen;
    button.addEventListener('click', () => {
        const input = button.closest('.acct-pw-wrap').querySelector('input');
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.innerHTML = isPassword ? eyeClosed : eyeOpen;
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
});

const shouldOpenChangePwOverlay = <?= $password_error ? 'true' : 'false' ?>;
if (shouldOpenChangePwOverlay) {
    openChangeOverlay();
}

overlay.addEventListener('click', e => {
    if (e.target === overlay) {
        closeChangeOverlay();
    }
});

/* ── Auto-hide success message ── */
const successNotice = document.querySelector('.acct-notice.success');
if (successNotice) {
    setTimeout(() => {
        successNotice.style.display = 'none';
    }, 10000); // 10 seconds
}

/* ── Auto-hide error notice in overlay ── */
const errorNotice = document.querySelector('.acct-overlay .acct-notice.error');
if (errorNotice) {
    setTimeout(() => {
        errorNotice.style.display = 'none';
    }, 10000); // 10 seconds
}
</script>

</body>
</html>