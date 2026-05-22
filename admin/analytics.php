<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/analytics.php';
requireAdmin();

// Default period: Today
$preset = $_GET['preset'] ?? 'today';
$custom_from = $_GET['from'] ?? null;
$custom_to = $_GET['to'] ?? null;
$period = parsePeriodPreset($preset, $custom_from, $custom_to);
$date_from = $period['from'];
$date_to = $period['to'];

// Fetch KPI data
$kpi = getAnalyticsKPI($pdo, $date_from, $date_to);
$kpi_orders = (int)$kpi['total_orders'];
$kpi_revenue = formatCurrency($kpi['total_revenue']);
$kpi_peak = htmlspecialchars($kpi['peak_hour']);
$kpi_top = htmlspecialchars($kpi['top_item']);

// Fetch heatmap data
$heatmap_data = getAnalyticsHeatmap($pdo, $date_from, $date_to);
$heatmap_json = json_encode($heatmap_data);

// Fetch pie chart data (default: by status)
$pie_status_data = getAnalyticsPieData($pdo, $date_from, $date_to, 'status');
$pie_category_data = getAnalyticsPieData($pdo, $date_from, $date_to, 'category');
$pie_time_data = getAnalyticsPieData($pdo, $date_from, $date_to, 'time');
$pie_ordertype_data = getAnalyticsPieData($pdo, $date_from, $date_to, 'ordertype');

// Fetch top performing data (last 7 days)
$top_items_data = getAnalyticsTopPerforming($pdo, 'item', 3, $date_from, $date_to);
$top_category_data = getAnalyticsTopPerforming($pdo, 'category', 3, $date_from, $date_to);
$top_area_data = getAnalyticsTopPerforming($pdo, 'area', 3, $date_from, $date_to);

// Fetch ranked data (first page)
$ranked_items = getAnalyticsRankedItems($pdo, $date_from, $date_to, 'item', 1, 10);
$ranked_categories = getAnalyticsRankedItems($pdo, $date_from, $date_to, 'category', 1, 10);
$ranked_areas = getAnalyticsRankedItems($pdo, $date_from, $date_to, 'area', 1, 10);

// Convert data to JSON for JavaScript
$pie_status_json = json_encode($pie_status_data);
$pie_category_json = json_encode($pie_category_data);
$pie_time_json = json_encode($pie_time_data);
$pie_ordertype_json = json_encode($pie_ordertype_data);

$top_items_json = json_encode($top_items_data);
$top_category_json = json_encode($top_category_data);
$top_area_json = json_encode($top_area_data);

$ranked_items_json = json_encode($ranked_items['items']);
$ranked_category_json = json_encode($ranked_categories['items']);
$ranked_area_json = json_encode($ranked_areas['items']);
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
<style>
    /* Adjusting the 2-column layout to give Order Trends more space for better alignment */
    @media (min-width: 1025px) {
        .charts-2col {
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
            gap: 20px;
        }
    }
</style>
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
                       value="<?= $date_from ?>">
                <span class="date-range-sep">—</span>
                <span class="filter-bar-label" style="margin:0">To</span>
                <input class="date-input" type="date" id="dateTo"
                       value="<?= $date_to ?>">
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
                <div class="kpi-value" id="kpiOrders"><?= $kpi_orders ?></div>
                <div class="kpi-sub">This period</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value" id="kpiRevenue"><?= $kpi_revenue ?></div>
                <div class="kpi-sub">Excl. cancelled</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Peak Hour</div>
                <div class="kpi-value" id="kpiPeak"><?= $kpi_peak ?></div>
                <div class="kpi-sub">Most orders placed</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Top Item</div>
                <div class="kpi-value" id="kpiTop" style="font-size:1rem;padding-top:4px"><?= $kpi_top ?></div>
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
                            <option value="ordertype">By Order Type</option>
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

<script>
  // Initialize analytics data from PHP
  let analyticsData = {
    heatmap: <?= $heatmap_json ?>,
    pie: {
      status: <?= $pie_status_json ?>,
      category: <?= $pie_category_json ?>,
      time: <?= $pie_time_json ?>,
      ordertype: <?= $pie_ordertype_json ?>
    },
    topPerforming: {
      item: <?= $top_items_json ?>,
      category: <?= $top_category_json ?>,
      area: <?= $top_area_json ?>
    },
    ranked: {
      item: <?= $ranked_items_json ?>,
      category: <?= $ranked_category_json ?>,
      area: <?= $ranked_area_json ?>
    },
    pagination: {
      items: { total: <?= $ranked_items['pagination']['total_pages'] ?> },
      category: { total: <?= $ranked_categories['pagination']['total_pages'] ?> },
      area: { total: <?= $ranked_areas['pagination']['total_pages'] ?> }
    },
    dateRange: {
      from: '<?= $date_from ?>',
      to: '<?= $date_to ?>',
      preset: '<?= $preset ?>'
    }
  };
</script>

<script src="analytics.js"></script>
</body>
</html>