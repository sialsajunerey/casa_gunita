<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

// ── Stats ──────────────────────────────────────────────────────────────────
$pending = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'"))['total'];

$total_orders = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders"))['total'];

$total_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(amount_paid), 0) AS total FROM transactions"))['total'];

// ── Search ─────────────────────────────────────────────────────────────────
$search_id    = isset($_GET['search']) ? (int)$_GET['search'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
if ($search_id)     $where[] = "o.order_id = $search_id";
if ($status_filter) $where[] = "o.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Orders list ────────────────────────────────────────────────────────────
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

// ── Pre-load items + transactions for each order ───────────────────────────
$order_ids = array_column($orders, 'order_id');
$items_map  = [];
$trans_map  = [];

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
        if (!empty($item['options'])) {
            $item['options'] = json_decode($item['options'], true) ?: [];
        } else {
            $item['options'] = [];
        }
        $items_map[$item['order_id']][] = $item;
    }

    $trans_res = mysqli_query($conn,
        "SELECT * FROM transactions WHERE order_id IN ($ids_sql)");
    while ($t = mysqli_fetch_assoc($trans_res)) {
        $trans_map[$t['order_id']] = $t;
    }
}

// Build full data for JS
$orders_js = [];
foreach ($orders as $o) {
    $oid   = $o['order_id'];
    $items = $items_map[$oid] ?? [];
    $trans = $trans_map[$oid]  ?? null;

    // one-line summary
    $summary_parts = array_map(fn($i) => $i['quantity'] . 'x ' . $i['name'], $items);
    $summary       = implode(', ', $summary_parts) ?: '—';

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
<style>
/* ── Reset & tokens ────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --crimson:   #210303;
    --crimson-d: #130301;
    --primary-dark: var(--crimson-d);
    --crimson-l: #f7e3c6;
    --gold:      #e8d191;
    --ink:       #130301;
    --muted:     #674328;
    --line:      rgba(33,3,3,.1);
    --surface:   #fff8eb;
    --bg:        #f4f2ea;
    --sidebar-w: 220px;
    --header-h:  64px;
    --radius:    14px;
    --shadow:    0 2px 18px rgba(33,3,3,.08);
    --shadow-lg: 0 8px 32px rgba(33,3,3,.12);
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
}

/* ── Sidebar ───────────────────────────────────────────────── */
.sidebar {
    width: var(--sidebar-w);
    background: var(--crimson);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 100;
}

.sidebar-logo {
    padding: 22px 20px 18px;
    border-bottom: 1px solid rgba(255,255,255,.12);
}
.sidebar-logo .brand {
    font-family: 'Cinzel Decorative', serif;
    font-size: 17px;
    color: #fff;
    line-height: 1.2;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.sidebar-logo .sub {
    font-size: 11px;
    color: rgba(255,255,255,.55);
    margin-top: 2px;
    letter-spacing: .5px;
}

.nav-list {
    list-style: none;
    padding: 16px 12px;
    flex: 1;
}
.nav-list li { margin-bottom: 4px; }
.nav-list a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.75);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background .15s, color .15s;
}
.nav-list a:hover,
.nav-list a.active {
    background: rgba(255,255,255,.14);
    color: #fff;
}
.nav-list a .icon { font-size: 16px; width: 20px; text-align: center; }

