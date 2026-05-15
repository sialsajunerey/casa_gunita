<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
    exit();
}

$stmt = mysqli_prepare($conn,
    "SELECT user_id, first_name, last_name, email, created_at
     FROM users
     WHERE user_id = ? AND role = 'customer'");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit();
}

$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$whereClauses = ["user_id = ?"];
$types = 'i';
$bindValues = [$user_id];

if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $whereClauses[] = "event_time >= ?";
    $types .= 's';
    $bindValues[] = $date_from . ' 00:00:00';
}
if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $whereClauses[] = "event_time <= ?";
    $types .= 's';
    $bindValues[] = $date_to . ' 23:59:59';
}

$sql = "SELECT IFNULL(NULLIF(event_type, ''), 'log_entry') AS event_type, event_time
     FROM user_access_logs
     WHERE " . implode(' AND ', $whereClauses) . "
     ORDER BY event_time DESC
     LIMIT 200";
$logStmt = mysqli_prepare($conn, $sql);

$bindParams = [];
$bindParams[] = &$types;
foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}
call_user_func_array('mysqli_stmt_bind_param', array_merge([$logStmt], $bindParams));
mysqli_stmt_execute($logStmt);
$logResult = mysqli_stmt_get_result($logStmt);
$logs = [];
while ($log = mysqli_fetch_assoc($logResult)) {
    $logs[] = [
        'event_type' => $log['event_type'],
        'event_time' => $log['event_time'],
    ];
}

echo json_encode([
    'success'    => true,
    'user_id'    => $user['user_id'],
    'first_name' => $user['first_name'],
    'last_name'  => $user['last_name'],
    'email'      => $user['email'],
    'created_at' => $user['created_at'],
    'logs'       => $logs,
]);
