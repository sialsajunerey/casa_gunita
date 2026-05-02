<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM audit_logs");
$totalRows = (int)mysqli_fetch_assoc($countResult)['total'];
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = mysqli_prepare($conn,
    "SELECT a.*, ua.full_name AS admin_name, uc.full_name AS customer_name
     FROM audit_logs a
     LEFT JOIN users ua ON a.admin_id = ua.user_id
     LEFT JOIN users uc ON a.customer_id = uc.user_id
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'ii', $perPage, $offset);
mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log — Casa Gunita Admin</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --crimson:#210303; --ink:#130301; --surface:#fff8eb; --bg:#f4f2ea; --line:rgba(33,3,3,.1); --radius:14px; --shadow:0 2px 18px rgba(33,3,3,.08); --sidebar-w:220px; }
body{margin:0;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar-w);background:var(--crimson);min-height:100vh;position:fixed;top:0;left:0;display:flex;flex-direction:column;}
.sidebar-logo{padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,.12);}
.sidebar-logo .brand{font-family:'Cinzel Decorative',serif;font-size:17px;color:#fff;letter-spacing:.08em;text-transform:uppercase;}
.nav-list{list-style:none;padding:16px 12px;margin:0;flex:1;}
.nav-list li{margin-bottom:4px;}
.nav-list a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:rgba(255,255,255,.75);font-size:14px;font-weight:500;}
.nav-list a.active,.nav-list a:hover{background:rgba(255,255,255,.14);color:#fff;}
.nav-list a .icon{width:20px;text-align:center;}
.sidebar-footer{padding:16px 12px;border-top:1px solid rgba(255,255,255,.12);}
.sidebar-footer a{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.65);text-decoration:none;}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:64px;background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:5;}
.topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--crimson);}.topbar-spacer{flex:1;}.topbar-user{display:flex;align-items:center;gap:10px;color:var(--ink);font-size:14px;}.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--crimson);color:#fff;font-weight:700;}
.content{padding:24px 28px;display:flex;flex-direction:column;gap:20px;}
.card{background:var(--surface);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);}
.table-wrap{overflow-x:auto;}
.table{width:100%;border-collapse:collapse;}
.table th,.table td{padding:14px 16px;border-bottom:1px solid #ecebf1;text-align:left;vertical-align:top;}
.table th{background:#e8d191;color:#130301;text-transform:uppercase;font-size:12px;letter-spacing:.08em;}
.status-badge{padding:7px 12px;border-radius:999px;font-size:12px;display:inline-flex;align-items:center;}
.status-login{background:#d1fae5;color:#0f5132;}
.status-logout{background:#cff4fc;color:#055160;}
.status-failed{background:#fde2e2;color:#9b1c1c;}
.pagination{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.pagination a{padding:8px 12px;border-radius:10px;background:#fff;border:1px solid #d6d2d9;color:#130301;text-decoration:none;font-weight:600;}
.pagination span{padding:8px 12px;font-weight:700;}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><div class="brand">Casa Gunita</div></div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="menu.php"><span class="icon">🍽️</span> Menu</a></li>
        <li><a href="feature.php"><span class="icon">⭐</span> Feature</a></li>
        <li><a href="modifiers.php"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php" class="active"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php"><span class="icon">🚪</span> Logout</a></div>
</aside>
<div class="main">
    <header class="topbar">
        <div class="topbar-title">Audit Log</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user"><div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div><span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
    </header>
    <div class="content">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-weight:700;font-size:1.05rem;">All audit records</div>
                    <div style="color:#555; margin-top:6px;">Showing <?= mysqli_num_rows($logs) ?> of <?= $totalRows ?> records.</div>
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="audit.php?page=<?= $page - 1 ?>">Previous</a>
                    <?php endif; ?>
                    <span>Page <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="audit.php?page=<?= $page + 1 ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Admin / User</th>
                        <th>Target</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($logs) === 0): ?>
                        <tr><td colspan="5" style="text-align:center;color:#777;padding:40px 0;">No audit entries yet.</td></tr>
                    <?php else: ?>
                        <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge <?php
                                        if ($log['action'] === 'login') echo 'status-login';
                                        elseif ($log['action'] === 'logout') echo 'status-logout';
                                        elseif ($log['action'] === 'failed_login') echo 'status-failed';
                                        else echo 'status-login';
                                    ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', ucfirst($log['action'])), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['admin_name'] ?: $log['customer_name'] ?: 'System', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars($log['target_type'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($log['target_id']): ?>(#<?= $log['target_id'] ?>)<?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($log['details'] ?: '-', ENT_QUOTES, 'UTF-8')) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card" style="display:flex;justify-content:flex-end;">
            <div class="pagination">
                <?php if ($page > 1): ?><a href="audit.php?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="audit.php?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
