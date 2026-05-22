<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

/* ── TODO: fetch real KPI values ── */
// $kpi_orders  = /* SELECT COUNT(*) FROM orders WHERE ... */;
// $kpi_revenue = /* SELECT SUM(total) FROM orders WHERE status != 'cancelled' AND ... */;
// $kpi_peak    = /* SELECT HOUR(created_at) ... GROUP BY HOUR ORDER BY COUNT DESC LIMIT 1 */;
// $kpi_top     = /* SELECT menu_item_name ... GROUP BY ... ORDER BY COUNT DESC LIMIT 1 */;

/* ── TODO: fetch heatmap data (7 rows × 24 cols) ── */
// $heatmap_json = /* SELECT DAY_OF_WEEK, HOUR, COUNT(*) FROM orders GROUP BY ... */;

/* ── TODO: fetch pie chart data per group ── */
// $pie_status_json   = /* SELECT status, COUNT(*) FROM orders GROUP BY status */;
// $pie_category_json = /* SELECT category_name, COUNT(*) FROM order_items JOIN menu_items ... GROUP BY category */;
// $pie_time_json     = /* SELECT HOUR buckets, COUNT(*) FROM orders GROUP BY time_slot */;

/* ── TODO: fetch top performing line chart (last 7 days, top 3 per tab) ── */
// $top_items_json    = /* top 3 menu items × last 7 days */;
// $top_category_json = /* top 3 categories × last 7 days */;
// $top_area_json     = /* top 3 barangays × last 7 days */;

/* ── TODO: fetch ranked list per tab ── */
// $ranked_items_json    = /* all menu items with order count */;
// $ranked_category_json = /* all categories with order count */;
// $ranked_area_json     = /* all barangays with order count */;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Analytics — Casa Gunita Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="analytics.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="customizations.php">Customizations</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit Log</a></li>
        <li><a href="analytics.php" class="active">Analytics</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main" id="main">

    <header class="topbar">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-title">Analytics</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content" id="analyticsContent">

        <!-- ── Filter Bar ── -->
        <div class="filter-bar">
            <span class="filter-bar-label">Period</span>

            <div class="preset-btns">
                <button class="preset-btn active" data-preset="today">Today</button>
                <button class="preset-btn" data-preset="yesterday">Yesterday</button>
                <button class="preset-btn" data-preset="7d">Last 7 Days</button>
                <button class="preset-btn" data-preset="30d">Last 30 Days</button>
                <button class="preset-btn" data-preset="thismonth">This Month</button>
                <button class="preset-btn" data-preset="custom">Custom</button>
            </div>

            <div class="filter-divider"></div>

            <div class="date-range">
                <span class="filter-bar-label" style="margin:0">From</span>
                <input class="date-input" type="date" id="dateFrom"
                       value="<?= date('Y-m-d') ?>">
                <span class="date-range-sep">—</span>
                <span class="filter-bar-label" style="margin:0">To</span>
                <input class="date-input" type="date" id="dateTo"
                       value="<?= date('Y-m-d') ?>">
            </div>

            <div class="filter-bar-spacer"></div>

            <button class="pdf-btn" id="downloadPdfBtn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download PDF
            </button>
        </div>

        <!-- ── KPI Cards ── -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-value" id="kpiOrders">—</div>
                <div class="kpi-sub">This period</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value" id="kpiRevenue">—</div>
                <div class="kpi-sub">Excl. cancelled</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Peak Hour</div>
                <div class="kpi-value" id="kpiPeak">—</div>
                <div class="kpi-sub">Most orders placed</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Top Item</div>
                <div class="kpi-value" style="font-size:1rem;padding-top:4px" id="kpiTop">—</div>
                <div class="kpi-sub" id="kpiTopSub">Most ordered</div>
            </div>
        </div>

        <!-- ── Active Hours Heatmap ── -->
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Active Hours</div>
                    <div class="chart-sub">Order volume by hour and day — darker = more orders</div>
                </div>
            </div>
            <div class="heatmap-wrap" id="heatmapWrap"></div>
            <div class="heatmap-legend">
                <span class="heatmap-legend-label">Low</span>
                <div class="heatmap-legend-swatch" style="background:#f5ede0"></div>
                <div class="heatmap-legend-swatch" style="background:#e8c99a"></div>
                <div class="heatmap-legend-swatch" style="background:#d4a55a"></div>
                <div class="heatmap-legend-swatch" style="background:#b87820"></div>
                <div class="heatmap-legend-swatch" style="background:#210303"></div>
                <span class="heatmap-legend-label">High</span>
            </div>
        </div>

        <!-- ── Two Column: Order Trends Pie + Top Performing Line ── -->
        <div class="charts-2col">

            <!-- Order Trends — Doughnut -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">Order Trends</div>
                        <div class="chart-sub">Distribution for the selected period</div>
                    </div>
                    <div class="chart-controls">
                        <select class="ctrl-select" id="pieGroupBy">
                            <option value="status">By Status</option>
                            <option value="category">By Category</option>
                            <option value="time">By Time Slot</option>
                        </select>
                    </div>
                </div>
                <div class="pie-wrap">
                    <div class="pie-canvas-wrap">
                        <canvas id="pieChart"></canvas>
                    </div>
                    <div class="pie-legend" id="pieLegend"></div>
                </div>
            </div>

            <!-- Top Performing — Line Chart (has its own independent tab bar + filter) -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">Top Performing</div>
                        <div class="chart-sub">Sales trend — last 7 days</div>
                    </div>
                    <div class="chart-controls">
                        <div class="tab-bar" id="topTabBar" style="margin-bottom:0">
                            <button class="tab-btn active" data-tab="item">Menu Item</button>
                            <button class="tab-btn" data-tab="category">Category</button>
                            <button class="tab-btn" data-tab="area">Area</button>
                        </div>
                        <select class="ctrl-select" id="topFilterSelect" style="display:none"></select>
                    </div>
                </div>
                <div class="chart-canvas-wrap" style="height:180px">
                    <canvas id="topLineChart"></canvas>
                </div>
                <div class="chart-legend" id="topLineLegend"></div>
                <div class="pagination-bar">
                    <button class="page-btn" id="topPrev" disabled>← Prev</button>
                    <span class="page-info" id="topPageInfo">Page 1 / 1</span>
                    <button class="page-btn" id="topNext">Next →</button>
                </div>
            </div>

        </div>

        <!-- ── Ranked Breakdown (full width, independent tabs + filter) ── -->
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <div class="chart-title">Ranked Breakdown</div>
                    <div class="chart-sub" id="rankedSub">Menu items by order count</div>
                </div>
                <div class="chart-controls">
                    <div class="tab-bar" id="rankedTabBar" style="margin-bottom:0">
                        <button class="tab-btn active" data-tab="item">Menu Item</button>
                        <button class="tab-btn" data-tab="category">Category</button>
                        <button class="tab-btn" data-tab="area">Area</button>
                    </div>
                    <select class="ctrl-select" id="rankedFilterSelect" style="display:none"></select>
                    <button class="sort-btn" id="rankedSortBtn">↓ Highest First</button>
                </div>
            </div>

            <div class="top-list" id="topList"></div>
            <div class="pagination-bar">
                <button class="page-btn" id="rankedPrev" disabled>← Prev</button>
                <span class="page-info" id="rankedPageInfo">Page 1 / 1</span>
                <button class="page-btn" id="rankedNext">Next →</button>
            </div>
        </div>

    </div><!-- .content -->
</div><!-- .main -->

<script src="analytics.js"></script>
</body>
</html>