<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id  = (int)$_POST['order_id'];
    $newstatus = $_POST['status'];

    $stmt = mysqli_prepare($conn,
        "UPDATE orders SET status = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $newstatus, $order_id);
    mysqli_stmt_execute($stmt);

    $admin_id = $_SESSION['user_id'] ?? null;
    $audit_stmt = mysqli_prepare($conn,
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, order_id, details)
         VALUES (?, 'order_status_change', 'order', ?, ?, ?)");
    $details = "Changed order status to: " . strtoupper($newstatus);
    mysqli_stmt_bind_param($audit_stmt, 'iiss', $admin_id, $order_id, $order_id, $details);
    mysqli_stmt_execute($audit_stmt);

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    header("Location: orders.php");
    exit();
}

// Filter by date
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search      = trim($_GET['search'] ?? '');
$where_clause = '';
$bind_params = [];
$types = '';
if ($filter_date) {
    $where_clause .= " AND DATE(o.created_at) = ?";
    $bind_params[] = $filter_date;
    $types .= 's';
}
if ($search) {
    $where_clause .= " AND (o.order_id LIKE ? OR u.full_name LIKE ?)";
    $bind_params[] = '%' . $search . '%';
    $bind_params[] = '%' . $search . '%';
    $types .= 'ss';
}

// Fetch all orders with customer name
$query = "SELECT o.*, u.full_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.user_id 
          WHERE 1=1 $where_clause
          ORDER BY o.created_at DESC";
$stmt = mysqli_prepare($conn, $query);
if (!empty($bind_params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$bind_params);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);

// Build orders array + status counts
$all_orders = [];
$counts = ['all' => 0, 'pending' => 0, 'preparing' => 0, 'ready' => 0, 'completed' => 0, 'cancelled' => 0];
if ($orders && mysqli_num_rows($orders) > 0) {
    while ($row = mysqli_fetch_assoc($orders)) {
        $all_orders[] = $row;
        $counts['all']++;
        if (isset($counts[$row['status']])) $counts[$row['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders — Casa Gunita Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="orders.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php" class="active">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="modifiers.php">Modifiers</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Orders</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <!-- Header + Filter -->
        <div class="header-card">
            <h2>Orders</h2>
            <form method="GET" class="filter-row" id="filter-form">
                <span class="search-icon"></span>
                <input type="text" name="search" placeholder="Search Order ID or Customer Name" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <input type="date" name="date"
                       value="<?= htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                <button type="submit">Go</button>
            </form>
        </div>

        <!-- Summary Chips -->
        <div class="summary-chips">
            <div class="chip">All <span class="chip-count"><?= $counts['all'] ?></span></div>
            <?php foreach (['pending','preparing','ready','completed','cancelled'] as $s): ?>
                <?php if ($counts[$s]): ?>
                <div class="chip"><?= ucfirst($s) ?> <span class="chip-count"><?= $counts[$s] ?></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Update Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($all_orders)): ?>
                    <tr class="empty-row">
                        <td colspan="9">
                            <div class="empty-inner">
                                <p>No orders yet. Waiting for customers...</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_orders as $order): ?>
                    <tr>
                        <td>
                            <span class="order-num">
                                #<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td>
                            <span class="customer-name">
                                <?= htmlspecialchars($order['full_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <span class="type-pill">
                                <?= ucfirst($order['order_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                $address = trim(($order['house_number'] ?? '') . ' ' . ($order['street'] ?? ''));
                                $addressParts = array_filter([
                                    $address,
                                    $order['barangay'] ?? '',
                                    $order['city']     ?? ''
                                ]);
                            ?>
                            <span class="address-text">
                                <?= $addressParts
                                    ? htmlspecialchars(implode(', ', $addressParts), ENT_QUOTES, 'UTF-8')
                                    : '<span style="color:#bbb;">N/A</span>' ?>
                            </span>
                        </td>
                        <td>
                            <span class="total-amount">
                                <?= formatPrice($order['total_amount']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $order['status'] ?>">
                                <?= strtoupper($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="date-text">
                                <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                            <form method="POST" class="status-select-wrap ajax-status-form">
                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                <select name="status" onchange="this.form.dispatchEvent(new Event('submit'))">
                                    <option value="pending"   <?= $order['status']==='pending'   ? 'selected':'' ?>>Pending</option>
                                    <option value="preparing" <?= $order['status']==='preparing' ? 'selected':'' ?>>Preparing</option>
                                    <option value="ready"     <?= $order['status']==='ready'     ? 'selected':'' ?>>Ready</option>
                                    <option value="completed" <?= $order['status']==='completed' ? 'selected':'' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['status']==='cancelled' ? 'selected':'' ?>>Cancelled</option>
                                </select>
                            </form>
                            <?php else: ?>
                                <span class="finalized-label">Finalized</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="receipt-link"
                               href="receipt.php?order_id=<?= $order['order_id'] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- .content -->
</div><!-- .main -->

<div class="toast" id="toast">Status updated!</div>

<script>
document.querySelectorAll('.ajax-status-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var data = new FormData(form);
        fetch('orders.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                showToast('Status updated!');
                setTimeout(function() { location.reload(); }, 800);
            }
        });
    });
});

function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2500);
}
</script>

</body>
</html>