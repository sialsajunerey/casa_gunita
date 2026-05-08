<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

// Log logout before destroying session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $log_stmt = mysqli_prepare($conn, 
        "INSERT INTO user_access_logs (user_id, event_type) VALUES (?, 'logout')");
    mysqli_stmt_bind_param($log_stmt, 'i', $user_id);
    mysqli_stmt_execute($log_stmt);
}

session_destroy();
header("Location: index.php");
exit();
?>