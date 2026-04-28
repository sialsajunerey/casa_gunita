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

        if ($user['role'] === 'admin') {
            header("Location: /casa_gunita/admin/index.php");
        } else {
            header("Location: /casa_gunita/user/index.php");
        }
        exit();
    } else {
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
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">Casa Gunita</div>
            <h1 class="auth-title">Log In</h1>
            <p class="auth-subtitle">Welcome back. Enter your details to continue.</p>
            <?php if ($error): ?><div class="auth-message auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        </div>
        <form method="POST" class="auth-form">
            <input type="email" name="email" class="auth-input" placeholder="Email" required>
            <input type="password" name="password" class="auth-input" placeholder="Password" required>
            <button type="submit" class="auth-button">Login</button>
        </form>
        <p class="auth-footer">No account yet? <a href="register.php">Register</a></p>
    </div>
</body>
</html>