.sidebar-footer {
    padding: 16px 12px;
    border-top: 1px solid rgba(255,255,255,.12);
}
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.65);
    text-decoration: none;
    font-size: 14px;
    transition: background .15s, color .15s;
}
.sidebar-footer a:hover { background: rgba(255,255,255,.1); color: #fff; }

/* ── Main ──────────────────────────────────────────────────── */
.main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── Topbar ────────────────────────────────────────────────── */
.topbar {
    height: var(--header-h);
    background: var(--surface);
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-title {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: var(--crimson);
    white-space: nowrap;
}

.search-wrap {
    flex: 1;
    max-width: 360px;
    margin-left: 24px;
}
.search-wrap form {
    display: flex;
    align-items: center;
    background: var(--bg);
    border: 1.5px solid var(--line);
    border-radius: 30px;
    padding: 0 14px;
    gap: 8px;
    transition: border-color .2s;
}
.search-wrap form:focus-within { border-color: var(--crimson); }
.search-wrap .search-icon { color: var(--muted); font-size: 14px; }
.search-wrap input {
    border: none;
    background: transparent;
    outline: none;
    font-family: inherit;
    font-size: 13.5px;
    padding: 9px 0;
    width: 100%;
    color: var(--ink);
}
.search-wrap button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--crimson);
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 20px;
    transition: background .15s;
}
.search-wrap button:hover { background: var(--crimson-l); }

.topbar-spacer { flex: 1; }

.topbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--ink);
}
.avatar {
    width: 34px; height: 34px;
    background: var(--crimson);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}

/* ── Content ───────────────────────────────────────────────── */
.content {
    padding: 24px 28px;
    display: flex;
    flex-direction: column;
    gap: 22px;
    flex: 1;
}

/* ── Stat cards ────────────────────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 20px 22px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 16px;
    border-left: 4px solid transparent;
    transition: transform .15s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card.pending  { border-color: #f59e0b; }
.stat-card.total    { border-color: var(--crimson); }
.stat-card.revenue  { border-color: #10b981; }

.stat-icon {
    width: 46px; height: 46px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.stat-card.pending  .stat-icon { background: #fef3c7; }
.stat-card.total    .stat-icon { background: var(--crimson-l); }
.stat-card.revenue  .stat-icon { background: #d1fae5; }

.stat-info .num {
    font-size: 28px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1;
}
.stat-info .lbl {
    font-size: 12.5px;
    color: var(--muted);
    margin-top: 3px;
}

/* ── Orders area ───────────────────────────────────────────── */
.orders-area {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 20px;
    align-items: start;
}

/* ── Orders panel (left) ───────────────────────────────────── */
.orders-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - var(--header-h) - 160px);
    overflow: hidden;
}

.panel-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-shrink: 0;
}
.panel-header h3 {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
}

.filter-tabs {
    display: flex;
    gap: 6px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--line);
    flex-shrink: 0;
    overflow-x: auto;
}
.filter-tab {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--line);
    background: transparent;
    color: var(--muted);
    transition: all .15s;
    white-space: nowrap;
    text-decoration: none;
}
.filter-tab:hover,
.filter-tab.active {
    background: var(--crimson);
    border-color: var(--crimson);
    color: #fff;
}

.orders-list {
    overflow-y: auto;
    flex: 1;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ── Order card ────────────────────────────────────────────── */
.order-card {
    border: 1.5px solid var(--line);
    border-radius: 10px;
    padding: 13px 14px;
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, background .15s;
    background: #fff;
}
.order-card:hover { border-color: #c0c0d0; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.order-card.selected {
    border-color: var(--crimson);
    background: var(--crimson-l);
    box-shadow: 0 2px 8px rgba(139,0,0,.12);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 4px;
}
.card-oid {
    font-size: 12px;
    color: var(--muted);
    font-weight: 500;
}
.card-name {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--ink);
    margin: 2px 0 1px;
}
.card-date {
    font-size: 11.5px;
    color: var(--muted);
    margin-bottom: 7px;
}

.order-summary-line {
    font-size: 12.5px;
    color: #555;
    background: var(--bg);
    border-radius: 6px;
    padding: 6px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: background .15s;
}
.order-summary-line:hover { background: #ebebf0; }
.order-summary-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}
.toggle-icon { font-size: 11px; color: var(--muted); flex-shrink: 0; }

.order-items-expanded {
    display: none;
    margin-top: 8px;
    padding: 8px 10px;
    background: #f8f8fb;
    border-radius: 8px;
    font-size: 12.5px;
    color: var(--ink);
}
.order-items-expanded.open { display: block; }
.expanded-item {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
    border-bottom: 1px dashed var(--line);
}
.expanded-item:last-child { border: none; }

