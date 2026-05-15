<?php
$auth_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_type'])) {
    $auth_type = $_POST['auth_type'];
    $redirect_to = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : $_SERVER['REQUEST_URI'];

    if ($auth_type === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['full_name']  = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['role']      = $user['role'];

            // Log success
            $log_stmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type) VALUES (?, 'login')");
            mysqli_stmt_bind_param($log_stmt, 'i', $user['user_id']);
            mysqli_stmt_execute($log_stmt);

            if ($user['role'] === 'admin') {
                $audit_stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (admin_id, action, target_type, details) VALUES (?, 'login', 'admin', 'Admin logged in')"); // 'Admin logged in' is hardcoded in the SQL
                mysqli_stmt_bind_param($audit_stmt, 'i', $user['user_id']); // Only bind admin_id
                mysqli_stmt_execute($audit_stmt);
                header("Location: /casa_gunita/admin/index.php");
            } else {
                header("Location: " . $redirect_to);
            }
            exit();
        } else {
            $auth_error = "Invalid email or password.";
            $fail_stmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type) SELECT user_id, 'failed_login' FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($fail_stmt, 's', $email);
            mysqli_stmt_execute($fail_stmt);
        }
    }

    if ($auth_type === 'register') {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name  = sanitize($_POST['last_name'] ?? '');
        $email      = sanitize($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';

        $namePattern = '/^[A-Za-z](?:[A-Za-z.\-]*[A-Za-z])?$/';

        if ($first_name === '' || $last_name === '') {
            $auth_error = "First name and last name are required.";
        } elseif (!preg_match($namePattern, $first_name) || !preg_match($namePattern, $last_name)) {
            $auth_error = "Names may only contain letters, dots, and hyphens.";
        } elseif ($password !== $confirm) {
            $auth_error = "Passwords do not match.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w]).{8,64}$/', $password)) {
            $auth_error = "Password must be 8-64 characters and include uppercase, lowercase, number, and symbol.";
        } else {
            $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
            mysqli_stmt_bind_param($check, 's', $email);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $auth_error = "Email already registered.";
            } else {
                $first_name = ucwords(strtolower($first_name));
                $last_name  = ucwords(strtolower($last_name));
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'customer')");
                mysqli_stmt_bind_param($stmt, 'ssss', $first_name, $last_name, $email, $hashed);

                if (mysqli_stmt_execute($stmt)) {
                    $new_id = mysqli_insert_id($conn);
                    $_SESSION['user_id']   = $new_id;
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name']  = $last_name;
                    $_SESSION['full_name']  = trim($first_name . ' ' . $last_name);
                    $_SESSION['role']       = 'customer';
                    mysqli_query($conn, "INSERT INTO user_access_logs (user_id, event_type) VALUES ($new_id, 'login')");
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