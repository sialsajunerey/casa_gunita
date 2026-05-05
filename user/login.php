<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        // Log successful login
        $log_stmt = mysqli_prepare($conn, 
            "INSERT INTO user_access_logs (user_id, event_type) VALUES (?, 'login')");
        mysqli_stmt_bind_param($log_stmt, 'i', $user['user_id']);
        mysqli_stmt_execute($log_stmt);

        // Log admin login to audit_logs
        if ($user['role'] === 'admin') {
            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, details)
                 VALUES (?, 'login', 'admin', ?)");
            $details = "Admin logged in";
            mysqli_stmt_bind_param($audit_stmt, 'is', $user['user_id'], $details);
            mysqli_stmt_execute($audit_stmt);
        }

        if ($user['role'] === 'admin') {
            header("Location: /casa_gunita/admin/index.php");
        } else {
            header("Location: /casa_gunita/user/index.php");
        }
        exit();
    } else {
        // Log failed login attempt
        $fail_stmt = mysqli_prepare($conn, 
            "INSERT INTO user_access_logs (user_id, event_type) 
             SELECT user_id, 'failed_login' FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($fail_stmt, 's', $email);
        mysqli_stmt_execute($fail_stmt);
        
        // Log failed admin login attempt to audit_logs
        $check_admin = mysqli_prepare($conn, 
            "SELECT user_id FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        mysqli_stmt_bind_param($check_admin, 's', $email);
        mysqli_stmt_execute($check_admin);
        $admin_check = mysqli_fetch_assoc(mysqli_stmt_get_result($check_admin));
        if ($admin_check) {
            $admin_id = $admin_check['user_id'];
            $audit_stmt = mysqli_prepare($conn,
                "INSERT INTO audit_logs (admin_id, action, target_type, details)
                 VALUES (?, 'failed_login', 'admin', ?)");
            $details = "Failed login attempt from IP: $ip";
            mysqli_stmt_bind_param($audit_stmt, 'is', $admin_id, $details);
            mysqli_stmt_execute($audit_stmt);
        }
        
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Casa Gunita</title>
    <link rel="stylesheet" href="login.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-page">

    <div class="auth-overlay">
        <div class="auth-card">

            <div class="auth-logo">
            </div>

            <h1 class="auth-title">Log In</h1>
            <p class="auth-subtitle">Welcome back. Enter your details to continue.</p>

            <?php if ($error): ?>
                <div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="auth-field">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="auth-field">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="auth-btn">Login</button>
            </form>

            <p class="auth-footer">No account yet? <a href="register.php">Register</a></p>

        </div>
    </div>

</body>
</html>