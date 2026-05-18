<?php
require_once 'db_user.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get the user's latest order
$stmt = mysqli_prepare($conn, 
    "SELECT order_id, status, total_amount 
     FROM orders 
     WHERE user_id = ? 
     ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if ($order) {
    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => (int)$order['order_id'],
            'status' => $order['status'],
            'total_amount' => (float)$order['total_amount']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No active order']);
}
?>
