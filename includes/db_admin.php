<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host     = 'localhost';
$dbname   = 'casa_gunita';
$username = 'admin_user';
$password = 'password'; // replace with the admin_user password you set in phpMyAdmin

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    error_log('Admin DB connection failed: ' . mysqli_connect_error());
    die('Database connection error.');
}

mysqli_set_charset($conn, 'utf8mb4');

// Use this connection for admin pages that require elevated privileges.
// For production: load credentials from environment variables and disable display_errors.
