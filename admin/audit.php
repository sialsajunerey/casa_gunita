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
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$action_filter = $_GET['action_filter'] ?? [];
if (!is_array($action_filter)) {
    $action_filter = trim($action_filter) !== '' ? [trim($action_filter)] : [];
}

$date_range_value = '';
if ($date_from !== '' && $date_to !== '') {
    $date_range_value = $date_from . ' to ' . $date_to;
} elseif ($date_from !== '') {
    $date_range_value = $date_from;
}

$action_filters = [
    'login'               => ['label' => 'Login',               'actions' => ['login']],
    'failed_login'        => ['label' => 'Failed Log In',       'actions' => ['failed_login']],
    'logout'              => ['label' => 'Logout',              'actions' => ['logout']],
    'menu_edit'           => ['label' => 'Menu Edit',           'actions' => ['menu_edit']],
    'menu_add'            => ['label' => 'Menu Add',            'actions' => ['menu_add']],
    'menu_delete'         => ['label' => 'Menu Delete',         'actions' => ['menu_delete']],
    'category_edit'       => ['label' => 'Category Edit',       'actions' => ['category_edit']],
    'category_add'        => ['label' => 'Category Add',        'actions' => ['category_add']],
    'category_delete'     => ['label' => 'Category Delete',     'actions' => ['category_delete']],
    'customization_edit'   => ['label' => 'Customization Edit',   'actions' => ['modifier_edit'], 'type' => 'global'],
    'customization_add'    => ['label' => 'Customization Add',    'actions' => ['modifier_add'], 'type' => 'global'],
    'customization_delete' => ['label' => 'Customization Delete', 'actions' => ['modifier_delete'], 'type' => 'global'],
    'menu_customization_edit' => ['label' => 'Menu Customization Edit', 'actions' => ['modifier_edit'], 'type' => 'menu'],
    'menu_customization_add'  => ['label' => 'Menu Customization Add',  'actions' => ['modifier_add'], 'type' => 'menu'],
    'menu_customization_del'  => ['label' => 'Menu Customization Delete', 'actions' => ['modifier_delete'], 'type' => 'menu'],
    'order_status_change' => ['label' => 'Order Status Change', 'actions' => ['order_status_change']],
];

$where = [];
$bind_params = [];
$types = '';

if ($date_from !== '' && $date_to !== '') {
    $where[] = "DATE(a.created_at) BETWEEN ? AND ?";
    $bind_params[] = $date_from;
    $bind_params[] = $date_to;
    $types .= 'ss';
} elseif ($date_from !== '') {
    $where[] = "DATE(a.created_at) >= ?";
    $bind_params[] = $date_from;
    $types .= 's';
}

