<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Configuration - Replace with your actual credentials
$client_id     = '681255809395-oo8ft2oprdrnp9e3aqf6av3hmdib135j.apps.googleusercontent.com';
$client_secret = 'YOUR_GOOGLE_CLIENT_SECRET'; 
$redirect_uri  = 'http://localhost:61698/oauth2redirect'; // As per your request

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for access token
    $token_url = "https://oauth2.googleapis.com/token";
    $params = [
        'code'          => $code,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $access_token = $data['access_token'];

        // Get user profile info
        $info_url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $access_token;
        $user_info = json_decode(file_get_contents($info_url), true);

        if (isset($user_info['id'])) {
            $google_id  = $user_info['id'];
            $email      = $user_info['email'];
            $first_name = $user_info['given_name'];
            $last_name  = $user_info['family_name'];

            // Check if user exists by google_id or email
            $stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name, role FROM users WHERE google_id = ? OR email = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $google_id, $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user) {
                // User exists, update google_id if missing and login
                if (empty($user['google_id'])) {
                    $upd = mysqli_prepare($conn, "UPDATE users SET google_id = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($upd, 'si', $google_id, $user['user_id']);
                    mysqli_stmt_execute($upd);
                }
            } else {
                // Register new user
                $role = 'customer';
                $ins = mysqli_prepare($conn, "INSERT INTO users (first_name, last_name, email, google_id, role) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins, 'sssss', $first_name, $last_name, $email, $google_id, $role);
                mysqli_stmt_execute($ins);
                
                $new_id = mysqli_insert_id($conn);
                $user = [
                    'user_id'    => $new_id,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'role'       => $role
                ];
            }

            // Set Session
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['full_name']  = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['role']       = $user['role'];

            // Log access
            $log_stmt = mysqli_prepare($conn, "INSERT INTO user_access_logs (user_id, event_type) VALUES (?, 'login')");
            mysqli_stmt_bind_param($log_stmt, 'i', $user['user_id']);
            mysqli_stmt_execute($log_stmt);

            header("Location: index.php");
            exit();
        }
    }
}

// If something fails
header("Location: index.php?error=google_auth_failed");
exit();
?>