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

$id = (int)$_GET['id'];

// Fetch existing product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    echo "Product not found.";
    exit();
}

// Fetch current stock
$inv_stmt = mysqli_prepare($conn,
    "SELECT * FROM inventory WHERE product_id = ?");
mysqli_stmt_bind_param($inv_stmt, 'i', $id);
mysqli_stmt_execute($inv_stmt);
$inventory = mysqli_fetch_assoc(mysqli_stmt_get_result($inv_stmt));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name']);
    $description  = trim($_POST['description'] ?? '');
    $price        = (float)$_POST['price'];
    $category     = sanitize($_POST['category']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $stock        = (int)$_POST['stock_quantity'];
    $image_name   = $product['image']; // keep old image by default

    // Handle new image upload
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
        // Update product
        $stmt = mysqli_prepare($conn,
            "UPDATE products 
             SET name=?, description=?, price=?, category=?, image=?, is_available=?
             WHERE product_id=?");
        mysqli_stmt_bind_param($stmt, 'ssdssii',
            $name, $description, $price, $category, $image_name, $is_available, $id);

        if (mysqli_stmt_execute($stmt)) {
            // Update inventory stock
            $inv_update = mysqli_prepare($conn,
                "UPDATE inventory SET stock_quantity=? WHERE product_id=?");
            mysqli_stmt_bind_param($inv_update, 'ii', $stock, $id);
            mysqli_stmt_execute($inv_update);

            header('Location: products.php');
            exit();
        } else {
            $error = "Failed to update product.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product — Casa Gunita Admin</title>
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
            border: 1px solid #ddd; border-radius: 5px; font-size: 14px;
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
        .current-img { margin-bottom: 10px; }
        .current-img img {
            width: 100px; height: 100px;
            object-fit: cover; border-radius: 5px;
            border: 2px solid #ddd;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2 style="margin:0">🍽️ Casa Gunita — Edit Product</h2>
    <div>
        <a href="products.php">← Back to Products</a>
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h3>Edit: <?= $product['name'] ?></h3>

    <?php if ($error):   ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label>Dish Name</label>
            <input type="text" name="name"
                   value="<?= htmlspecialchars($name ?? $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($description ?? $product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <label>Price (₱)</label>
            <input type="number" name="price" step="0.01"
                   value="<?= htmlspecialchars($price ?? $product['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category">
                <?php
                $categories = ['Silog Meals','Ulam','Pulutan','Soup','Dessert','Drinks'];
                foreach ($categories as $cat):
                ?>
                <option value="<?= $cat ?>"
                    <?= (isset($category) ? $category : $product['category']) === $cat ? 'selected' : '' ?>>
                    <?= $cat ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Stock Quantity</label>
            <input type="number" name="stock_quantity"
                   value="<?= htmlspecialchars(isset($stock) ? $stock : ($inventory['stock_quantity'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Product Image</label>
            <?php if ($product['image']): ?>
            <div class="current-img">
                <p style="font-size:12px; color:#666;">Current image:</p>
                <img src="/casa_gunita/assets/images/<?= $product['image'] ?>"
                     alt="current">
            </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
            <small style="color:#999;">Leave blank to keep current image</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_available"
                    <?= (isset($is_available) ? $is_available : $product['is_available']) ? 'checked' : '' ?>>
                Available for ordering
            </label>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
</div>

</body>
</html>