if (!empty($action_filter)) {
    $clauses = [];
    foreach ($action_filter as $filter_key) {
        if (isset($action_filters[$filter_key])) {
            $cfg = $action_filters[$filter_key];
            $actions = $cfg['actions'];
            $placeholders = implode(',', array_fill(0, count($actions), '?'));

            $sub_where = "(a.action IN ($placeholders)";
            foreach ($actions as $act) {
                $bind_params[] = $act;
                $types .= 's';
            }

            if (str_contains($filter_key, 'customization')) {
                $term = str_contains($filter_key, 'add') ? 'Added' : (str_contains($filter_key, 'edit') ? 'Updated' : 'Deleted');
                $sub_where .= " OR (a.action = '' AND a.details LIKE ?)";
                $bind_params[] = '%' . $term . '%';
                $types .= 's';
            }
            $sub_where .= ")";

            if (isset($cfg['type']) && $cfg['type'] === 'menu') {
                $sub_where .= " AND a.product_id IS NOT NULL";
            } elseif (isset($cfg['type']) && $cfg['type'] === 'global') {
                $sub_where .= " AND a.product_id IS NULL";
            }
            $clauses[] = "($sub_where)";
        }
    }
    if (!empty($clauses)) {
        $where[] = "(" . implode(' OR ', $clauses) . ")";
    }
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$filter_params = [];
if ($date_from !== '') $filter_params['date_from'] = $date_from;
if ($date_to !== '') $filter_params['date_to'] = $date_to;
if (!empty($action_filter)) $filter_params['action_filter'] = $action_filter;
$paginationUrl = function (int $targetPage) use ($filter_params): string {
    return 'audit.php?' . http_build_query(array_merge($filter_params, ['page' => $targetPage]));
};

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM audit_logs a $where_sql");
if (!empty($bind_params)) {
    mysqli_stmt_bind_param($countStmt, $types, ...$bind_params);
}
mysqli_stmt_execute($countStmt);
$totalRows = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = mysqli_prepare($conn,
    "SELECT a.*, CONCAT_WS(' ', ua.first_name, ua.last_name) AS admin_name, CONCAT_WS(' ', uc.first_name, uc.last_name) AS customer_name
     FROM audit_logs a
     LEFT JOIN users ua ON a.admin_id = ua.user_id
     LEFT JOIN users uc ON a.customer_id = uc.user_id
     $where_sql
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?");
$query_params = $bind_params;
$query_params[] = $perPage;
$query_params[] = $offset;
mysqli_stmt_bind_param($stmt, $types . 'ii', ...$query_params);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="audit.css?v=<?= filemtime('audit.css') ?>">
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
    .top-bar-right { flex-wrap: wrap; }
    .filter-row { flex-wrap: wrap; }
    .audit-table th:nth-child(3),
    .audit-table td:nth-child(3) { display: none; }
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
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php" class="active">Audit Log</a></li>
        <li><a href="analytics.php">Analytics</a></li>
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
                <form method="GET" class="filter-row" id="filter-form">
                    <input type="text" id="date-range" class="date-range-input" placeholder="Record date range" value="<?= htmlspecialchars($date_range_value, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <button type="button" id="clear-date" class="clear-date-btn <?= $date_range_value === '' ? 'is-hidden' : '' ?>">Clear</button>
                    <input type="hidden" name="date_from" id="date-from" value="<?= htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="date_to" id="date-to" value="<?= htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="custom-multiselect" id="actionMultiselect">
                        <div class="multiselect-header">
                            <span class="multiselect-summary">Filter Actions</span>
                        </div>
                        <div class="multiselect-dropdown">
                            <label class="multiselect-item" style="border-bottom: 1px solid var(--border); font-weight: 600; color: var(--muted); cursor: default;">Select Actions</label>
                            <?php foreach ($action_filters as $value => $config): ?>
                                <label class="multiselect-item">
                                    <input type="checkbox" name="action_filter[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($value, $action_filter) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" id="clear-actions" class="clear-date-btn <?= empty($action_filter) ? 'is-hidden' : '' ?>">Clear</button>
                    <button type="submit" class="btn btn-primary <?= (!empty($action_filter) || $date_range_value !== '') ? 'is-hidden' : '' ?>">Filter</button>
                </form>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars($paginationUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-gray">Previous</a>
                    <?php endif; ?>
                    <span class="page-indicator">Page <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars($paginationUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Next</a>
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
                                elseif (str_contains($action, 'menu') || str_contains($action, 'customization') || str_contains($action, 'modifier') || $log['target_type'] === 'customization') $badgeClass = 'badge-menu';
                                elseif (str_contains($action, 'featured')) $badgeClass = 'badge-feature';
                            ?>
                            <?php
                                $details = $log['details'] ?: '';
                                if (!empty($action)) {
                                    $displayAction = ucwords(str_replace('_', ' ', $action));
                                    $displayAction = str_replace('Modifier', 'Customization', $displayAction);
                                } else {
                                    if (str_contains($details, 'Added')) $displayAction = 'Customization Add';
                                    elseif (str_contains($details, 'Updated')) $displayAction = 'Customization Edit';
                                    elseif (str_contains($details, 'Deleted')) $displayAction = 'Customization Delete';
                                    else $displayAction = 'Customization';
                                }

                                if (!empty($log['product_id']) && str_contains($displayAction, 'Customization')) {
                                    $displayAction = 'Menu ' . $displayAction;
                                }

                                $displayDetails = ($action === 'failed_login') ? 'Failed log in' : preg_replace('/\s*\(?IP:\s*[\d\.]+\)?/i', '', $details ?: '—');
                            ?>
                            <tr>
                                <td><span class="date-text"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($displayAction, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><span class="user-name"><?= htmlspecialchars($log['admin_name'] ?: $log['customer_name'] ?: 'System', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <span class="target-text">
                                        <?= htmlspecialchars($log['target_type'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($log['target_id']): ?><span class="target-id">(#<?= $log['target_id'] ?>)</span><?php endif; ?>
                                    </span>
                                </td>
                                <td><span class="details-text"><?= nl2br(htmlspecialchars($displayDetails, ENT_QUOTES, 'UTF-8')) ?></span></td>
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
                    <a href="<?= htmlspecialchars($paginationUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-gray">Previous</a>
                <?php endif; ?>
                <span class="page-indicator">Page <?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars($paginationUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div><!-- .main -->

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
/* ── Flatpickr date range ── */
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
        if (selectedDates.length === 2) instance.element.form.submit();
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

/* ── Custom Multi-select ── */
(function() {
    const ms = document.getElementById('actionMultiselect');
    const header = ms.querySelector('.multiselect-header');
    const dropdown = ms.querySelector('.multiselect-dropdown');
    const summary = ms.querySelector('.multiselect-summary');
    const checkboxes = ms.querySelectorAll('input[type="checkbox"]');

    function updateSummary() {
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        summary.textContent = checkedCount === 0
            ? 'Filter Actions'
            : checkedCount + (checkedCount === 1 ? ' Action' : ' Actions') + ' Selected';
    }

    const clearActionsBtn = document.getElementById('clear-actions');

    header.addEventListener('click', (e) => { ms.classList.toggle('active'); e.stopPropagation(); });
    document.addEventListener('click', () => ms.classList.remove('active'));
    clearActionsBtn.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = false);
        document.getElementById('filter-form').submit();
    });
    dropdown.addEventListener('click', (e) => e.stopPropagation());
    checkboxes.forEach(cb => cb.addEventListener('change', updateSummary));
    updateSummary();
})();

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