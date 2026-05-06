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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="audit.css">
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
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php" class="active">Audit</a></li>
    </ul>
    <div class="sidebar-footer"><a href="logout.php">Logout</a></div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Audit Log</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <div class="card top-bar">
            <div class="top-bar-left">
                <div>
                    <div class="top-bar-title">All Audit Records</div>
                    <div class="top-bar-meta">Showing <?= mysqli_num_rows($logs) ?> of <?= $totalRows ?> records</div>
                </div>
            </div>
            <div class="top-bar-right">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="audit.php?page=<?= $page - 1 ?>" class="btn btn-gray">Previous</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="audit.php?page=<?= $page + 1 ?>" class="btn btn-primary">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-card">
            <table class="audit-table">
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
                        <tr class="empty-row">
                            <td colspan="5">
                                <div class="empty-inner"><p>No audit entries yet.</p></div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                            <?php
                                $action = $log['action'];
                                $badgeClass = 'badge-default';
                                if ($action === 'login') $badgeClass = 'badge-login';
                                elseif ($action === 'logout') $badgeClass = 'badge-logout';
                                elseif ($action === 'failed_login') $badgeClass = 'badge-failed';
                                elseif (str_contains($action, 'order')) $badgeClass = 'badge-order';
                                elseif (str_contains($action, 'menu') || str_contains($action, 'modifier')) $badgeClass = 'badge-menu';
                                elseif (str_contains($action, 'featured')) $badgeClass = 'badge-feature';
                            ?>
                            <tr>
                                <td><span class="date-text"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', ucfirst($action)), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><span class="user-name"><?= htmlspecialchars($log['admin_name'] ?: $log['customer_name'] ?: 'System', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <span class="target-text">
                                        <?= htmlspecialchars($log['target_type'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($log['target_id']): ?><span class="target-id">(#<?= $log['target_id'] ?>)</span><?php endif; ?>
                                    </span>
                                </td>
                                <td><span class="details-text"><?= nl2br(htmlspecialchars($log['details'] ?: '—', ENT_QUOTES, 'UTF-8')) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card" style="display:flex;justify-content:flex-end;">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="audit.php?page=<?= $page - 1 ?>" class="btn btn-gray">Previous</a>
                <?php endif; ?>
                <span class="page-indicator">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="audit.php?page=<?= $page + 1 ?>" class="btn btn-primary">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>