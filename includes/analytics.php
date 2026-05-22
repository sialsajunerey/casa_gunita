<?php
/**
 * Analytics Functions
 * Handles all analytics data fetching from database
 */

/**
 * Get KPI metrics for a date range
 * @param PDO $pdo Database connection
 * @param string $date_from YYYY-MM-DD
 * @param string $date_to YYYY-MM-DD
 * @return array KPI values
 */
function getAnalyticsKPI($pdo, $date_from, $date_to) {
  try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_kpi(?, ?)");
    $stmt->execute([$date_from, $date_to]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $result ?: [];
  } catch (Exception $e) {
    error_log("Error in getAnalyticsKPI: " . $e->getMessage());
    return [
      'total_orders' => 0,
      'total_revenue' => 0,
      'peak_hour' => '—',
      'top_item' => '—',
      'top_item_count' => 0
    ];
  }
}

/**
 * Get heatmap data (7 days × 24 hours grid)
 * @param PDO $pdo Database connection
 * @param string $date_from YYYY-MM-DD
 * @param string $date_to YYYY-MM-DD
 * @return array Heatmap grid data
 */
function getAnalyticsHeatmap($pdo, $date_from, $date_to) {
  try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_heatmap(?, ?)");
    $stmt->execute([$date_from, $date_to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Build 7×24 grid (Sunday-Saturday × 0-23 hours)
    $grid = array_fill(0, 7, array_fill(0, 24, 0));
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    foreach ($rows as $row) {
      $day_index = $row['day_num'] - 1; // DAYOFWEEK returns 1-7, we want 0-6
      $hour = (int)$row['hour'];
      $grid[$day_index][$hour] = (int)$row['order_count'];
    }

    return [
      'grid' => $grid,
      'days' => $dayNames
    ];
  } catch (Exception $e) {
    error_log("Error in getAnalyticsHeatmap: " . $e->getMessage());
    return [
      'grid' => array_fill(0, 7, array_fill(0, 24, 0)),
      'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
    ];
  }
}

/**
 * Get pie chart data grouped by specified field
 * @param PDO $pdo Database connection
 * @param string $date_from YYYY-MM-DD
 * @param string $date_to YYYY-MM-DD
 * @param string $group_by 'status', 'category', 'time', 'ordertype'
 * @return array Pie chart data
 */
function getAnalyticsPieData($pdo, $date_from, $date_to, $group_by = 'status') {
  try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_pie_data(?, ?, ?)");
    $stmt->execute([$date_from, $date_to, $group_by]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $rows ?: [];
  } catch (Exception $e) {
    error_log("Error in getAnalyticsPieData: " . $e->getMessage());
    return [];
  }
}

/**
 * Get top performing items/categories/areas (last 7 days)
 * @param PDO $pdo Database connection
 * @param string $type 'item', 'category', 'area'
 * @param int $limit Number of top items per day (default 3)
 * @param string $date_from YYYY-MM-DD
 * @param string $date_to YYYY-MM-DD
 * @return array Top performing data with dates
 */
function getAnalyticsTopPerforming($pdo, $type = 'item', $limit = 3, $date_from = null, $date_to = null) {
  try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_top_performing(?, ?, ?, ?)");
    $stmt->execute([$date_from, $date_to, $type, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $rows ?: [];
  } catch (Exception $e) {
    error_log("Error in getAnalyticsTopPerforming: " . $e->getMessage());
    return [];
  }
}

/**
 * Aggregate all analytics data for AJAX response
 */
function get_analytics_data($date_from, $date_to) {
    global $pdo;
    
    $kpi = getAnalyticsKPI($pdo, $date_from, $date_to);
    $heatmap = getAnalyticsHeatmap($pdo, $date_from, $date_to);
    
    return [
        'kpis' => [
            'total_orders' => $kpi['total_orders'] ?? 0,
            'total_revenue' => $kpi['total_revenue'] ?? 0,
            'peak_hour' => $kpi['peak_hour'] ?? '—',
            'top_item' => $kpi['top_item'] ?? '—',
            'top_item_count' => $kpi['top_item_count'] ?? 0
        ],
        'heatmap' => $heatmap,
        'pie' => [
            'status' => getAnalyticsPieData($pdo, $date_from, $date_to, 'status'),
            'category' => getAnalyticsPieData($pdo, $date_from, $date_to, 'category'),
            'time' => getAnalyticsPieData($pdo, $date_from, $date_to, 'time'),
            'ordertype' => getAnalyticsPieData($pdo, $date_from, $date_to, 'ordertype')
        ],
        'topPerforming' => [
            'item' => getAnalyticsTopPerforming($pdo, 'item', 3, $date_from, $date_to),
            'category' => getAnalyticsTopPerforming($pdo, 'category', 3, $date_from, $date_to),
            'area' => getAnalyticsTopPerforming($pdo, 'area', 3, $date_from, $date_to)
        ],
        'ranked' => [
            'item' => getAnalyticsRankedItems($pdo, $date_from, $date_to, 'item', 1, 50)['items'],
            'category' => getAnalyticsRankedItems($pdo, $date_from, $date_to, 'category', 1, 50)['items'],
            'area' => getAnalyticsRankedItems($pdo, $date_from, $date_to, 'area', 1, 50)['items']
        ],
        'dateRange' => [
            'from' => $date_from,
            'to' => $date_to
        ]
    ];
}

/**
 * Get ranked list of items/categories/areas with pagination
 * @param PDO $pdo Database connection
 * @param string $date_from YYYY-MM-DD
 * @param string $date_to YYYY-MM-DD
 * @param string $type 'item', 'category', 'area'
 * @param int $page Page number (starting from 1)
 * @param int $per_page Items per page (default 10)
 * @return array Ranked items with pagination info
 */
function getAnalyticsRankedItems($pdo, $date_from, $date_to, $type = 'item', $page = 1, $per_page = 10) {
  try {
    $offset = ($page - 1) * $per_page;
    
    $stmt = $pdo->prepare("CALL sp_analytics_get_ranked_items(?, ?, ?, ?, ?)");
    $stmt->execute([$date_from, $date_to, $type, $per_page, $offset]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Optimized count fetching
    $count_stmt = $pdo->prepare("CALL sp_analytics_get_total_count(?, ?, ?, @total)");
    $count_stmt->execute([$date_from, $date_to, $type]);
    $count_stmt->closeCursor();

    $total_res = $pdo->query("SELECT @total as total")->fetch();
    $total_count = (int)($total_res['total'] ?? count($items));

    $total_pages = max(1, ceil($total_count / $per_page));

    return [
      'items' => $items,
      'pagination' => [
        'page' => $page,
        'per_page' => $per_page,
        'total_count' => $total_count,
        'total_pages' => $total_pages
      ]
    ];
  } catch (Exception $e) {
    error_log("Error in getAnalyticsRankedItems: " . $e->getMessage());
    return [
      'items' => [],
      'pagination' => ['page' => 1, 'per_page' => $per_page, 'total_count' => 0, 'total_pages' => 1]
    ];
  }
}

/**
 * Parse preset period filter
 * @param string $preset 'today', 'yesterday', '7d', '30d', 'thismonth', 'custom'
 * @param string $custom_from Optional custom from date (YYYY-MM-DD)
 * @param string $custom_to Optional custom to date (YYYY-MM-DD)
 * @return array ['from' => YYYY-MM-DD, 'to' => YYYY-MM-DD]
 */
function parsePeriodPreset($preset, $custom_from = null, $custom_to = null) {
  $today = date('Y-m-d');
  
  switch ($preset) {
    case 'today':
      return ['from' => $today, 'to' => $today];
    case 'yesterday':
      $yesterday = date('Y-m-d', strtotime('-1 day'));
      return ['from' => $yesterday, 'to' => $yesterday];
    case '7d':
      $from = date('Y-m-d', strtotime('-6 days'));
      return ['from' => $from, 'to' => $today];
    case '30d':
      $from = date('Y-m-d', strtotime('-29 days'));
      return ['from' => $from, 'to' => $today];
    case 'thismonth':
      $from = date('Y-m-01');
      return ['from' => $from, 'to' => $today];
    case 'custom':
      if ($custom_from && $custom_to) {
        return ['from' => $custom_from, 'to' => $custom_to];
      }
      return ['from' => $today, 'to' => $today];
    default:
      return ['from' => $today, 'to' => $today];
  }
}

/**
 * Format currency for display
 * @param float $amount
 * @param string $currency Currency code (default 'PHP')
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = 'PHP') {
  if ($currency === 'PHP') {
    return '₱' . number_format($amount, 2);
  }
  return number_format($amount, 2);
}
