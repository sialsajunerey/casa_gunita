<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle AJAX status update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim($_POST['status']);
    $valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    
    if (in_array($new_status, $valid_statuses, true)) {
        // Try to update with updated_at, but fall back if column doesn't exist
        $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE order_id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $new_status, $order_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $new_status]);
            exit();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit();
}

$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to'] ?? '');
$search        = trim($_GET['search'] ?? '');
$raw_status_filter = $_GET['status'] ?? [];
if (!is_array($raw_status_filter)) {
    $raw_status_filter = trim($raw_status_filter) !== '' ? [trim($raw_status_filter)] : [];
}
$valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
$status_filter = array_values(array_intersect($raw_status_filter, $valid_statuses));
$type_filter   = trim($_GET['type'] ?? '');
$status_open = isset($_GET['status_open']) && $_GET['status_open'] === '1';
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
if (!empty($status_filter)) {
    $placeholders = implode(',', array_fill(0, count($status_filter), '?'));
    $where_clause .= " AND o.status IN ($placeholders)";
    foreach ($status_filter as $s) { $bind_params[] = $s; $types .= 's'; }
}
if (in_array($type_filter, $valid_types, true)) {
    $where_clause .= " AND o.order_type = ?";
    $bind_params[] = $type_filter;
    $types .= 's';
}

// Fetch all orders with customer name
$query = "SELECT o.*, CONCAT_WS(' ', u.first_name, u.last_name) AS customer_name 
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
                          WHERE o.status <> 'cancelled' $where_clause";
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
<link rel="stylesheet" href="customers.css">
<style>
/* ══════════════════════════════════════
   HAMBURGER + COLLAPSIBLE SIDEBAR
   Works on ALL screen sizes
══════════════════════════════════════ */

/* Always-visible hamburger in topbar */
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

/* Overlay — used on mobile */
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

/* Sidebar smooth slide */
.sidebar {
    transition: transform 0.3s ease;
    will-change: transform;
}

.sidebar.collapsed {
    transform: translateX(-100%);
}

.main {
    transition: margin-left 0.3s ease;
}

.main.expanded {
    margin-left: 0 !important;
}

/* ── Mobile ── */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 50;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .main,
    .main.expanded {
        margin-left: 0 !important;
    }
    .topbar { padding: 0 16px; gap: 12px; }
    .topbar-title { font-size: 0.95rem; }
    .content { padding: 16px; }
    .stats-row,
    .orders-stats { grid-template-columns: 1fr !important; }
    .header-card { flex-direction: column; align-items: stretch; gap: 12px; }
    .filter-row { flex-direction: column; align-items: stretch; }
    .filter-row .search-wrap,
    .filter-row input[type="text"],
    .filter-row select { width: 100% !important; }
    .filter-row .date-range-input { width: 100% !important; }
    .orders-table th:nth-child(4),
    .orders-table td:nth-child(4) { display: none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
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

<div class="main" id="main">
    <header class="topbar">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
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

        <div class="header-card">
            <h2>Orders</h2>
            <form method="GET" class="filter-row" id="filter-form">
                <div class="search-wrap">
                    <span class="search-icon"></span>
                    <input type="search" name="search" placeholder="Search Order ID"
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        oninput="this.value = this.value.replace(/[^0-9-]/g, ''); debounceSubmit()">
                </div>
                <input type="text" id="date-range" class="date-range-input" placeholder="Record date range"
                    value="<?= htmlspecialchars($date_range_value, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                <button type="button" id="clear-date" class="clear-date-btn <?= $date_range_value === '' ? 'is-hidden' : '' ?>">Clear</button>
                <input type="hidden" name="date_from" id="date-from" value="<?= htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="date_to" id="date-to" value="<?= htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') ?>">
                <div class="custom-multiselect<?= $status_open ? ' active' : '' ?>" id="statusMultiselect">
                    <div class="multiselect-header">
                        <span class="multiselect-summary">Status</span>
                    </div>
                    <div class="multiselect-dropdown">
                        <?php foreach (['pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <label class="multiselect-item">
                            <input type="checkbox" name="status[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                <?= in_array($value, $status_filter, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" id="clearStatusFilters" class="btn btn-gray <?= empty($status_filter) ? 'is-hidden' : '' ?>">Clear</button>
                <input type="hidden" name="status_open" id="status-open" value="<?= $status_open ? '1' : '' ?>">
                <select name="type" onchange="this.form.submit()">
                    <option value="">Order Type</option>
                    <option value="takeout"  <?= $type_filter === 'takeout'  ? 'selected' : '' ?>>Pick-Up</option>
                    <option value="delivery" <?= $type_filter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                </select>
            </form>
        </div>

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
                                <?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <span class="type-pill">
                                <?= $order['order_type'] === 'takeout' ? 'Pick-Up' : ucfirst($order['order_type']) ?>
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
                        <td><span class="total-amount"><?= formatPrice($order['total_amount']) ?></span></td>
                        <td><span class="badge <?= $order['status'] ?>"><?= strtoupper($order['status']) ?></span></td>
                        <td><span class="date-text"><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></span></td>
                        <td>
                            <a class="receipt-link" href="receipt.php?order_id=<?= $order['order_id'] ?>&from=orders">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

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
        var to   = document.getElementById('date-to');
        from.value = selectedDates[0] ? instance.formatDate(selectedDates[0], 'Y-m-d') : '';
        to.value   = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : '';
        document.getElementById('clear-date').classList.toggle('is-hidden', selectedDates.length === 0);
        if (selectedDates.length === 2) instance.element.form.submit();
    }
});

