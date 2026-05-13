<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/auth_modal_handler.php';

$categoryColumn = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'is_featured'");
if ($categoryColumn && mysqli_num_rows($categoryColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE categories ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
}
$productColumn = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'is_featured'");
if ($productColumn && mysqli_num_rows($productColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
}

$featuredCategories = mysqli_query($conn,
    "SELECT * FROM categories WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 3");
$featured = mysqli_query($conn,
    "SELECT * FROM products WHERE is_available = 1 AND is_featured = 1 ORDER BY created_at DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casa Gunita — Authentic Filipino Cuisine</title>
    <link rel="stylesheet" href="landingpage.css">
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
        <a href="#about">About</a>
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

<!-- ===== SECTION 1: HERO ===== -->
<section class="hero" id="home">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-ornament">ᜃᜐ ᜄᜓᜈᜒᜆ</div>
        <h1 class="hero-title">Casa Gunita</h1>
    </div>
</section>

<!-- ===== SECTION 2: WE OFFER ===== -->
<section class="we-offer" id="menu">
    <div class="we-offer-spotlight"></div>
    <div class="we-offer-inner">
        <h2 class="we-offer-title">We Offer</h2>
        <p class="we-offer-sub">A curated selection of Filipino favorites, crafted with heart.</p>

        <div class="we-offer-grid">
            <?php if (mysqli_num_rows($featuredCategories) === 0): ?>
                <div class="offer-card" style="grid-column:1/-1;text-align:center;color:#777;padding:60px 0;">
                    No categories are currently featured. Please check back later.
                </div>
            <?php else: ?>
                <?php while ($category = mysqli_fetch_assoc($featuredCategories)): ?>
                    <?php
                        $placeholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIj48cmVjdCBmaWxsPSIjZThhMDcyIiB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIvPjwvc3ZnPg==';
                        if (!empty($category['image'])) {
                            $catImg = strpos($category['image'], '/') === false ? '../assets/images/' . $category['image'] : $category['image'];
                        } else {
                            $catImg = $placeholder;
                        }
                    ?>
                    <div class="offer-card">
                        <div class="offer-card-img-wrap">
                            <div class="shine"></div>
                            <img src="<?= htmlspecialchars($catImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <h3><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <a href="menu.php?category_id=<?= (int)$category['category_id'] ?>" class="view-menu">View Menu</a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== BAMBOO DIVIDER ===== -->
<div class="bamboo-divider"></div>

<!-- ===== SECTION 3: ABOUT US ===== -->
<section class="about-section" id="about">
    <div class="about-spotlight"></div>
    <div class="about-inner">
        <div class="about-text">
            <p>
                Each dish we serve is inspired by the rich traditions of Filipino cuisine,
                bringing together familiar flavors and heartfelt moments. <br>
                From our kitchen to your table, we create an experience that feels like home,
                where every bite tells a story worth remembering.
            </p>
        </div>
        <div class="about-right">
            <h2 class="about-title">About Us</h2>
            <div class="about-photos">
                <div class="about-photo about-photo-circle">
                    <img src="foodbg.png" alt="Filipino food">
                </div>
                <div class="about-photo about-photo-rect">
                    <img src="diningbg.png" alt="Restaurant interior">
                </div>
            </div>
            <p class="about-since">Crafting Filipino flavors<br>since 2023</p>
            <a href="menu.php" class="btn-ghost">View Full Menu &nbsp;⟶</a>
        </div>
    </div>
</section>

<!-- ===== SECTION 4: FEATURED DISHES ===== -->
<section class="featured-section" id="featured">
    <div class="featured-inner">
        <h2 class="featured-title">Featured Dishes</h2>

        <?php if (mysqli_num_rows($featured) === 0): ?>
            <div class="offer-card" style="grid-column:1/-1;text-align:center;color:#777;padding:60px 0;width:100%;">
                No dishes are currently featured. Please check back later.
            </div>
        <?php else: ?>
            <div class="featured-grid">
                <?php
                    while ($item = mysqli_fetch_assoc($featured)):
                        $placeholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIj48cmVjdCBmaWxsPSIjZThhMDcyIiB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIvPjwvc3ZnPg==';
                        if (!empty($item['image'])) {
                            $imgFile = strpos($item['image'], '/') === false ? '../assets/images/' . $item['image'] : $item['image'];
                        } else {
                            $imgFile = $placeholder;
                        }
                ?>
                <div class="dish-card">
                    <img src="<?= htmlspecialchars($imgFile) ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>">
                    <div class="dish-overlay">
                        <span class="dish-name"><?= htmlspecialchars($item['name']) ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <a href="menu.php" class="btn-ghost btn-center">View Full Menu &nbsp;⟶</a>
    </div>
</section>

<!-- ===== SECTION 5: CONTACT US ===== -->
<section class="contact-section" id="contact">
    <div class="contact-overlay"></div>
    <div class="contact-inner">
        <h2 class="contact-title">Contact Us</h2>
        <div class="contact-group">
            <p class="contact-label">Landline &amp; Mobile</p>
            <p class="contact-value">8-535-889 | 0968 849 3459</p>
        </div>
        <div class="contact-group">
            <p class="contact-label">Location</p>
            <p class="contact-value">Alcalde Jose St. Kapasigan<br>Pasig City, 1600</p>
        </div>
        <div class="contact-group">
            <p class="contact-label">Breakfast and Lunch Time</p>
            <p class="contact-value">Monday to Friday<br>6:00 am – 1:00 pm</p>
        </div>
        <div class="contact-group">
            <p class="contact-label">Dinner Time</p>
            <p class="contact-value">Monday to Sunday<br>5:00 pm to 10:00 pm</p>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-ornament">ᜃᜐ ᜄᜓᜈᜒᜆ</div>
        <p class="footer-logo">Casa Gunita</p>
    </div>
    <nav class="footer-nav" aria-label="Footer navigation">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
        <a href="#featured">Featured Dishes</a>
    </nav>
</footer>
<div class="footer-copy">© <?= date('Y') ?> Casa Gunita — Authentic Filipino Cuisine</div>

<!-- ===== AUTH MODAL OVERLAY ===== -->
<div class="auth-modal-overlay" id="authModal">
    <div class="auth-modal-card">
        <button class="auth-modal-close" onclick="closeAuthModal()">✕</button>
        
        <!-- Login View -->
        <div id="loginView">
            <h1 class="auth-modal-title">Log In</h1>
            <p class="auth-modal-subtitle">Welcome back. Enter your details to continue.</p>
            
            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'login'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="login">
                <div class="auth-modal-field">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="auth-modal-field">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="auth-modal-btn">Login</button>
            </form>
            <p class="auth-modal-footer">No account yet? <a href="javascript:void(0)" onclick="showAuthView('register')">Register</a></p>
        </div>

        <!-- Register View -->
        <div id="registerView" style="display:none;">
            <h1 class="auth-modal-title">Sign Up</h1>
            <p class="auth-modal-subtitle">Join us for authentic Filipino favorites.</p>

            <?php if ($auth_error && ($_POST['auth_type'] ?? '') === 'register'): ?>
                <div class="auth-modal-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-modal-form">
                <input type="hidden" name="auth_type" value="register">
                <div class="auth-modal-field">
                    <input type="text" name="full_name" placeholder="Full Name" required>
                </div>
                <div class="auth-modal-field">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="auth-modal-field">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="auth-modal-field">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="auth-modal-btn">Register</button>
            </form>
            <p class="auth-modal-footer">Already have an account? <a href="javascript:void(0)" onclick="showAuthView('login')">Login</a></p>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" aria-label="Back to top">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="18 15 12 9 6 15"/>
    </svg>
</button>

<script>
// Auth Modal
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
window.onclick = function(event) {
    if (event.target == document.getElementById('authModal')) closeAuthModal();
}

<?php if ($auth_error): ?>
document.addEventListener('DOMContentLoaded', () => {
    openAuthModal('<?= htmlspecialchars($_POST['auth_type']) ?>');
});
<?php endif; ?>

// Back to Top
const backToTop = document.getElementById('backToTop');
window.addEventListener('scroll', () => {
    if (window.scrollY > 400) {
        backToTop.classList.add('visible');
    } else {
        backToTop.classList.remove('visible');
    }
});
backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Fade in We Offer title on scroll
const weOfferTitle = document.querySelector('.we-offer-title');
if (weOfferTitle) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0, rootMargin: '0px 0px -50px 0px' });
    observer.observe(weOfferTitle);
}

// ✅ FIX: Account Dropdown Toggle
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
</script>

<script src="search.js"></script>

</body>
</html>