<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$search_id = trim($_GET['search_id'] ?? '');

$sql = "SELECT user_id, first_name, last_name, email, created_at FROM users WHERE role = ?";
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>

<aside class="sidebar">
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
                    <input class="input-group" type="text" name="search_id" placeholder="Search Customer ID" value="<?= htmlspecialchars($search_id, ENT_QUOTES, 'UTF-8') ?>" oninput="this.value = this.value.replace(/[^0-9-]/g, '')">
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
                            <td><span class="customer-name"><?= htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="customer-email"><?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="date-text"><?= htmlspecialchars($customer['created_at'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <button type="button" class="receipt-link access-log" data-user-id="<?= $customer['user_id'] ?>">User Log</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div id="accessLogOverlay" class="access-log-overlay" aria-hidden="true">
    <div class="access-log-modal">
        <div class="access-log-header">
            <div>
                <div id="accessLogCustomerName" class="access-log-title">Customer User Log</div>
                <div id="accessLogCustomerMeta" class="access-log-meta">Loading…</div>
            </div>
            <div class="access-log-header-right">
                <input type="text" id="accessLogDateRange" class="date-range-input flatpickr-input" placeholder="Record date range" autocomplete="off">
                <button type="button" id="clearAccessLogDate" class="clear-date-btn is-hidden">Clear</button>
                <input type="hidden" id="accessLogDateFrom">
                <input type="hidden" id="accessLogDateTo">
                <button type="button" class="btn btn-gray" id="closeAccessLog">Close</button>
            </div>
        </div>
        <div id="accessLogBody" class="access-log-body">
            <div class="empty-inner" style="padding: 40px 24px;">
                <p>Select a customer to view their login/logout history.</p>
            </div>
        </div>
    </div>
</div>

<script>
const openAccessLogButtons = document.querySelectorAll('.access-log');
const accessLogOverlay = document.getElementById('accessLogOverlay');
const closeAccessLog = document.getElementById('closeAccessLog');
const accessLogBody = document.getElementById('accessLogBody');
const accessLogCustomerName = document.getElementById('accessLogCustomerName');
const accessLogCustomerMeta = document.getElementById('accessLogCustomerMeta');
const accessLogDateFrom = document.getElementById('accessLogDateFrom');
const accessLogDateTo = document.getElementById('accessLogDateTo');
const applyAccessLogFilter = document.getElementById('applyAccessLogFilter');
const resetAccessLogFilter = document.getElementById('resetAccessLogFilter');
let currentAccessLogUserId = null;

async function fetchAccessLog(userId, dateFrom = '', dateTo = '') {
    accessLogCustomerName.textContent = 'Loading user log...';
    accessLogCustomerMeta.textContent = '';
    accessLogBody.innerHTML = '<div class="empty-inner" style="padding: 40px 24px;"><p>Loading logs…</p></div>';
    accessLogDateFrom.value = dateFrom;
    accessLogDateTo.value = dateTo;
    accessLogOverlay.classList.add('open');
    accessLogOverlay.setAttribute('aria-hidden', 'false');
    currentAccessLogUserId = userId;

    let url = `customer_logs.php?user_id=${encodeURIComponent(userId)}`;
    if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
    if (dateTo) url += `&date_to=${encodeURIComponent(dateTo)}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to load logs');
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'No logs');

        const customerName = `${data.first_name} ${data.last_name}`.trim();
        accessLogCustomerName.textContent = customerName || `Customer #${data.user_id}`;
        accessLogCustomerMeta.textContent = `Customer ID: ${data.user_id} · Joined: ${data.created_at}`;

        if (!data.logs || data.logs.length === 0) {
            accessLogBody.innerHTML = '<div class="empty-inner" style="padding: 40px 24px;"><p>No login/logout activity found for this customer.</p></div>';
            return;
        }

        const rows = data.logs.map(log => {
            const eventType = (log.event_type || '').trim();
            let title;
            let badgeClass = 'badge-success';

            switch (eventType) {
                case 'failed_login':
                    title = 'FAILED LOGIN';
                    badgeClass = 'badge-failed';
                    break;
                case 'logout':
                    title = 'LOGOUT';
                    break;
                case 'login':
                    title = 'LOGIN';
                    break;
                case 'password_change_success':
                    title = 'PASSWORD CHANGE SUCCESS';
                    break;
                case 'password_change_failed':
                    title = 'PASSWORD CHANGE FAILED';
                    badgeClass = 'badge-failed';
                    break;
                case 'log_entry':
                    title = 'LOG ENTRY';
                    break;
                default:
                    title = eventType ? eventType.replace(/_/g, ' ').toUpperCase() : 'LOG ENTRY';
            }

            return `
            <div class="access-log-row">
                <div class="access-log-event">
                    <span class="badge ${badgeClass}">
                        ${title}
                    </span>
                </div>
                <div class="access-log-time">${log.event_time || 'Unknown time'}</div>
            </div>
        `;
        }).join('');

        accessLogBody.innerHTML = `<div class="access-log-list">${rows}</div>`;
    } catch (err) {
        accessLogBody.innerHTML = `<div class="empty-inner" style="padding: 40px 24px;"><p>${err.message}</p></div>`;
    }
}

openAccessLogButtons.forEach(button => {
    button.addEventListener('click', () => {
        fetchAccessLog(button.dataset.userId);
    });
});

if (resetAccessLogFilter) {
    resetAccessLogFilter.addEventListener('click', () => {
        if (!currentAccessLogUserId) return;
        document.getElementById('accessLogDateRange').value = '';
        accessLogDateFrom.value = '';
        accessLogDateTo.value = '';
        fetchAccessLog(currentAccessLogUserId, '', '');
    });
}

closeAccessLog.addEventListener('click', () => {
    accessLogOverlay.classList.remove('open');
    accessLogOverlay.setAttribute('aria-hidden', 'true');
});

accessLogOverlay.addEventListener('click', e => {
    if (e.target === accessLogOverlay) {
        accessLogOverlay.classList.remove('open');
        accessLogOverlay.setAttribute('aria-hidden', 'true');
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr('#accessLogDateRange', {
    mode: 'range',
    dateFormat: 'Y-m-d',
    allowInput: true,
    onChange: function(selectedDates, dateStr, instance) {
        var from = document.getElementById('accessLogDateFrom');
        var to = document.getElementById('accessLogDateTo');
        from.value = selectedDates[0] ? instance.formatDate(selectedDates[0], 'Y-m-d') : '';
        to.value = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : '';
        document.getElementById('clearAccessLogDate').classList.toggle('is-hidden', selectedDates.length === 0);
        if (selectedDates.length === 2 && currentAccessLogUserId) {
            fetchAccessLog(currentAccessLogUserId, from.value, to.value);
        }
    }
});

document.getElementById('accessLogDateRange').addEventListener('input', function() {
    if (this.value.trim() === '') {
        document.getElementById('accessLogDateFrom').value = '';
        document.getElementById('accessLogDateTo').value = '';
        document.getElementById('clearAccessLogDate').classList.add('is-hidden');
    }
});

document.getElementById('clearAccessLogDate').addEventListener('click', function() {
    document.getElementById('accessLogDateRange').value = '';
    document.getElementById('accessLogDateFrom').value = '';
    document.getElementById('accessLogDateTo').value = '';
    document.getElementById('clearAccessLogDate').classList.add('is-hidden');
    if (currentAccessLogUserId) {
        fetchAccessLog(currentAccessLogUserId, '', '');
    }
});
</script>

</body>
</html>