document.getElementById('date-range').addEventListener('input', function() {
    if (this.value.trim() === '') {
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value   = '';
        document.getElementById('clear-date').classList.add('is-hidden');
    }
});

document.getElementById('clear-date').addEventListener('click', function() {
    document.getElementById('date-range').value = '';
    document.getElementById('date-from').value  = '';
    document.getElementById('date-to').value    = '';
    document.getElementById('filter-form').submit();
});

// Status multiselect behavior (uses styles from customers.css)
const statusMultiselect = document.getElementById('statusMultiselect');
const statusCheckboxes = statusMultiselect ? Array.from(statusMultiselect.querySelectorAll('input[type="checkbox"]')) : [];
const clearStatusFilters = document.getElementById('clearStatusFilters');

function updateStatusSummary() {
    if (!statusMultiselect) return;
    const checked = statusCheckboxes.filter(cb => cb.checked);
    const summary = statusMultiselect.querySelector('.multiselect-summary');
    if (!summary) return;
    summary.textContent = checked.length === 0
        ? 'Status'
        : (checked.length + (checked.length === 1 ? ' Status' : ' Statuses') + ' Selected');
    clearStatusFilters?.classList.toggle('is-hidden', checked.length === 0);
}

if (statusMultiselect) {
    const header = statusMultiselect.querySelector('.multiselect-header');
    const dropdown = statusMultiselect.querySelector('.multiselect-dropdown');
    header?.addEventListener('click', e => {
        statusMultiselect.classList.toggle('active');
        e.stopPropagation();
    });
    dropdown?.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', () => statusMultiselect.classList.remove('active'));
}

statusCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
        updateStatusSummary();
        const statusOpenInput = document.getElementById('status-open');
        if (statusOpenInput) statusOpenInput.value = '1';
        debounceSubmit();
    });
});

if (clearStatusFilters) {
    clearStatusFilters.addEventListener('click', () => {
        statusCheckboxes.forEach(cb => cb.checked = false);
        updateStatusSummary();
        const statusOpenInput = document.getElementById('status-open');
        if (statusOpenInput) statusOpenInput.value = '1';
        debounceSubmit();
    });
}

updateStatusSummary();

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

/* Restore saved state on load */
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