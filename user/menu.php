<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$result = mysqli_query($conn,
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_available = 1
     ORDER BY c.name, p.name");
$menu = [];
while ($row = mysqli_fetch_assoc($result)) {
    $category = $row['category_name'] ?? 'Uncategorized';
    $menu[$category][] = $row;
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
        .content { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .page-title { margin: 0 0 16px; color: #333; }
        .cart-summary { margin: 12px 0; font-weight: bold; }
        .menu-layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; }
        .sidebar { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .sidebar h3 { margin-top: 0; color: #8B0000; font-size: 20px; margin-bottom: 16px; }
        .category-link { display: block; width: 100%; text-align: left; background: transparent; border: none; padding: 12px 14px; margin-bottom: 10px; font-size: 15px; cursor: pointer; border-radius: 8px; color: #333; transition: background 0.2s, color 0.2s; }
        .category-link:hover,
        .category-link.active { background: #8B0000; color: #fff; }
        .menu-main { }
        .category-section { display: none; margin-bottom: 40px; }
        .category-section.active { display: block; }
        .category-title { color: #8B0000; font-size: 24px; margin-bottom: 20px; border-bottom: 2px solid #8B0000; padding-bottom: 10px; }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .menu-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }
        .menu-item:hover { transform: translateY(-5px); }
        .menu-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .menu-item .no-image {
            width: 100%;
            height: 180px;
            background: #f0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        .menu-item-body { padding: 15px; }
        .menu-item h3 { margin: 0 0 10px; color: #333; font-size: 18px; }
        .menu-item .price { color: #8B0000; font-weight: bold; font-size: 20px; margin-bottom: 15px; }
        .menu-item button {
            background: #8B0000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .menu-item button:hover { background: #a10000; }
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

        <div class="menu-layout">
            <aside class="sidebar">
                <h3>Categories</h3>
                <?php foreach ($menu as $category => $items): ?>
                    <button type="button" class="category-link" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></button>
                <?php endforeach; ?>
            </aside>

            <main class="menu-main">
                <?php foreach ($menu as $category => $items): ?>
                    <div class="category-section" data-category="<?= htmlspecialchars($category) ?>">
                        <h2 class="category-title"><?= htmlspecialchars($category) ?></h2>
                        <div class="menu-grid">
                            <?php foreach ($items as $item): ?>
                                <div class="menu-item">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="/casa_gunita/assets/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php else: ?>
                                        <div class="no-image">🍽️</div>
                                    <?php endif; ?>
                                    <div class="menu-item-body">
                                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                                        <div class="price"><?= formatPrice($item['price']) ?></div>
                                        <form method="POST" action="cart.php" class="add-cart-form">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($item['product_id']) ?>">
                                            <input type="hidden" name="name" value="<?= htmlspecialchars($item['name']) ?>">
                                            <input type="hidden" name="price" value="<?= htmlspecialchars($item['price']) ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit">Order</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </main>
        </div>
    </div>

    <script>
        function setActiveCategory(category) {
            document.querySelectorAll('.category-link').forEach(function(button) {
                button.classList.toggle('active', button.dataset.category === category);
            });
            document.querySelectorAll('.category-section').forEach(function(section) {
                section.classList.toggle('active', section.dataset.category === category);
            });
        }

        document.querySelectorAll('.category-link').forEach(function(button, index) {
            button.addEventListener('click', function() {
                setActiveCategory(button.dataset.category);
            });
            if (index === 0) {
                setActiveCategory(button.dataset.category);
            }
        });

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