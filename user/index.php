<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

// Fetch featured products (latest 3)
$featured = mysqli_query($conn,
    "SELECT * FROM products WHERE is_available = 1 ORDER BY created_at DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casa Gunita — Authentic Filipino Cuisine</title>
    <link rel="stylesheet" href="css/home.css">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600&family=Cinzel+Decorative:wght@400;700&family=Playfair+Display:ital,wght@0,400;1,400&family=Cormorant+Garamond:ital,wght@0,300;1,300&display=swap" rel="stylesheet">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search">
    </div>

    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="about.php">About</a>
    </div>

    <div class="nav-icons">
        <!-- Cart -->
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= count($_SESSION['cart']) ?></span>
            <?php endif; ?>
        </a>

        <!-- Account -->
        <div class="account-wrap">
            <button class="nav-icon-btn" id="accountBtn" aria-label="Account">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8">
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
    </div>
</nav>

<!-- ===== SECTION 1: HERO ===== -->
<section class="hero" id="home">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-ornament">✦ ✦ ✦ ✦ ✦</div>
        <h1 class="hero-title">Casa Gunita</h1>
    </div>
</section>

<!-- ===== SECTION 2: WE OFFER ===== -->
<section class="we-offer" id="menu">
    <div class="bling-overlay"></div>
    <div class="we-offer-inner">
        <h2 class="we-offer-title">We Offer</h2>
        <p class="we-offer-sub">
            A curated<br>selection of<br>Filipino<br>favorites,<br>crafted with<br>heart.
        </p>

        <div class="offer-grid">
            <!-- Left: Breakfast -->
            <div class="offer-card offer-left">
                <div class="offer-img-wrap">
                    <img src="../assets/images/bfastbg.jpg" alt="Breakfast">
                </div>
                <div class="offer-ornament-bar">
                    <span>❖</span><span class="ornament-line"></span><span>❖</span>
                </div>
                <p class="offer-name">Breakfast</p>
                <a href="menu.php?category=breakfast" class="offer-link">View Menu</a>
            </div>

            <!-- Center: Main Course -->
            <div class="offer-card offer-center">
                <div class="offer-ornament-bar offer-ornament-top">
                    <span>❖</span><span class="ornament-line"></span><span>❖</span>
                </div>
                <div class="offer-img-wrap">
                    <img src="../assets/images/lunchbg.jpg" alt="Main Course">
                </div>
                <p class="offer-name">Main Course</p>
                <a href="menu.php?category=main" class="offer-link">View Menu</a>
            </div>

            <!-- Right: Drinks & Desserts -->
            <div class="offer-card offer-right">
                <div class="offer-img-wrap">
                    <img src="../assets/images/drinksdessbg.jpg" alt="Drinks & Desserts">
                </div>
                <div class="offer-ornament-bar">
                    <span>❖</span><span class="ornament-line"></span><span>❖</span>
                </div>
                <p class="offer-name">Drinks &amp;<br>Desserts</p>
                <a href="menu.php?category=drinks" class="offer-link">View Menu</a>
            </div>
        </div>
    </div>
</section>

<!-- ===== BAMBOO DIVIDER ===== -->
<div class="bamboo-divider"></div>

<!-- ===== SECTION 3: ABOUT US ===== -->
<section class="about-section" id="about">
    <div class="about-inner">
        <div class="about-text">
            <p>
                Each dish we serve is inspired by the rich traditions of Filipino cuisine,
                bringing together familiar flavors and heartfelt moments. From our kitchen
                to your table, we create an experience that feels like home, where every
                bite tells a story worth remembering.
            </p>
        </div>
        <div class="about-right">
            <h2 class="about-title">About Us</h2>
            <div class="about-photos">
                <div class="about-photo about-photo-circle">
                    <img src="../assets/images/aboutfood.jpg" alt="Filipino food">
                </div>
                <div class="about-photo about-photo-rect">
                    <img src="../assets/images/aboutrest.jpg" alt="Restaurant interior">
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
        <div class="section-ornament">✦</div>
        <h2 class="featured-title">Featured Dishes</h2>

        <?php if (mysqli_num_rows($featured) === 0): ?>
            <p class="empty-msg">Menu coming soon. Check back later!</p>
        <?php else: ?>
            <div class="featured-grid">
                <?php while ($item = mysqli_fetch_assoc($featured)): ?>
                <div class="dish-card">
                    <?php if ($item['image']): ?>
                        <img src="/casa_gunita/assets/images/<?= htmlspecialchars($item['image']) ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                        <div class="dish-no-img">🍽️</div>
                    <?php endif; ?>
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
        <div class="footer-ornament">✦ ✦ ✦ ✦ ✦</div>
        <p class="footer-logo">Casa Gunita</p>
    </div>
    <nav class="footer-nav" aria-label="Footer navigation">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="about.php">About Us</a>
        <a href="#contact">Contact</a>
        <a href="promos.php">Promos</a>
        <a href="#featured">Featured Dishes</a>
    </nav>
    <p class="footer-copy">© <?= date('Y') ?> Casa Gunita — Authentic Filipino Cuisine</p>
</footer>

<script>
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');

    accountBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });

    document.addEventListener('click', function() {
        accountDropdown.classList.remove('open');
    });
</script>

</body>
</html>