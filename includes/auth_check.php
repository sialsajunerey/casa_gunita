<?php
require_once 'session.php';

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /casa_gunita/user/login.php");
        exit();
    }
}

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: /casa_gunita/user/login.php");
        exit();
    }
}

function requireCustomer() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
        header("Location: /casa_gunita/user/login.php");
        exit();
    }
}
?>