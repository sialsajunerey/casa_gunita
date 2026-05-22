<?php
/**
 * DEBUG: Test Analytics Procedures
 * Run this file to see if procedures are working
 * Then delete it
 */

require_once '../includes/db.php';  
require_once '../includes/analytics.php';  


echo "<h2>Testing Analytics Procedures</h2>";
echo "<pre>";

// Test 1: Check if procedures exist
echo "\n=== TEST 1: Check if procedures exist ===\n";
$result = $pdo->query("SHOW PROCEDURE STATUS WHERE Db='casa_gunita' AND Name LIKE 'sp_analytics%'");
$procedures = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($procedures) . " procedures:\n";
foreach ($procedures as $proc) {
    echo "  - " . $proc['Name'] . "\n";
}

// Test 2: Call sp_analytics_get_kpi directly
echo "\n=== TEST 2: Call sp_analytics_get_kpi ===\n";
try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_kpi(?, ?)");
    $stmt->execute(['2026-05-01', '2026-05-22']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "Result:\n";
    var_dump($result);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Call sp_analytics_get_heatmap directly
echo "\n=== TEST 3: Call sp_analytics_get_heatmap ===\n";
try {
    $stmt = $pdo->prepare("CALL sp_analytics_get_heatmap(?, ?)");
    $stmt->execute(['2026-05-01', '2026-05-22']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "Rows returned: " . count($rows) . "\n";
    if (count($rows) > 0) {
        echo "First 5 rows:\n";
        foreach (array_slice($rows, 0, 5) as $row) {
            print_r($row);
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Check actual orders in database
echo "\n=== TEST 4: Check orders in database ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(total_amount) as revenue FROM orders WHERE status != 'cancelled'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total orders (excl. cancelled): " . $result['count'] . "\n";
    echo "Total revenue: " . $result['revenue'] . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Check orders by date
echo "\n=== TEST 5: Check orders by date range ===\n";
try {
    $stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM orders WHERE status != 'cancelled' GROUP BY DATE(created_at) ORDER BY date DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Orders by date:\n";
    foreach ($results as $row) {
        echo "  " . $row['date'] . ": " . $row['count'] . " orders\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
echo "</pre>";
?>
