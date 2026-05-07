<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$search = trim($_GET['q'] ?? '');

if (strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

// Search for products matching the query
$searchTerm = '%' . mysqli_real_escape_string($conn, $search) . '%';
$stmt = mysqli_prepare($conn,
    "SELECT product_id, name, image, price, category_id
     FROM products
     WHERE is_available = 1 AND (name LIKE ? OR description LIKE ?)
     ORDER BY name
     LIMIT 8");

mysqli_stmt_bind_param($stmt, 'ss', $searchTerm, $searchTerm);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = [
        'product_id' => (int)$row['product_id'],
        'name' => htmlspecialchars($row['name']),
        'image' => htmlspecialchars($row['image'] ?? ''),
        'price' => (float)$row['price'],
        'category_id' => (int)$row['category_id'],
        'formattedPrice' => formatPrice((float)$row['price'])
    ];
}

echo json_encode($products);
?>
