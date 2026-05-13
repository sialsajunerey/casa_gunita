<?php
$auth_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_type'])) {
    $auth_type = $_POST['auth_type'];
    $redirect_to = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : $_SERVER['REQUEST_URI'];

    if ($auth_type === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Log success
            logAccess($conn, $user['user_id'], 'login');

            if ($user['role'] === 'admin') {
                logAudit($conn, 'login', 'admin', $user['user_id'], 'Admin logged in');
                header("Location: /casa_gunita/admin/index.php");
            } else {
                header("Location: " . $redirect_to);
            }
            exit();
        } else {
            $auth_error = "Invalid email or password.";
            // Log failed attempt if user exists
            if($user) logAccess($conn, $user['user_id'], 'failed_login');
        }
    }

    if ($auth_type === 'register') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            $auth_error = "Passwords do not match.";
        } else {
            $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($check, 's', $email);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $auth_error = "Email already registered.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
                mysqli_stmt_bind_param($stmt, 'sss', $full_name, $email, $hashed);

                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    $_SESSION['user_id'] = $new_id;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role'] = 'customer';
                    
                    $reg_log = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type) VALUES (?, 'login')");
                    mysqli_stmt_bind_param($reg_log, 'i', $new_id);
                    mysqli_stmt_execute($reg_log);

                    header("Location: " . $redirect_to);
                    exit();
                } else {
                    $auth_error = "Registration failed. Try again.";
                }
            }
        }
    }
}
?>