/* ── Status badge ──────────────────────────────────────────── */
.badge {
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
}
.badge-pending   { background: #fef3c7; color: #92400e; }
.badge-preparing { background: #dbeafe; color: #1e40af; }
.badge-ready     { background: #d1fae5; color: #065f46; }
.badge-completed { background: #dcfce7; color: #14532d; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

/* ── Detail panel (right) ──────────────────────────────────── */
.detail-panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 22px 24px;
    max-height: calc(100vh - var(--header-h) - 160px);
    overflow-y: auto;
    position: sticky;
    top: calc(var(--header-h) + 24px);
}

.detail-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--muted);
    text-align: center;
    gap: 12px;
}
.detail-empty .icon { font-size: 40px; opacity: .35; }
.detail-empty p { font-size: 13.5px; }

.detail-content { display: none; }
.detail-content.visible { display: block; }

.detail-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    gap: 10px;
}
.detail-oid { font-size: 12.5px; color: var(--muted); }
.detail-name {
    font-size: 20px;
    font-weight: 700;
    margin: 2px 0 3px;
    color: var(--ink);
}
.detail-meta { font-size: 12.5px; color: var(--muted); }

.detail-actions { display: flex; gap: 8px; align-items: center; }
.detail-actions .badge {
    background: rgba(33, 3, 3, 0.08);
    color: var(--crimson);
    font-size: 12px;
    padding: 8px 12px;
    border-radius: 999px;
    font-weight: 700;
}
.btn-receipt {
    background: var(--crimson);
    color: #fff;
    border: none;
    border-radius: 7px;
    padding: 7px 14px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background .15s;
}
.btn-receipt:hover { background: var(--crimson-d); }

.section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin: 18px 0 10px;
}

