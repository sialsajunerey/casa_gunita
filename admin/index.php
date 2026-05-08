<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$search = trim($_GET['search'] ?? '');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
if ($search) $where[] = "o.order_id LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
if ($status_filter) $where[] = "o.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pending = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders o WHERE o.status = 'pending'"))['total'];

$total_orders = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders"))['total'];

$total_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(t.amount_paid), 0) AS total 
     FROM orders o 
     LEFT JOIN transactions t ON o.order_id = t.order_id"))['total'];


$orders_result = mysqli_query($conn,
    "SELECT o.*, u.full_name, u.email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     $where_sql
     ORDER BY o.created_at DESC
     LIMIT 60");

$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
}

$order_ids = array_column($orders, 'order_id');
$items_map = [];
$trans_map = [];

if ($order_ids) {
    $ids_sql = implode(',', $order_ids);

    $columnExists = mysqli_query($conn, "SHOW COLUMNS FROM order_items LIKE 'options'");
    if ($columnExists && mysqli_num_rows($columnExists) === 0) {
        mysqli_query($conn, "ALTER TABLE order_items ADD COLUMN options TEXT NULL");
    }

    $items_res = mysqli_query($conn,
        "SELECT oi.order_id, p.name, oi.quantity, oi.subtotal, oi.unit_price, oi.options
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id IN ($ids_sql)");
    while ($item = mysqli_fetch_assoc($items_res)) {
        $item['options'] = !empty($item['options']) ? json_decode($item['options'], true) ?: [] : [];
        $items_map[$item['order_id']][] = $item;
    }

    $trans_res = mysqli_query($conn, "SELECT * FROM transactions WHERE order_id IN ($ids_sql)");
    while ($t = mysqli_fetch_assoc($trans_res)) {
        $trans_map[$t['order_id']] = $t;
    }
}

$orders_js = [];
foreach ($orders as $o) {
    $oid   = $o['order_id'];
    $items = $items_map[$oid] ?? [];
    $trans = $trans_map[$oid] ?? null;

    $summary_parts = array_map(fn($i) => $i['quantity'] . 'x ' . $i['name'], $items);
    $summary       = implode(', ', $summary_parts) ?: '—';

    $address = trim(($o['house_number'] ?? '') . ' ' . ($o['street'] ?? ''));
    $addressParts = array_filter([$address, $o['barangay'] ?? '', $o['city'] ?? '']);

    $orders_js[] = [
        'order_id'       => $oid,
        'full_name'      => $o['full_name'],
        'email'          => $o['email'],
        'order_type'     => $o['order_type'],
        'status'         => $o['status'],
        'total_amount'   => $o['total_amount'],
        'notes'          => $o['notes'],
        'created_at'     => $o['created_at'],
        'summary'        => $summary,
        'items'          => $items,
        'address'        => $addressParts ? implode(', ', $addressParts) : null,
        'payment_method' => $trans['payment_method'] ?? null,
        'amount_paid'    => $trans['amount_paid']    ?? null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Casa Gunita Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="index.css">
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php" class="active">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
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
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Pending Orders</div>
                    <div class="num"><?= $pending ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Orders</div>
                    <div class="num"><?= $total_orders ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Revenue</div>
                    <div class="num"><?= formatPrice($total_revenue) ?></div>
                </div>
            </div>
        </div>

        <div class="orders-area">

            <div class="orders-panel">
                <div class="panel-header">
                    <h3>All Orders <span style="color:var(--muted);font-weight:400;font-size:13px;">(<?= count($orders) ?>)</span></h3>
                    <div class="panel-header-controls">
                        <div class="search-wrap">
                            <form method="GET" action="" id="search-form">
                                <span class="search-icon"></span>
                                <input type="text" name="search" placeholder="Search Order ID"
                                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" oninput="debounceSubmit()">
                                <?php if ($status_filter): ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="filter-tabs">
                    <?php
                    $tabs = [
                        ''          => 'All',
                        'pending'   => 'Pending',
                        'preparing' => 'Preparing',
                        'ready'     => 'Ready',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ];
                    foreach ($tabs as $val => $label):
                        $active = ($status_filter === $val) ? 'active' : '';
                        $href   = $val ? "?status=$val" : 'index.php';
                        if ($search) $href .= ($val ? "&search=$search" : "?search=$search");
                    ?>
                    <a href="<?= $href ?>" class="filter-tab <?= $active ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="orders-list" id="ordersList">
                    <?php if (empty($orders)): ?>
                        <p style="text-align:center;color:var(--muted);padding:30px;font-size:13.5px;">No orders found.</p>
                    <?php else: ?>
                        <?php foreach ($orders as $idx => $o):
                            $oid     = $o['order_id'];
                            $items   = $items_map[$oid] ?? [];
                            $summaryParts = array_map(fn($i) => $i['quantity'] . 'x ' . $i['name'], $items);
                            $summary = implode(', ', $summaryParts) ?: '—';
                            $badge   = 'badge-' . $o['status'];
                            $fmt_date = date('g:iA | d M Y', strtotime($o['created_at']));
                        ?>
                        <div class="order-card" data-idx="<?= $idx ?>" onclick="selectOrder(<?= $idx ?>)">
                            <div class="card-top">
                                <div>
                                    <div class="card-oid">Order ID #<?= str_pad($oid, 5, '0', STR_PAD_LEFT) ?></div>
                                    <div class="card-name"><?= htmlspecialchars($o['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="card-date"><?= $fmt_date ?></div>
                                </div>
                                <span class="badge <?= $badge ?>"><?= ucfirst($o['status']) ?></span>
                            </div>
                            <div class="order-summary-line" onclick="toggleExpand(event, <?= $idx ?>)">
                                <span class="order-summary-text"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="toggle-icon" id="toggle-icon-<?= $idx ?>">▼</span>
                            </div>
                            <div class="order-items-expanded" id="expanded-<?= $idx ?>">
                                <?php foreach ($items as $item): ?>
                                <div class="expanded-item">
                                    <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?> × <?= $item['quantity'] ?></span>
                                    <span><?= formatPrice($item['subtotal']) ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($items)): ?>
                                    <div style="color:var(--muted);font-size:12px;">No items found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-panel" id="detailPanel">
                <div class="detail-empty" id="detailEmpty">
                    <div class="icon">📄</div>
                    <p>Select an order to<br>view its details</p>
                </div>

                <div class="detail-content" id="detailContent">
                    <div class="detail-top">
                        <div>
                            <div class="detail-oid" id="d-oid"></div>
                            <div class="detail-name" id="d-name"></div>
                            <div class="detail-meta" id="d-meta"></div>
                        </div>
                        <div class="detail-actions">
                            <span class="badge" id="d-badge"></span>
                            <a id="d-receipt-link" href="#" class="btn-receipt">Receipt</a>
                        </div>
                    </div>

                    <div class="section-title">Update Status</div>
                    <form id="d-status-form" method="POST" action="#" class="status-form">
                        <input type="hidden" name="order_id" id="d-status-order-id" value="">
                        <div class="status-row">
                            <label for="d-status-select">Status</label>
                            <select name="status" id="d-status-select">
                                <option value="pending">Pending</option>
                                <option value="preparing">Preparing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </form>

                    <div class="section-title">Order Details</div>
                    <table class="items-table" id="d-items"></table>

                    <div class="section-title">Bill Details</div>
                    <div id="d-bill"></div>

                    <div id="d-payment-wrap">
                        <div class="section-title">Payment</div>
                        <div id="d-payment"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let timeout;
function debounceSubmit() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        const searchForm = document.getElementById('search-form');
        if (searchForm) searchForm.submit();
    }, 500);
}

const ORDERS = <?= json_encode($orders_js, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function formatPrice(n) {
    return '₱' + parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(s) {
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
}

function formatItemOptions(options) {
    if (!options || !options.length) return '';
    const groups = {};
    options.forEach(opt => {
        const group = opt.group_name || (opt.group_type === 'addon' ? 'Add-ons' : opt.group_type === 'size' ? 'Size' : 'Flavor');
        groups[group] = groups[group] || [];
        groups[group].push(opt);
    });
    return Object.entries(groups).map(([group, opts]) => {
        const items = opts.map(item => `${escHtml(item.name)}${item.additional_price && item.group_type === 'addon' ? ` (+${formatPrice(item.additional_price)})` : ''}`).join(', ');
        return `<div class="item-options"><strong>${escHtml(group)}:</strong> ${items}</div>`;
    }).join('');
}

let selectedOrderIndex = null;

function selectOrder(idx) {
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.order-card[data-idx="${idx}"]`);
    if (card) card.classList.add('selected');
    selectedOrderIndex = idx;

    const o = ORDERS[idx];
    if (!o) return;

    document.getElementById('d-oid').textContent  = 'Order ID #' + String(o.order_id).padStart(5, '0');
    document.getElementById('d-name').textContent = o.full_name;
    document.getElementById('d-meta').textContent = formatDate(o.created_at) + ' · ' + o.email;

    const badge = document.getElementById('d-badge');
    badge.textContent = o.status.charAt(0).toUpperCase() + o.status.slice(1);
    badge.className   = 'badge badge-' + o.status;

    document.getElementById('d-receipt-link').href = 'receipt.php?order_id=' + o.order_id;
    document.getElementById('d-status-order-id').value = o.order_id;
    document.getElementById('d-status-select').value = o.status;

    const tbl = document.getElementById('d-items');
    tbl.innerHTML = '<tr><th>Item</th><th>Qty</th><th>Price</th></tr>';
    if (!o.items || o.items.length === 0) {
        tbl.innerHTML += '<tr><td colspan="3" style="color:var(--muted);font-size:12.5px;">No items.</td></tr>';
    } else {
        o.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="item-name">${escHtml(item.name)}</td><td class="item-qty">&nbsp;× ${item.quantity}</td><td class="item-price">${formatPrice(item.subtotal)}</td>`;
            tbl.appendChild(tr);
            const optionsHtml = formatItemOptions(item.options || []);
            if (optionsHtml) {
                const optRow = document.createElement('tr');
                optRow.className = 'item-options-row';
                optRow.innerHTML = `<td colspan="3" class="item-options">${optionsHtml}</td>`;
                tbl.appendChild(optRow);
            }
        });
    }

    const bill = document.getElementById('d-bill');
    bill.innerHTML = `
        <div class="bill-row"><span>Order Type</span><span>${o.order_type ? o.order_type.charAt(0).toUpperCase() + o.order_type.slice(1) : '—'}</span></div>
        ${o.address ? `<div class="bill-row"><span>Address</span><span style="max-width:200px;text-align:right;font-size:12.5px;">${escHtml(o.address)}</span></div>` : ''}
        ${o.notes ? `<div class="bill-row"><span>Notes</span><span style="max-width:200px;text-align:right;font-size:12.5px;">${escHtml(o.notes)}</span></div>` : ''}
        <div class="bill-row total"><span>Total Bill</span><span>${formatPrice(o.total_amount)}</span></div>
    `;

    const payWrap = document.getElementById('d-payment-wrap');
    const payDiv  = document.getElementById('d-payment');
    if (o.payment_method) {
        payWrap.style.display = '';
        const cls   = o.payment_method === 'gcash' ? 'payment-gcash' : 'payment-cash';
        const label = o.payment_method.toUpperCase();
        payDiv.innerHTML = `<span class="payment-chip ${cls}">${label}</span>
            <div style="margin-top:8px;font-size:13.5px;color:#8a7060;">Amount Paid: <strong style="color:#210303;">${formatPrice(o.amount_paid)}</strong></div>`;
    } else {
        payWrap.style.display = 'none';
    }

    document.getElementById('detailEmpty').style.display = 'none';
    document.getElementById('detailContent').classList.add('visible');
}

