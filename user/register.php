<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email     = sanitize($_POST['email']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
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
<html>
<head><title>Register — Casa Gunita</title></head>
<body>
    <h2>Create Account</h2>
    <?php if ($error): ?><p style="color:red"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color:green"><?= $success ?></p><?php endif; ?>
    
    <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required><br>
        <button type="submit">Register</button>
    </form>
    <a href="login.php">Already have an account? Login</a>
</body>
</html>