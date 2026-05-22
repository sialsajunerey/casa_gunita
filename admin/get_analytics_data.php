<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable direct error display to ensure clean JSON output

require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/analytics.php';

// Check if user is logged in as administrator
requireAdmin();

header('Content-Type: application/json');

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to'] ?? date('Y-m-d');

// Basic sanitization of parameters
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? $date_from : date('Y-m-d');
$date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) ? $date_to : date('Y-m-d');

$data = get_analytics_data($date_from, $date_to);

echo json_encode($data);
exit();