const statusForm   = document.getElementById('d-status-form');
const statusSelect = document.getElementById('d-status-select');

statusSelect.addEventListener('change', () => statusForm.dispatchEvent(new Event('submit', { cancelable: true })));

statusForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (selectedOrderIndex === null) return;

    const formData = new FormData(event.currentTarget);
    formData.set('order_id', document.getElementById('d-status-order-id').value);

    const response = await fetch('orders.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });

    if (!response.ok) return;

    const newStatus = formData.get('status');
    ORDERS[selectedOrderIndex].status = newStatus;

    const badge = document.getElementById('d-badge');
    badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    badge.className = 'badge badge-' + newStatus;

    const selectedCard = document.querySelector(`.order-card[data-idx="${selectedOrderIndex}"]`);
    if (selectedCard) {
        const cardBadge = selectedCard.querySelector('.badge');
        if (cardBadge) {
            cardBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            cardBadge.className = 'badge badge-' + newStatus;
        }
    }
});

function toggleExpand(e, idx) {
    e.stopPropagation();
    const expanded = document.getElementById('expanded-' + idx);
    const icon     = document.getElementById('toggle-icon-' + idx);
    const isOpen   = expanded.classList.contains('open');
    expanded.classList.toggle('open', !isOpen);
    icon.textContent = isOpen ? '▼' : '▲';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>
