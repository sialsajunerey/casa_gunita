<?php
function formatPrice($amount) {
    return '₱' . number_format($amount, 2);
}

function getCartTotal($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function reduceStock($conn, $product_id, $quantity) {
    $sql = "UPDATE inventory SET stock_quantity = stock_quantity - ? 
            WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $quantity, $product_id);
    mysqli_stmt_execute($stmt);
}

function getCartItemCount($cart) {
    $count = 0;
    foreach ($cart as $item) {
        $count += $item['quantity'];
    }
    return $count;
}
