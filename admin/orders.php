<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to'] ?? '');
$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$type_filter   = trim($_GET['type'] ?? '');
$valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
$valid_types = ['takeout', 'delivery'];
$date_range_value = '';
if ($date_from !== '' && $date_to !== '') {
    $date_range_value = $date_from . ' to ' . $date_to;
} elseif ($date_from !== '') {
    $date_range_value = $date_from;
}

$where_clause = '';
$bind_params = [];
$types = '';
if ($date_from !== '' && $date_to !== '') {
    $where_clause .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $bind_params[] = $date_from;
    $bind_params[] = $date_to;
    $types .= 'ss';
} elseif ($date_from !== '') {
    $where_clause .= " AND DATE(o.created_at) >= ?";
    $bind_params[] = $date_from;
    $types .= 's';
}
if ($search) {
    $where_clause .= " AND o.order_id LIKE ?";
    $bind_params[] = '%' . $search . '%';
    $types .= 's';
}
if (in_array($status_filter, $valid_statuses, true)) {
    $where_clause .= " AND o.status = ?";
    $bind_params[] = $status_filter;
    $types .= 's';
}
if (in_array($type_filter, $valid_types, true)) {
    $where_clause .= " AND o.order_type = ?";
    $bind_params[] = $type_filter;
    $types .= 's';
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

$filtered_total_orders = count($all_orders);
$revenue_query = "SELECT COALESCE(SUM(t.amount_paid), 0) AS total
                  FROM orders o
                  JOIN users u ON o.user_id = u.user_id
                  LEFT JOIN transactions t ON o.order_id = t.order_id
                  WHERE 1=1 $where_clause";
$revenue_stmt = mysqli_prepare($conn, $revenue_query);
if (!empty($bind_params)) {
    mysqli_stmt_bind_param($revenue_stmt, $types, ...$bind_params);
}
mysqli_stmt_execute($revenue_stmt);
$filtered_total_revenue = mysqli_fetch_assoc(mysqli_stmt_get_result($revenue_stmt))['total'];
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="orders.css?v=3">
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
        <li><a href="customizations.php">Customizations</a></li>
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

        <div class="stats-row orders-stats">
            <div class="stat-card order-stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Orders</div>
                    <div class="num"><?= $filtered_total_orders ?></div>
                </div>
            </div>
            <div class="stat-card order-stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Revenue</div>
                    <div class="num"><?= formatPrice($filtered_total_revenue) ?></div>
                </div>
            </div>
        </div>

        <!-- Header + Filter -->
        <div class="header-card">
            <h2>Orders</h2>
            <form method="GET" class="filter-row" id="filter-form">
                <div class="search-wrap">
                    <span class="search-icon"></span>
                    <input type="search" name="search" placeholder="Search Order ID" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9-]/g, ''); debounceSubmit()">
                </div>
                <input type="text" id="date-range" class="date-range-input" placeholder="Record date range" value="<?= htmlspecialchars($date_range_value, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                <button type="button" id="clear-date" class="clear-date-btn <?= $date_range_value === '' ? 'is-hidden' : '' ?>">Clear</button>
                <input type="hidden" name="date_from" id="date-from" value="<?= htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="date_to" id="date-to" value="<?= htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') ?>">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                    <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="takeout" <?= $type_filter === 'takeout' ? 'selected' : '' ?>>Takeout</option>
                    <option value="delivery" <?= $type_filter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                </select>
            </form>
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
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($all_orders)): ?>
                    <tr class="empty-row">
                        <td colspan="8">
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
                            <a class="receipt-link"
                               href="receipt.php?order_id=<?= $order['order_id'] ?>&from=orders">
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

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
var searchTimer;
function debounceSubmit() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() {
        document.getElementById('filter-form').submit();
    }, 500);
}

flatpickr('#date-range', {
    mode: 'range',
    dateFormat: 'Y-m-d',
    allowInput: true,
    defaultDate: [
        <?= $date_from !== '' ? "'" . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . "'" : 'null' ?>,
        <?= $date_to !== '' ? "'" . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . "'" : 'null' ?>
    ].filter(Boolean),
    onChange: function(selectedDates, dateStr, instance) {
        var from = document.getElementById('date-from');
        var to = document.getElementById('date-to');
        from.value = selectedDates[0] ? instance.formatDate(selectedDates[0], 'Y-m-d') : '';
        to.value = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : '';
        document.getElementById('clear-date').classList.toggle('is-hidden', selectedDates.length === 0);
        if (selectedDates.length === 2) {
            instance.element.form.submit();
        }
    }
});

document.getElementById('date-range').addEventListener('input', function() {
    if (this.value.trim() === '') {
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value = '';
        document.getElementById('clear-date').classList.add('is-hidden');
    }
});

document.getElementById('clear-date').addEventListener('click', function() {
    document.getElementById('date-range').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('filter-form').submit();
});
</script>

</body>
</html>
