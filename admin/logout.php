<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log admin logout before destroying session
if (isset($_SESSION['user_id'])) {
    $admin_id = $_SESSION['user_id'];
    $audit_stmt = mysqli_prepare($conn, 
        "INSERT INTO audit_logs (admin_id, action, target_type, details)
         VALUES (?, 'logout', 'admin', 'Admin logged out')");
    mysqli_stmt_bind_param($audit_stmt, 'i', $admin_id);
    mysqli_stmt_execute($audit_stmt);
}

session_unset();
session_destroy();

header('Location: ../user/index.php');
exit();
?>