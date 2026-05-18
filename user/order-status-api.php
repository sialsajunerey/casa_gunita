<?php
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Get latest active/recent order
if ($action === 'get_latest') {
    $stmt = mysqli_prepare($conn,
        "SELECT order_id, total_amount, status, order_type, created_at 
         FROM orders 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'No orders found']);
        exit();
    }
    
    // Get order items
    $items_stmt = mysqli_prepare($conn,
        "SELECT p.name, oi.quantity, oi.unit_price, oi.subtotal 
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?");
    mysqli_stmt_bind_param($items_stmt, 'i', $order['order_id']);
    mysqli_stmt_execute($items_stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
    
    $order['items'] = $items;
    $order['formatted_total'] = formatPrice($order['total_amount']);
    $order['formatted_time'] = date('M d, Y h:i A', strtotime($order['created_at']));
    echo json_encode(['success' => true, 'order' => $order]);
    exit();
}

// Fallback: get specific order by ID
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT order_id, total_amount, status, order_type, created_at FROM orders WHERE order_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit();
}

// Get order items
$items_stmt = mysqli_prepare($conn,
    "SELECT p.name, oi.quantity, oi.unit_price, oi.subtotal 
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?");
mysqli_stmt_bind_param($items_stmt, 'i', $order['order_id']);
mysqli_stmt_execute($items_stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'order' => [
        'order_id' => (int)$order['order_id'],
        'total_amount' => (float)$order['total_amount'],
        'status' => $order['status'],
        'order_type' => $order['order_type'],
        'created_at' => $order['created_at'],
        'formatted_total' => formatPrice($order['total_amount']),
        'formatted_time' => date('M d, Y h:i A', strtotime($order['created_at'])),
        'items' => $items
    ]
]);
?>
