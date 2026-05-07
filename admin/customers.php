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

$sql = "SELECT user_id, full_name, email, password, created_at FROM users WHERE role = ?";
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
    <link rel="stylesheet" href="customers.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="modifiers.php">Modifiers</a></li>
        <li><a href="customers.php" class="active">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php">Logout</a></div>
</aside>

<div class="main">
    <header class="topbar">
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
                    <input class="input-group" type="text" name="search_id" placeholder="Search by customer ID" value="<?= htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8') ?>">
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
                        <th>Password</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($customers) === 0): ?>
                    <tr class="empty-row">
                        <td colspan="6">
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
                            <td>
                                <div class="password-cell">
                                    <span class="password-mask" id="mask-<?= $customer['user_id'] ?>">••••••••</span>
                                    <span class="password-text" id="pw-<?= $customer['user_id'] ?>" style="display:none;"><?= htmlspecialchars($customer['password'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <button type="button" class="toggle-btn" onclick="togglePassword(this, <?= $customer['user_id'] ?>)">Show</button>
                                </div>
                            </td>
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
</div>

<script>
function togglePassword(button, id) {
    const mask = document.getElementById('mask-' + id);
    const text = document.getElementById('pw-' + id);
    if (!mask || !text) return;
    if (text.style.display === 'none') {
        text.style.display = 'inline';
        mask.style.display = 'none';
        button.textContent = 'Hide';
    } else {
        text.style.display = 'none';
        mask.style.display = 'inline';
        button.textContent = 'Show';
    }
}
</script>
</body>
</html>