<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$result = mysqli_query($conn, "SELECT * FROM products WHERE is_available = 1 ORDER BY category, name");
$menu = [];
while ($row = mysqli_fetch_assoc($result)) {
    $menu[$row['category']][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Menu — Casa Gunita</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .navbar { background: #8B0000; color: #fff; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { font-size: 18px; font-weight: bold; }
        .navbar .links a { color: #fff; text-decoration: none; margin-left: 18px; }
        .navbar .links a:hover { text-decoration: underline; }
        .content { padding: 30px; max-width: 1100px; margin: 0 auto; }
        .page-title { margin: 0 0 16px; color: #333; }
        .cart-summary { margin: 12px 0; font-weight: bold; }
        .item-card { border: 1px solid #ccc; padding: 12px; margin: 10px 0; background: #fff; }
        .item-card img { max-width: 150px; display: block; margin-bottom: 10px; }
        .item-card label { display: block; margin-bottom: 6px; }
        .item-card input[type="number"] { width: 60px; padding: 6px; margin-right: 6px; }
        .item-card button { background: #8B0000; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .item-card button:hover { background: #a10000; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand">Casa Gunita — Menu</div>
        <div class="links">
            <a href="index.php">🏠</a>
            <a href="cart.php">Cart (<span id="nav-cart-count"><?= isset($_SESSION['cart']) ? getCartItemCount($_SESSION['cart']) : 0 ?></span>)</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    <div class="content">
        <h1 class="page-title">Casa Gunita Menu</h1>
        <p class="cart-summary">🛒 Current Cart Item (<span id="menu-cart-count"><?= isset($_SESSION['cart']) ? getCartItemCount($_SESSION['cart']) : 0 ?></span>)</p>

    <?php foreach ($menu as $category => $items): ?>
        <h2><?= htmlspecialchars($category) ?></h2>
        <?php foreach ($items as $item): ?>
            <div class="item-card">
                <?php if (!empty($item['image'])): ?>
                    <img src="/casa-gunita/assets/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php endif; ?>
                <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                <small><?= htmlspecialchars($item['description']) ?></small><br>
                <strong><?= formatPrice($item['price']) ?></strong>
                <form method="POST" action="cart.php" class="add-cart-form" style="margin-top:10px;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id']) ?>">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($item['name']) ?>">
                    <input type="hidden" name="price" value="<?= htmlspecialchars($item['price']) ?>">
                    <label>
                        Quantity:
                        <input type="number" name="quantity" value="1" min="1" max="10">
                    </label>
                    <button type="submit">Add to Cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <script>
        function updateMenuCartCount(count) {
            const navCount = document.getElementById('nav-cart-count');
            const menuCount = document.getElementById('menu-cart-count');
            if (navCount) navCount.textContent = count;
            if (menuCount) menuCount.textContent = count;
        }

        document.querySelectorAll('.add-cart-form').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(form);
                formData.set('ajax', '1');

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams(formData)
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data && data.success) {
                        updateMenuCartCount(data.count);
                    } else {
                        form.submit();
                    }
                })
                .catch(function() {
                    form.submit();
                });
            });
        });
    </script>
</body>
</html>