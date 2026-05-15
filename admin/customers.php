<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$search_id = trim($_GET['search_id'] ?? '');
$view_customer_id = isset($_GET['view_customer_id']) ? (int)$_GET['view_customer_id'] : 0;

$sql = "SELECT user_id, full_name, email, created_at FROM users WHERE role = ?";
if ($search_id !== '' && ctype_digit($search_id)) {
    $sql .= " AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $role = 'customer';
    mysqli_stmt_bind_param($stmt, 'si', $role, $search_id);
} else {
    $stmt = mysqli_prepare($conn, $sql);
    $role = 'customer';
    mysqli_stmt_bind_param($stmt, 's', $role);
}
mysqli_stmt_execute($stmt);
$customers = mysqli_stmt_get_result($stmt);

$access_logs = [];
$customer_detail = null;
if ($view_customer_id > 0) {
    $detail_stmt = mysqli_prepare($conn,
        "SELECT user_id, full_name, email, created_at FROM users WHERE user_id = ? AND role = 'customer'");
    mysqli_stmt_bind_param($detail_stmt, 'i', $view_customer_id);
    mysqli_stmt_execute($detail_stmt);
    $customer_detail = mysqli_fetch_assoc(mysqli_stmt_get_result($detail_stmt));
    if ($customer_detail) {
        $log_stmt = mysqli_prepare($conn,
            "SELECT event_type, event_time FROM user_access_logs
             WHERE user_id = ? ORDER BY event_time DESC LIMIT 50");
        mysqli_stmt_bind_param($log_stmt, 'i', $view_customer_id);
        mysqli_stmt_execute($log_stmt);
        $access_logs = mysqli_stmt_get_result($log_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="customers.css?v=<?= filemtime('customers.css') ?>">
    <style>
/* ══════════════════════════════════════
   HAMBURGER + COLLAPSIBLE SIDEBAR
══════════════════════════════════════ */
.hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 36px;
    height: 36px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: background 0.2s;
    flex-shrink: 0;
}
.hamburger:hover { background: rgba(33,3,3,0.08); }
.hamburger span {
    display: block;
    height: 2px;
    background: #210303;
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: center;
    width: 100%;
}
.hamburger span:nth-child(2) { width: 70%; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 49;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.visible {
    opacity: 1;
    pointer-events: all;
}

.sidebar {
    transition: transform 0.3s ease;
    will-change: transform;
}
.sidebar.collapsed { transform: translateX(-100%); }
.main { transition: margin-left 0.3s ease; }
.main.expanded { margin-left: 0 !important; }

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 50;
    }
    .sidebar.open { transform: translateX(0); }
    .main,
    .main.expanded { margin-left: 0 !important; }
    .topbar { padding: 0 16px; gap: 12px; }
    .topbar-title { font-size: 0.95rem; }
    .content { padding: 16px; }
    .top-bar { flex-direction: column; align-items: stretch; gap: 10px; }
    .customers-table th:nth-child(3),
    .customers-table td:nth-child(3) { display: none; }
}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="customizations.php">Customizations</a></li>
        <li><a href="customers.php" class="active">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php">Logout</a></div>
</aside>

<div class="main" id="main">
    <header class="topbar">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="topbar-title">Customers</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <div class="card top-bar">
            <div class="top-bar-left">
                <div class="top-bar-title">Customer Accounts</div>
            </div>
            <div class="top-bar-right">
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <input class="input-group" type="text" name="search_id" placeholder="Search Customer ID"
                           value="<?= htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8') ?>"
                           oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
                    <?php if ($search_id !== ''): ?>
                        <a href="customers.php" class="btn btn-gray">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-card">
            <table class="customers-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($customers) === 0): ?>
                    <tr class="empty-row">
                        <td colspan="5">
                            <div class="empty-inner">
                                <p>No customers found.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($customer = mysqli_fetch_assoc($customers)): ?>
                        <tr>
                            <td><span class="customer-id">#<?= $customer['user_id'] ?></span></td>
                            <td><span class="customer-name"><?= htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="customer-email"><?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="date-text"><?= htmlspecialchars($customer['created_at'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <a href="customers.php?view_customer_id=<?= $customer['user_id'] ?>" class="receipt-link">View Access Log</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($customer_detail): ?>
            <div class="table-card access-log-card">
                <div class="access-log-header">
                    <div>
                        <div class="access-log-title"><?= htmlspecialchars($customer_detail['full_name'] ?: 'Customer #' . $customer_detail['user_id'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="access-log-meta">Customer ID: <?= $customer_detail['user_id'] ?> &middot; Joined: <?= htmlspecialchars($customer_detail['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <a href="customers.php" class="btn btn-gray">Close</a>
                </div>
                <?php if (mysqli_num_rows($access_logs) === 0): ?>
                    <div class="empty-inner" style="padding: 40px 24px;">
                        <p>No login/logout activity found for this customer.</p>
                    </div>
                <?php else: ?>
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = mysqli_fetch_assoc($access_logs)): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $log['event_type'] === 'failed_login' ? 'badge-failed' : 'badge-success' ?>">
                                            <?= strtoupper(str_replace('_', ' ', $log['event_type'])) ?>
                                        </span>
                                    </td>
                                    <td><span class="date-text"><?= htmlspecialchars($log['event_time'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div><!-- .main -->

<script>
/* ══════════════════════════════════════
   HAMBURGER — all screen sizes
══════════════════════════════════════ */
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const mainEl         = document.getElementById('main');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const isMobile = () => window.innerWidth <= 768;

function openSidebar() {
    hamburgerBtn.classList.add('open');
    if (isMobile()) {
        sidebar.classList.add('open');
        sidebar.classList.remove('collapsed');
        sidebarOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
    }
    localStorage.setItem('sidebarOpen', '1');
}

function closeSidebar() {
    hamburgerBtn.classList.remove('open');
    if (isMobile()) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('collapsed');
        mainEl.classList.add('expanded');
    }
    localStorage.setItem('sidebarOpen', '0');
}

function toggleSidebar() {
    const desktopOpen = !isMobile() && !sidebar.classList.contains('collapsed');
    const mobileOpen  =  isMobile() &&  sidebar.classList.contains('open');
    (desktopOpen || mobileOpen) ? closeSidebar() : openSidebar();
}

(function init() {
    const saved = localStorage.getItem('sidebarOpen');
    if (isMobile()) {
        sidebar.classList.remove('open');
        mainEl.classList.remove('expanded');
    } else {
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    }
})();

hamburgerBtn.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

window.addEventListener('resize', () => {
    if (!isMobile()) {
        sidebarOverlay.classList.remove('visible');
        sidebar.classList.remove('open');
        document.body.style.overflow = '';
        const saved = localStorage.getItem('sidebarOpen');
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
        mainEl.style.marginLeft = '';
    }
});
</script>

</body>
</html>