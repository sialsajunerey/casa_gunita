<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price       = (float)$_POST['price'];
    $category    = sanitize($_POST['category']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $stock       = (int)$_POST['stock_quantity'];
    $image_name  = '';

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, WEBP images allowed.";
        } else {
            $image_name  = time() . '_' . basename($_FILES['image']['name']);
            $upload_dir  = __DIR__ . '/../assets/images/';
            $upload_path = $upload_dir . $image_name;

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $error = "Unable to create upload folder.";
            } elseif (!is_uploaded_file($_FILES['image']['tmp_name'])) {
                $error = "Uploaded file is invalid.";
            } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $error = "Failed to save uploaded image.";
            }
        }
    }

    if (!$error) {
        // Insert product
        $stmt = mysqli_prepare($conn,
            "INSERT INTO products (name, description, price, category, image, is_available)
             VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sdsssi',
            $name, $description, $price, $category, $image_name, $is_available);

        if (mysqli_stmt_execute($stmt)) {
            $product_id = mysqli_insert_id($conn);

            // Insert inventory row
            $inv = mysqli_prepare($conn,
                "INSERT INTO inventory (product_id, stock_quantity, low_stock_alert)
                 VALUES (?, ?, 5)");
            mysqli_stmt_bind_param($inv, 'ii', $product_id, $stock);
            mysqli_stmt_execute($inv);

            $success = "Product added successfully!";
        } else {
            $error = "Failed to add product.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product — Casa Gunita Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .navbar {
            background: #8B0000; color: white;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; }
        .container { padding: 30px; max-width: 600px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type=text], input[type=number],
        textarea, select {
            width: 100%; padding: 10px;
            border: 1px solid #ddd; border-radius: 5px;
            font-size: 14px;
        }
        textarea { height: 80px; resize: vertical; }
        .btn-submit {
            background: #8B0000; color: white;
            padding: 12px 30px; border: none;
            border-radius: 5px; font-size: 16px;
            cursor: pointer; font-weight: bold;
        }
        .btn-submit:hover { background: #a00000; }
        .success { color: #27ae60; font-weight: bold; margin-bottom: 15px; }
        .error   { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Add Product</h2>
    <div>
        <a href="products.php">← Back to Products</a>
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>Add New Product</h3>

    <?php if ($error):   ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label>Dish Name</label>
            <input type="text" name="name" placeholder="e.g. Adobong Manok" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"
                      placeholder="Short description of the dish"></textarea>
        </div>

        <div class="form-group">
            <label>Price (₱)</label>
            <input type="number" name="price" step="0.01"
                   placeholder="e.g. 150.00" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category">
                <option value="Silog Meals">Silog Meals</option>
                <option value="Ulam">Ulam</option>
                <option value="Pulutan">Pulutan</option>
                <option value="Soup">Soup</option>
                <option value="Dessert">Dessert</option>
                <option value="Drinks">Drinks</option>
            </select>
        </div>

        <div class="form-group">
            <label>Stock Quantity</label>
            <input type="number" name="stock_quantity"
                   placeholder="e.g. 50" value="50" required>
        </div>

        <div class="form-group">
            <label>Product Image (optional)</label>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available" checked>
                Available for ordering
            </label>
        </div>

        <button type="submit" class="btn-submit">Add Product</button>
    </form>
</div>

</body>
</html>