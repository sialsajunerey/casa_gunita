<?php
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$user_id    = $_SESSION['user_id'];
$stmt       = mysqli_prepare($conn, "SELECT first_name, last_name FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user       = mysqli_stmt_get_result($stmt)->fetch_assoc();
$first_name = htmlspecialchars($user['first_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$initial    = strtoupper(substr($user['first_name'] ?? 'U', 0, 1));

/* ── Fetch activity logs ── */
$logs_result = null;
$log_count   = 0;
$logs_stmt   = mysqli_prepare($conn,
    "SELECT action, created_at FROM activity_logs
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
if ($logs_stmt) {
    mysqli_stmt_bind_param($logs_stmt, 'i', $user_id);
    mysqli_stmt_execute($logs_stmt);
    $logs_result = mysqli_stmt_get_result($logs_stmt);
    $log_count   = mysqli_num_rows($logs_result);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Activity — Casa Gunita</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Cinzel:wght@400;600&family=EB+Garamond:wght@400;500&family=Public+Sans:wght@300;400;500;600&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </a>

    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu…" id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
    </div>

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
</nav>

<!-- ═══ PAGE ═══ -->
<main class="acct-page">
    <div class="acct-layout">

        <!-- SIDEBAR -->
        <aside class="acct-sidebar">
            <div class="acct-sidebar-top">
                <div class="acct-avatar"><?= $initial ?></div>
                <div class="acct-avatar-info">
                    <strong><?= $first_name ?></strong>
                </div>
            </div>
            <nav class="acct-sidebar-nav">
                <a href="account.php" class="acct-nav-link">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Account Information
                </a>
                <a href="account_activity.php" class="acct-nav-link active">
                    <svg viewBox="0 0 24 24">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Account Activity
                </a>
            </nav>
        </aside>

        <!-- CONTENT -->
        <section class="acct-content">

            <div class="acct-content-header">
                <a href="account.php" class="acct-back-link">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to Account
                </a>
                <h1>Account Activity</h1>
                <p>Your recent login and logout history.</p>
            </div>

            <div class="acct-card">
                <div class="acct-card-head">
                    <h2>Recent Activity</h2>
                    <?php if ($log_count > 0): ?>
                        <span class="acct-log-count"><?= $log_count ?> record<?= $log_count !== 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>

                <div class="acct-log">
                    <?php if ($logs_result && $log_count > 0):
                        while ($log = mysqli_fetch_assoc($logs_result)):
                            $action    = strtolower(trim($log['action'] ?? ''));
                            $is_login  = str_contains($action, 'login');
                            $type      = $is_login ? 'login' : 'logout';
                            $label     = $is_login ? 'Logged in' : 'Logged out';
                            $tag_class = $is_login ? 'login-tag' : 'logout-tag';
                            $dt        = new DateTime($log['created_at']);
                            $formatted = $dt->format('F j, Y · g:i A');
                    ?>
                    <div class="acct-log-item">
                        <div class="acct-log-badge <?= $type ?>">
                            <?php if ($is_login): ?>
                                <svg viewBox="0 0 24 24">
                                    <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                                    <polyline points="10 17 15 12 10 7"/>
                                    <line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                            <?php else: ?>
                                <svg viewBox="0 0 24 24">
                                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="acct-log-details">
                            <span class="acct-log-action"><?= $label ?></span>
                            <span class="acct-log-time"><?= $formatted ?></span>
                        </div>
                        <span class="acct-log-tag <?= $tag_class ?>"><?= ucfirst($type) ?></span>
                    </div>
                    <?php endwhile; else: ?>

                    <!-- Empty state -->
                    <div class="acct-log-empty">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <p>No activity recorded yet.</p>
                    </div>

                    <?php endif; ?>
                </div>
            </div>

        </section>
    </div>
</main>

<script src="search.js"></script>
<script>
const accountBtn      = document.getElementById('accountBtn');
const accountDropdown = document.getElementById('accountDropdown');
if (accountBtn && accountDropdown) {
    accountBtn.addEventListener('click', e => {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });
    document.addEventListener('click', () => accountDropdown.classList.remove('open'));
}
</script>
</body>
</html>