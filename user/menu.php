<?php
require_once '../includes/db_user.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/auth_modal_handler.php';

$categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selected_category_name = 'All';

if ($category_id > 0) {
    $found = false;
    foreach ($categories as $cat) {
        if ((int)$cat['category_id'] === $category_id) {
            $selected_category_name = $cat['name'];
            $found = true;
            break;
        }
    }
    if (!$found) $category_id = 0;
}

$query = "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.category_id
     WHERE 1=1";
if ($category_id > 0) {
    $query .= " AND p.category_id = ?";
}
$query .= " ORDER BY p.is_available DESC, p.name";

$stmt = mysqli_prepare($conn, $query);
if ($category_id > 0) {
    mysqli_stmt_bind_param($stmt, 'i', $category_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="menu.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300&family=EB+Garamond:wght@400;500&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </div>
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu..." id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
    </div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="index.php#about">About</a>
    </div>
    <div class="nav-icons">
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && getCartItemCount($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= getCartItemCount($_SESSION['cart']) ?></span>
            <?php endif; ?>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="account-wrap">
            <button class="nav-icon-btn" id="accountBtn" aria-label="Account">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </button>
            <div class="account-dropdown" id="accountDropdown">
                <a href="account.php">Account Information</a>
                <a href="order_status.php">My Orders</a>
                <hr>
                <a href="logout.php">Log Out</a>
            </div>
        </div>
        <?php else: ?>
            <button class="nav-auth-btn" onclick="openAuthModal('login')">Login</button>
            <button class="nav-auth-btn reg" onclick="openAuthModal('register')">Register</button>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== CONTENT ===== -->
<div class="content">
    <h1 class="page-title">Hapág ng Gunita</h1>

    <!-- Category Bar -->
    <div class="top-category-bar" id="categoryBar">
        <a href="menu.php?category_id=0" class="category-button <?= $category_id === 0 ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="menu.php?category_id=<?= (int)$cat['category_id'] ?>"
               class="category-button <?= $category_id === (int)$cat['category_id'] ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Menu Grid -->
    <div class="menu-grid">
        <?php if (empty($products)): ?>
            <div class="empty-msg">No products found for this category.</div>
        <?php else: ?>
            <?php foreach ($products as $item): ?>
                <?php $is_available = (int)($item['is_available'] ?? 1) === 1; ?>
                <div class="item-card <?= $is_available ? '' : 'item-unavailable' ?>">
                    <?php if (!empty($item['image'])): ?>
                        <div class="item-img-wrap">
                            <img src="../assets/images/<?= htmlspecialchars($item['image']) ?>"
                                 alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>
                    <?php else: ?>
                        <div class="item-img-wrap placeholder">
                            <span>Image coming soon</span>
                        </div>
                    <?php endif; ?>
                    <div class="item-info">
                        <strong class="item-name"><?= htmlspecialchars($item['name']) ?></strong>
                        <span class="item-price"><?= formatPrice($item['price']) ?></span>
                    </div>
                    <?php if ($is_available): ?>
                        <a href="customize.php?product_id=<?= htmlspecialchars($item['product_id']) ?>" class="order-link">Order</a>
                    <?php else: ?>
                        <span class="order-link unavailable-link">Not Available</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== AUTH MODAL OVERLAY ===== -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal-card">
        <button class="auth-modal-close" onclick="closeAuthModal()">✕</button>
        <div id="loginView">
            <h1 class="auth-modal-title">Log In</h1>
            <p class="auth-modal-subtitle">Welcome back. Enter your details to continue.</p>

            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'login'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="login">
                <div class="auth-modal-field"><input type="email" name="email" placeholder="Email" required></div>
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <button type="submit" class="auth-modal-btn">Login</button>
            </form>
            <p class="auth-modal-footer">No account yet? <a href="javascript:void(0)" onclick="showAuthView('register')">Register</a></p>
        </div>
        <div id="registerView" style="display:none;">
            <h1 class="auth-modal-title">Sign Up</h1>
            <p class="auth-modal-subtitle">Join us for authentic Filipino favorites.</p>

            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'register'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="register">
                <div class="auth-modal-field"><input type="text" name="first_name" placeholder="First Name" required pattern="[A-Za-z.\-]+" title="Only letters, dots, and hyphens allowed"></div>
                <div class="auth-modal-field"><input type="text" name="last_name" placeholder="Last Name" required pattern="[A-Za-z.\-]+" title="Only letters, dots, and hyphens allowed"></div>
                <div class="auth-modal-field"><input type="email" name="email" placeholder="Email" required></div>
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <div class="auth-modal-field password-field"><input type="password" name="confirm_password" placeholder="Confirm Password" required><button type="button" class="password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></div>
                <button type="submit" class="auth-modal-btn">Register</button>
            </form>
            <p class="auth-modal-footer">Already have an account? <a href="javascript:void(0)" onclick="showAuthView('login')">Login</a></p>
        </div>
    </div>
</div>

<script>
function openAuthModal(view) {
    document.getElementById('authModal').classList.add('active');
    showAuthView(view);
}
function closeAuthModal() {
    document.getElementById('authModal').classList.remove('active');
}
function showAuthView(view) {
    document.getElementById('loginView').style.display = (view === 'login') ? 'block' : 'none';
    document.getElementById('registerView').style.display = (view === 'register') ? 'block' : 'none';
}
function initAuthPasswordToggles() {
    document.querySelectorAll('.auth-modal-field.password-field .password-toggle').forEach(button => {
        const field = button.closest('.auth-modal-field.password-field');
        const input = field ? field.querySelector('input') : null;
        if (!input) return;

        const eyeOpen = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        const eyeClosed = '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle><line x1="2" y1="2" x2="22" y2="22"></line></svg>';

        button.innerHTML = input.type === 'password' ? eyeOpen : eyeClosed;

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = show ? eyeClosed : eyeOpen;
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
}
initAuthPasswordToggles();
window.onclick = function(event) {
    if (event.target == document.getElementById('authModal')) closeAuthModal();
}

<?php if ($auth_error): ?>
document.addEventListener('DOMContentLoaded', () => {
    openAuthModal('<?= htmlspecialchars($_POST['auth_type']) ?>');
});
<?php endif; ?>
</script>

<script>
    // Account dropdown
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accountDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            accountDropdown.classList.remove('open');
        });
    }

    // Hide/show category bar on scroll
    const categoryBar = document.getElementById('categoryBar');
    let lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        if (!categoryBar) return;
        if (currentScroll > lastScrollTop + 10) {
            categoryBar.classList.add('hidden');
        } else if (currentScroll < lastScrollTop - 10) {
            categoryBar.classList.remove('hidden');
        }
        lastScrollTop = currentScroll;
    });
</script>

<script src="search.js"></script>

<?php include_once '../includes/order_status_overlay.php'; ?>

</body>
</html>
