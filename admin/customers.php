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
            "SELECT event_type, event_time, ip_address FROM user_access_logs
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
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --crimson: #210303; --ink: #130301; --surface: #fff8eb; --bg: #f4f2ea; --line: rgba(33,3,3,.1); --radius: 14px; --shadow: 0 2px 18px rgba(33,3,3,.08); --sidebar-w:220px; }
body { margin:0; font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--ink); min-height:100vh; display:flex; }
.sidebar{width:var(--sidebar-w); background:var(--crimson); min-height:100vh; position:fixed; top:0; left:0; display:flex; flex-direction:column; }
.sidebar-logo{padding:22px 20px 18px; border-bottom:1px solid rgba(255,255,255,.12); }
.sidebar-logo .brand{font-family:'Cinzel Decorative',serif;font-size:17px;color:#fff;letter-spacing:.08em;text-transform:uppercase; }
.nav-list{list-style:none;padding:16px 12px;margin:0;flex:1;}
.nav-list li{margin-bottom:4px;}
.nav-list a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;}
.nav-list a.active,.nav-list a:hover{background:rgba(255,255,255,.14);color:#fff;}
.nav-list a .icon{width:20px;text-align:center;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,.12);}
.sidebar-footer a{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.65);text-decoration:none;}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:64px;background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:5;}
.topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--crimson);} .topbar-spacer{flex:1;}.topbar-user{display:flex;align-items:center;gap:10px;color:var(--ink);font-size:14px;}.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--crimson);color:#fff;font-weight:700;}
.content{padding:24px 28px;display:flex;flex-direction:column;gap:20px;}
.card{background:var(--surface);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);}
.table-wrap{overflow-x:auto;}
.table{width:100%;border-collapse:collapse;}
.table th,.table td{padding:14px 16px;border-bottom:1px solid #ecebf1;text-align:left;}
.table th{background:#e8d191;color:#130301;text-transform:uppercase;font-size:12px;letter-spacing:.08em;}
.status-tag{padding:6px 10px;border-radius:999px;font-size:13px;display:inline-flex;align-items:center;gap:6px;}
.status-failed{background:#fde2e2;color:#9b1c1c;}
.status-success{background:#d1fae5;color:#0f5132;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:12px;padding:10px 16px;font-weight:700;cursor:pointer;text-decoration:none;}
.btn-blue{background:#3498db;color:#fff;}.btn-gray{background:#6b7280;color:#fff;}.btn-red{background:#e74c3c;color:#fff;}
.input-inline{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.input-inline input{padding:12px 14px;border-radius:12px;border:1px solid #d6d2d9;min-width:220px;}
.password-cell{display:flex;align-items:center;gap:10px;}
.password-mask{font-family:monospace;letter-spacing:0.12em;}
.link-button{border:none;background:none;color:#3498db;cursor:pointer;text-decoration:underline;padding:0;font-size:0.95rem;}
.details-card{margin-top:16px;}
.details-card h3{margin:0 0 12px;font-size:1.2rem;}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="products.php"><span class="icon">🍽️</span> Menu</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php" class="active"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php"><span class="icon">🚪</span> Logout</a></div>
</aside>
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Customers</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user"><div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div><span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
    </header>

    <div class="content">
        <div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div style="font-weight:700;font-size:1.05rem;">Customer Accounts</div>
            <form method="GET" class="input-inline">
                <input type="text" name="search_id" placeholder="Search by customer ID" value="<?= htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-blue">Search</button>
                <?php if ($search_id !== ''): ?><a href="customers.php" class="btn btn-gray">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="card table-wrap">
            <table class="table">
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
                    <tr><td colspan="6" style="text-align:center;color:#777;padding:30px 0;">No customer found.</td></tr>
                <?php else: ?>
                    <?php while ($customer = mysqli_fetch_assoc($customers)): ?>
                        <tr>
                            <td><?= $customer['user_id'] ?></td>
                            <td><?= htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="password-cell">
                                    <span class="password-mask">********</span>
                                    <button type="button" class="link-button" onclick="togglePassword(this, 'pw-<?= $customer['user_id'] ?>')">Show</button>
                                    <span id="pw-<?= $customer['user_id'] ?>" style="display:none; font-family:monospace;"><?= htmlspecialchars($customer['password'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($customer['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a href="customers.php?view_customer_id=<?= $customer['user_id'] ?>" class="btn btn-gray">View Access Log</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($customer_detail): ?>
            <div class="card details-card">
                <h3>Access Log for <?= htmlspecialchars($customer_detail['full_name'] ?: 'Customer #' . $customer_detail['user_id'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div style="margin-bottom:16px;">Customer ID: <?= $customer_detail['user_id'] ?> · Created: <?= htmlspecialchars($customer_detail['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (mysqli_num_rows($access_logs) === 0): ?>
                    <div style="color:#777;">No login/logout activity found for this customer.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr><th>Event</th><th>Timestamp</th><th>IP Address</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($log = mysqli_fetch_assoc($access_logs)): ?>
                                <tr>
                                    <td><span class="status-tag <?= $log['event_type'] === 'failed_login' ? 'status-failed' : 'status-success' ?>"><?= strtoupper(str_replace('_', ' ', $log['event_type'])) ?></span></td>
                                    <td><?= htmlspecialchars($log['event_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
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
    const target = document.getElementById(id);
    if (!target) return;
    if (target.style.display === 'none') {
        target.style.display = 'inline';
        button.textContent = 'Hide';
    } else {
        target.style.display = 'none';
        button.textContent = 'Show';
    }
}
</script>
</body>
</html>
