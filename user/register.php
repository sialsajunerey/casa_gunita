<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email    = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Email already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
            mysqli_stmt_bind_param($stmt, 'sss', $full_name, $email, $hashed);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Account created! You can now login.";
            } else {
                $error = "Registration failed. Try again.";
            }
        }
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Casa Gunita</title>
    <link rel="stylesheet" href="register.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-page">

    <!-- RIGHT: form panel -->
    <div class="auth-card">

        <h1 class="auth-title">Sign Up</h1>
        <p class="auth-subtitle">Join us for authentic Filipino favorites and easy ordering.</p>

        <?php if ($error): ?>
            <div class="auth-message auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="auth-message auth-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <input type="text"    name="full_name" class="auth-input" placeholder="Full Name"            required>
            <input type="email"    name="email"            class="auth-input" placeholder="Email"            required>
            <input type="password" name="password"         class="auth-input" placeholder="Password"         required>
            <input type="password" name="confirm_password" class="auth-input" placeholder="Confirm Password" required>
            <button type="submit" class="auth-button">Register</button>
        </form>

        <p class="auth-footer">Already have an account? <a href="login.php">Login</a></p>

    </div>

</body>
</html>