.items-table { width: 100%; border-collapse: collapse; }
.items-table th, .items-table td { padding: 7px 0; font-size: 13.5px; border-bottom: 1px solid var(--line); }
.items-table th { text-align: left; color: var(--gold); background: var(--crimson-d); padding: 10px 0; }
.items-table tr:last-child td { border: none; }
.items-table .item-name { color: var(--ink); font-weight: 500; }
.items-table .item-qty  { color: var(--muted); font-size: 12px; }
.items-table .item-price { text-align: right; font-weight: 600; color: var(--ink); }
.items-table .item-options-row td { padding: 4px 0 10px; }
.items-table .item-options { color: var(--muted); font-size: 12px; line-height: 1.4; padding-left: 16px; }
.status-form { margin-bottom: 18px; padding: 16px; background: #fff; border: 1px solid var(--line); border-radius: 14px; }
.status-row { display: grid; gap: 10px; margin-bottom: 12px; }
.status-row label { font-size: 13px; color: var(--ink); font-weight: 600; }
.status-row select { width: 100%; padding: 10px 12px; border: 1px solid #d6d2d9; border-radius: 12px; background: #fff; }
.status-form .btn-primary { width: 100%; }

.bill-row {
    display: flex;
    justify-content: space-between;
    font-size: 13.5px;
    padding: 5px 0;
    color: var(--ink);
}
.bill-row.total {
    font-weight: 700;
    font-size: 15px;
    border-top: 2px solid var(--crimson);
    margin-top: 6px;
    padding-top: 10px;
    color: var(--crimson);
}

.payment-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 700;
    margin-top: 8px;
}
.payment-cash  { background: #d4edda; color: #155724; }
.payment-gcash { background: #cce5ff; color: #004085; }

/* ── Scrollbar ─────────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #d4d4dc; border-radius: 10px; }
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>

    <ul class="nav-list">
        <li>
            <a href="index.php" class="active">
                <span class="icon">🏠</span> Dashboard
            </a>
        </li>
        <li>
            <a href="orders.php">
                <span class="icon">📋</span> Orders
            </a>
        </li>
        <li>
            <a href="products.php">
                <span class="icon">🍖</span> Products
            </a>
        </li>
        <li>
            <a href="inventory.php">
                <span class="icon">📦</span> Inventory
            </a>
        </li>
        <li>
            <a href="transactions.php">
                <span class="icon">💰</span> Transactions
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php">
            <span class="icon">🚪</span> Logout
        </a>
    </div>
</aside>

<!-- ── Main ── -->
<div class="main">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-title">Dashboard</div>

        <div class="search-wrap">
            <form method="GET" action="">
                <span class="search-icon">🔍</span>
                <input
                    type="number"
                    name="search"
                    placeholder="Search Order ID…"
                    value="<?= htmlspecialchars($search_id ?: '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Go</button>
                <?php if ($search_id || $status_filter): ?>
                    <a href="index.php" style="font-size:12px;color:var(--muted);text-decoration:none;padding:4px 6px;">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="topbar-spacer"></div>

        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <!-- Content -->
    <div class="content">

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <div class="num"><?= $pending ?></div>
                    <div class="lbl">Pending Orders</div>
                </div>
            </div>
            <div class="stat-card total">
                <div class="stat-icon">🧾</div>
                <div class="stat-info">
                    <div class="num"><?= $total_orders ?></div>
                    <div class="lbl">Total Orders</div>
                </div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-icon">💵</div>
                <div class="stat-info">
                    <div class="num"><?= formatPrice($total_revenue) ?></div>
                    <div class="lbl">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Orders area -->
        <div class="orders-area">

            <!-- Left: orders list -->
            <div class="orders-panel">
                <div class="panel-header">
                    <h3>All Orders <span style="color:var(--muted);font-weight:400;font-size:13px;">(<?= count($orders) ?>)</span></h3>
                </div>

                <!-- Status filter tabs -->
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
                        if ($search_id) $href .= ($val ? "&search=$search_id" : "?search=$search_id");
                    ?>
                    <a href="<?= $href ?>" class="filter-tab <?= $active ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="orders-list" id="ordersList">
                    <?php if (empty($orders)): ?>
                        <p style="text-align:center;color:var(--muted);padding:30px;font-size:13.5px;">No orders found.</p>
                    <?php else: ?>
                        <?php foreach ($orders as $idx => $o): ?>
                        <?php
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

            <!-- Right: detail panel -->
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
                            <a id="d-receipt-link" href="#" class="btn-receipt" target="_blank">🖨️ Receipt</a>
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

        </div><!-- /orders-area -->
    </div><!-- /content -->
</div><!-- /main -->

<!-- ── JS: order data + interactions ── -->
<script>
const ORDERS = <?= json_encode($orders_js, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function formatPrice(n) {
    return '₱' + parseFloat(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(s) {
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
}

function formatItemOptions(options) {
    if (!options || !options.length) {
        return '';
    }
    const groups = {};
    options.forEach(opt => {
        const group = opt.group_name || (opt.group_type === 'addon' ? 'Add-ons' : opt.group_type === 'size' ? 'Size' : 'Flavor');
        groups[group] = groups[group] || [];
        groups[group].push(opt);
    });
    return Object.entries(groups).map(([group, opts]) => {
        const items = opts.map(item => `${escHtml(item.name)}${item.additional_price ? ` (+${formatPrice(item.additional_price)})` : ''}`).join(', ');
        return `<div class="item-options"><strong>${escHtml(group)}:</strong> ${items}</div>`;
    }).join('');
}

let selectedOrderIndex = null;

function selectOrder(idx) {
    // Highlight card
    document.querySelectorAll('.order-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.order-card[data-idx="${idx}"]`);
    if (card) card.classList.add('selected');
    selectedOrderIndex = idx;

    const o = ORDERS[idx];
    if (!o) return;

    // Order ID & name
    document.getElementById('d-oid').textContent  = 'Order ID #' + String(o.order_id).padStart(5, '0');
    document.getElementById('d-name').textContent = o.full_name;
    document.getElementById('d-meta').textContent = formatDate(o.created_at) + ' · ' + o.email;

    // Badge
    const badge = document.getElementById('d-badge');
    badge.textContent = o.status.charAt(0).toUpperCase() + o.status.slice(1);
    badge.className   = 'badge badge-' + o.status;

    // Receipt link
    document.getElementById('d-receipt-link').href = 'receipt.php?order_id=' + o.order_id;
    document.getElementById('d-status-order-id').value = o.order_id;
    document.getElementById('d-status-select').value = o.status;

    const selectedCard = document.querySelector(`.order-card[data-idx="${idx}"]`);
    if (selectedCard) {
        const cardBadge = selectedCard.querySelector('.badge');
        if (cardBadge) {
            cardBadge.textContent = o.status.charAt(0).toUpperCase() + o.status.slice(1);
            cardBadge.className = 'badge badge-' + o.status;
        }
    }

    // Items table
    const tbl = document.getElementById('d-items');
    tbl.innerHTML = '<tr><th>Item</th><th>Qty</th><th>Price</th></tr>';
    if (!o.items || o.items.length === 0) {
        tbl.innerHTML += '<tr><td colspan="3" style="color:var(--muted);font-size:12.5px;">No items.</td></tr>';
    } else {
        o.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="item-name">${escHtml(item.name)}</td>
                <td class="item-qty">&nbsp;× ${item.quantity}</td>
                <td class="item-price">${formatPrice(item.subtotal)}</td>`;
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

    // Bill
    const bill = document.getElementById('d-bill');
    bill.innerHTML = `
        <div class="bill-row"><span>Order Type</span><span>${o.order_type ? o.order_type.charAt(0).toUpperCase() + o.order_type.slice(1) : '—'}</span></div>
        ${o.notes ? `<div class="bill-row"><span>Notes</span><span style="max-width:200px;text-align:right;font-size:12.5px;">${escHtml(o.notes)}</span></div>` : ''}
        <div class="bill-row total"><span>Total Bill</span><span>${formatPrice(o.total_amount)}</span></div>
    `;

    // Payment
    const payWrap = document.getElementById('d-payment-wrap');
    const payDiv  = document.getElementById('d-payment');
    if (o.payment_method) {
        payWrap.style.display = '';
        const cls  = o.payment_method === 'gcash' ? 'payment-gcash' : 'payment-cash';
        const label = o.payment_method.toUpperCase();
        payDiv.innerHTML = `<span class="payment-chip ${cls}">${label}</span>
            <div style="margin-top:8px;font-size:13.5px;color:var(--muted);">Amount Paid: <strong style="color:var(--ink);">${formatPrice(o.amount_paid)}</strong></div>`;
    } else {
        payWrap.style.display = 'none';
    }

    // Show content
    document.getElementById('detailEmpty').style.display   = 'none';
    document.getElementById('detailContent').classList.add('visible');
}

const statusForm = document.getElementById('d-status-form');
const statusSelect = document.getElementById('d-status-select');

statusSelect.addEventListener('change', () => statusForm.dispatchEvent(new Event('submit', { cancelable: true })));

statusForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (selectedOrderIndex === null) return;

    const form = event.currentTarget;
    const formData = new FormData(form);
    formData.set('order_id', document.getElementById('d-status-order-id').value);

    const response = await fetch('orders.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    });

    if (!response.ok) {
        return;
    }

    const current = ORDERS[selectedOrderIndex];
    const newStatus = formData.get('status');
    current.status = newStatus;

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
    const expanded  = document.getElementById('expanded-' + idx);
    const icon      = document.getElementById('toggle-icon-' + idx);
    const isOpen    = expanded.classList.contains('open');
    expanded.classList.toggle('open', !isOpen);
    icon.textContent = isOpen ? '▼' : '▲';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>