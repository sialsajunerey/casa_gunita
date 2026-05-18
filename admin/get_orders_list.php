<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db_admin.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

header('Content-Type: application/json');

$orders_result = mysqli_query($conn,
    "SELECT o.*, CONCAT_WS(' ', u.first_name, u.last_name) AS full_name, u.email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
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

echo json_encode([
    'success' => true,
    'orders' => $orders_js
]);
?>
