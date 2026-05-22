<?php
/**
 * Casa Gunita Analytics Data Aggregation Helpers
 */

require_once __DIR__ . '/db.php';

/**
 * Fetch and format all analytics data for the specified date range.
 *
 * @param string $date_from Y-m-d format
 * @param string $date_to Y-m-d format
 * @return array
 */
function get_analytics_data($date_from, $date_to) {
    global $pdo;

    // Structure matching the frontend expectations in analytics.js
    $data = [
        'kpis' => [
            'total_orders' => 0,
            'total_revenue' => 0.00,
            'peak_hour' => '—',
            'top_item' => '—',
            'top_item_count' => 0
        ],
        'heatmap' => [
            'grid' => array_fill(0, 7, array_fill(0, 24, 0)),
            'days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
        ],
        'pie' => [
            'status' => [],
            'category' => [],
            'time' => [],
            'ordertype' => []
        ],
        'topPerforming' => [
            'item' => [],
            'category' => [],
            'area' => []
        ],
        'ranked' => [
            'item' => [],
            'category' => [],
            'area' => []
        ]
    ];

    if (!$pdo) {
        return $data;
    }

    try {
        // 1. KPIs
        $stmt = $pdo->prepare("CALL sp_analytics_get_kpi(?, ?)");
        $stmt->execute([$date_from, $date_to]);
        $kpi = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($kpi) {
            $data['kpis'] = [
                'total_orders' => (int)$kpi['total_orders'],
                'total_revenue' => (float)$kpi['total_revenue'],
                'peak_hour' => $kpi['peak_hour'],
                'top_item' => $kpi['top_item'],
                'top_item_count' => (int)$kpi['top_item_count']
            ];
        }

        // 2. Heatmap
        $stmt = $pdo->prepare("CALL sp_analytics_get_heatmap(?, ?)");
        $stmt->execute([$date_from, $date_to]);
        $heatmap_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($heatmap_rows as $row) {
            $day_idx = (int)$row['day_num'] - 1; // DAYOFWEEK returns 1 (Sun) to 7 (Sat)
            $hour_idx = (int)$row['hour'];
            if ($day_idx >= 0 && $day_idx < 7 && $hour_idx >= 0 && $hour_idx < 24) {
                $data['heatmap']['grid'][$day_idx][$hour_idx] = (int)$row['order_count'];
            }
        }

        // 3. Pie Charts (Breakdown data)
        foreach (['status', 'category', 'time', 'ordertype'] as $group_by) {
            $stmt = $pdo->prepare("CALL sp_analytics_get_pie_data(?, ?, ?)");
            $stmt->execute([$date_from, $date_to, $group_by]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $data['pie'][$group_by] = array_map(function($row) {
                return [
                    'label' => $row['label'],
                    'count' => (int)$row['count'],
                    'percentage' => (float)$row['percentage']
                ];
            }, $rows);
        }

        // 4. Top Performing (last 7 days trend)
        foreach (['item', 'category', 'area'] as $type) {
            $stmt = $pdo->prepare("CALL sp_analytics_get_top_performing(?, ?)");
            $stmt->execute([$type, 10]); // Top 10 items
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $data['topPerforming'][$type] = array_map(function($row) use ($type) {
                $formatted = [
                    'date' => $row['date'],
                    'label' => $row['label'],
                    'id' => $row['id']
                ];
                if ($type === 'area') {
                    $formatted['order_count'] = (int)$row['order_count'];
                    $formatted['revenue'] = (float)$row['revenue'];
                    $formatted['district'] = $row['district'] ?? 'Unknown District';
                } else {
                    $formatted['order_count'] = (int)$row['order_count'];
                    $formatted['quantity_sold'] = (int)$row['quantity_sold'];
                    if ($type === 'item') {
                        $formatted['category'] = $row['category'] ?? 'Uncategorized';
                    }
                }
                return $formatted;
            }, $rows);
        }

        // 5. Ranked Breakdown (complete list for client-side sort & pagination)
        foreach (['item', 'category', 'area'] as $type) {
            $stmt = $pdo->prepare("CALL sp_analytics_get_ranked_items(?, ?, ?, ?, ?)");
            $stmt->execute([$date_from, $date_to, $type, 1000, 0]); // Fetch top 1000
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $data['ranked'][$type] = array_map(function($row) use ($type) {
                $formatted = [
                    'id' => $row['id'],
                    'label' => $row['label'],
                    'order_count' => (int)$row['order_count'],
                    'revenue' => (float)$row['revenue']
                ];
                if ($type === 'item') {
                    $formatted['quantity_sold'] = (int)$row['quantity_sold'];
                    $formatted['category'] = $row['category'] ?? 'Uncategorized';
                } elseif ($type === 'category') {
                    $formatted['quantity_sold'] = (int)$row['quantity_sold'];
                } elseif ($type === 'area') {
                    $formatted['unique_customers'] = (int)$row['unique_customers'];
                    $formatted['district'] = $row['district'] ?? 'Unknown District';
                }
                return $formatted;
            }, $rows);
        }

    } catch (Exception $e) {
        error_log("Error in get_analytics_data: " . $e->getMessage());
    }

    return $data;
}
