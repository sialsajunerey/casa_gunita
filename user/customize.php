<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/auth_modal_handler.php';

$editCartKey = trim($_GET['cart_key'] ?? '');
$editCartItem = null;
if ($editCartKey !== '' && isset($_SESSION['cart'][$editCartKey])) {
    $editCartItem = $_SESSION['cart'][$editCartKey];
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    header('Location: menu.php');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT product_id, name, description, price, image FROM products WHERE product_id = ? AND is_available = 1");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$productResult = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($productResult);

if (!$product) {
    header('Location: menu.php');
    exit();
}

$groups = [];
$groupStmt = mysqli_prepare($conn, "SELECT group_id, name, group_type, pricing_type, is_required FROM product_customization_groups WHERE product_id = ? ORDER BY display_order, group_id");
mysqli_stmt_bind_param($groupStmt, 'i', $product_id);
mysqli_stmt_execute($groupStmt);
$groupResult = mysqli_stmt_get_result($groupStmt);
while ($group = mysqli_fetch_assoc($groupResult)) {
    $groups[$group['group_id']] = $group;
    $groups[$group['group_id']]['options'] = [];
}

if (!empty($groups)) {
    $groupIds = array_keys($groups);
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $sql = "SELECT option_id, group_id, name, additional_price, image FROM product_customization_options WHERE group_id IN ($placeholders) ORDER BY display_order, option_id";
    $stmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('i', count($groupIds));
    $refs = [];
    foreach ($groupIds as $index => $groupId) {
        $refs[$index] = &$groupIds[$index];
    }
    array_unshift($refs, $types);
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));
    mysqli_stmt_execute($stmt);
    $optionResult = mysqli_stmt_get_result($stmt);
    while ($option = mysqli_fetch_assoc($optionResult)) {
        $groups[$option['group_id']]['options'][] = $option;
    }
}

$anyRequired = false;
foreach($groups as $group) {
    if($group['is_required']) $anyRequired = true;
}

function buildOptionLabel($option) {
    $label = htmlspecialchars($option['name']);
    if ((float)$option['additional_price'] > 0) {
        $label .= ' (' . htmlspecialchars('+' . number_format($option['additional_price'], 2)) . ')';
    }
    return $label;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize — Casa Gunita</title>
    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="customize.css">
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
    
    <!-- Desktop Navigation -->
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
<div class="customize-content">
    <div class="customize-card">

        <!-- Product Top -->
        <div class="product-top">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="../assets/images/<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="product-image-placeholder" style="display:none;">No image available</div>
                <?php else: ?>
                    <div class="product-image-placeholder">No image available</div>
                <?php endif; ?>
            </div>
            <div class="product-meta">
                <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="product-desc"><?= nl2br(htmlspecialchars($product['description'] ?? 'Choose your options before adding to cart.')) ?></p>
                <div class="product-price">
                    <span>Base Price: </span>
                    <span id="baseDisplayPrice"><?= formatPrice($product['price']) ?></span>
                    <div id="validation-warning" style="display:none; margin-top:15px; padding:10px; background:rgba(176,48,48,0.1); color:#b03030; border:1px solid rgba(176,48,48,0.2); border-radius:6px; font-size:13px; font-family:'Public Sans', sans-serif;"></div>
                    <input type="hidden" id="basePrice" value="<?= htmlspecialchars($product['price']) ?>">
                </div>

                <!-- Quantity Selector — below base price -->
                <div class="quantity-selector">
                    <p class="qty-label">Quantity</p>
                    <div class="qty-controls qty-box">
                        <button type="button" onclick="adjQty(-1)" aria-label="Decrease quantity">−</button>
                        <span id="qtyDisplay"><?= htmlspecialchars($editCartItem['quantity'] ?? 1) ?></span>
                        <button type="button" onclick="adjQty(1)" aria-label="Increase quantity">+</button>
                    </div>
                </div>

                <!-- Customization Summary -->
                <div id="customizationSummary" style="font-size: 13px; color: #8a7060; margin-top: 12px; font-family: 'Public Sans', sans-serif; display: none;"></div>

                <!-- Total Price -->
                <div class="total-price-box">
                    <span>Total: </span>
                    <span id="displayPrice"><?= formatPrice($product['price']) ?></span>
                </div>
            </div>
        </div>

        <!-- Customization Form -->
        <form method="POST" action="cart.php" class="customization-section">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
            <input type="hidden" name="edit_cart_key" value="<?= htmlspecialchars($editCartKey) ?>">
            <input type="hidden" id="quantity" name="quantity" value="<?= htmlspecialchars($editCartItem['quantity'] ?? 1) ?>">

            <?php foreach ($groups as $group): ?>
                <div class="customization-group" data-group-name="<?= htmlspecialchars($group['name']) ?>" data-required="<?= $group['is_required'] ? '1' : '0' ?>">
                    <h3 class="group-title">
                        <?= htmlspecialchars($group['name']) ?>
                        <?= $group['is_required'] ? '<span class="required-star">*</span>' : '' ?>
                    </h3>
                    <div class="option-list">
                        <?php foreach ($group['options'] as $option): ?>
                            <?php $inputName = 'option_ids[' . $group['group_id'] . ']' . ($group['group_type'] === 'addon' ? '[]' : ''); ?>
                            <?php $selected = $editCartItem && !empty($editCartItem['options']) && in_array($option['option_id'], array_column($editCartItem['options'], 'option_id'), true); ?>
                            <label class="option-card">
                                <input
                                    type="<?= $group['group_type'] === 'addon' ? 'checkbox' : 'radio' ?>"
                                    name="<?= $inputName ?>"
                                    value="<?= htmlspecialchars($option['option_id']) ?>"
                                    data-price="<?= htmlspecialchars($option['additional_price']) ?>"
                                    data-group-type="<?= htmlspecialchars($group['group_type']) ?>"
                                    data-pricing="<?= htmlspecialchars($group['pricing_type'] ?? 'extra_charge') ?>"
                                    <?= $selected ? 'checked' : '' ?>
                                >
                                <div class="option-content">
                                    <p class="option-name"><?= htmlspecialchars($option['name']) ?></p>
                                    <p class="option-price">
                                        <?php if ($group['group_type'] === 'addon'): ?>
                                            <?= $option['additional_price'] > 0 ? '+ ' . formatPrice($option['additional_price']) : 'No extra charge' ?>
                                        <?php else: ?>
                                            <?php if ($group['pricing_type'] === 'extra_charge'): ?>
                                                <?= $option['additional_price'] > 0 ? '+ ' . formatPrice($option['additional_price']) : 'No extra charge' ?>
                                            <?php else: ?>
                                                <?= $option['additional_price'] > 0 ? formatPrice($option['additional_price']) : 'Included' ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if (!empty($option['image'])): ?>
                                    <div class="option-image">
                                        <img src="../assets/images/<?= htmlspecialchars($option['image']) ?>"
                                             alt="<?= htmlspecialchars($option['name']) ?>"
                                             onerror="this.style.display='none';">
                                    </div>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="submit-panel">
                <?php if($anyRequired): ?>
                    <p class="submit-note"><strong>Note:</strong> Required groups are marked with an asterisk.</p>
                <?php endif; ?>
                <button type="submit" class="btn-add-cart"><?= $editCartItem ? 'Save Changes' : 'Add to Cart' ?></button>
            </div>
        </form>

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
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password">👁</button></div>
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
                <div class="auth-modal-field password-field"><input type="password" name="password" placeholder="Password" required><button type="button" class="password-toggle" aria-label="Show password">👁</button></div>
                <div class="auth-modal-field password-field"><input type="password" name="confirm_password" placeholder="Confirm Password" required><button type="button" class="password-toggle" aria-label="Show password">👁</button></div>
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

    // Quantity adjuster
    function adjQty(delta) {
        const input = document.getElementById('quantity');
        const display = document.getElementById('qtyDisplay');
        let val = Math.min(99, Math.max(1, parseInt(input.value) + delta));
        input.value = val;
        display.textContent = val;
        updatePrice();
    }

    // Price update on modifier selection
    const basePrice = parseFloat(document.getElementById('basePrice').value);
    const displayPrice = document.getElementById('displayPrice');
    const form = document.querySelector('.customization-section');

    // Validation logic for Required groups
    form.addEventListener('submit', function(e) {
        const warningBox = document.getElementById('validation-warning');
        const requiredGroups = form.querySelectorAll('.customization-group[data-required="1"]');
        warningBox.style.display = 'none';

        for (let group of requiredGroups) {
            const selections = group.querySelectorAll('input:checked');
            if (selections.length === 0) {
                e.preventDefault();
                const groupName = group.getAttribute('data-group-name');
                warningBox.textContent = `Please select an option for: ${groupName}`;
                warningBox.style.display = 'block';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
        }
    });

    function updatePrice() {
        let extraChargeTotal = 0;
        let summaryHtml = '';
        const currentQuantity = parseInt(document.getElementById('quantity').value, 10) || 1;

        const checkedInputs = form.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked');

        checkedInputs.forEach(input => {
            const price = parseFloat(input.dataset.price) || 0;
            const optionLabel = input.closest('.option-card').querySelector('.option-name').textContent.trim();
            const groupContainer = input.closest('.customization-group');
            const groupName = groupContainer.getAttribute('data-group-name');

            extraChargeTotal += price;

            let priceText = '';
            if (price > 0) {
                const formattedPrice = '+₱' + price.toFixed(2);
                priceText = ` (${formattedPrice})`;
            }

            summaryHtml += `<div><strong>${groupName}:</strong> ${optionLabel}${priceText}</div>`;
        });

        const totalPrice = (basePrice + extraChargeTotal) * currentQuantity;

        const customizationSummary = document.getElementById('customizationSummary');
        if (summaryHtml) {
            customizationSummary.innerHTML = summaryHtml;
            customizationSummary.style.display = 'block';
        } else {
            customizationSummary.style.display = 'none';
        }

        const formattedPrice = new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2
        }).format(totalPrice);

        displayPrice.textContent = formattedPrice;
    }

    // Listen to all input changes
    form.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(input => {
        input.addEventListener('change', updatePrice);

        if (input.type === 'radio') {
            input.addEventListener('mousedown', function() {
                this.wasChecked = this.checked;
            });

            input.addEventListener('click', function() {
                const group = this.closest('.customization-group');
                const isRequired = group && group.getAttribute('data-required') === '1';

                if (!isRequired && this.wasChecked) {
                    this.checked = false;
                    this.dispatchEvent(new Event('change'));
                }
            });
        }
    });

    updatePrice();

    // ══════════════════════════════════════
    // MOBILE NAVIGATION DRAWER
    // ══════════════════════════════════════
    const navHamburger = document.getElementById('navHamburger');
    const navDrawer = document.getElementById('navDrawer');
    const navDrawerOverlay = document.getElementById('navDrawerOverlay');

    function openNavDrawer() {
        navHamburger.classList.add('open');
        navDrawer.classList.add('open');
        navDrawerOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }

    function closeNavDrawer() {
        navHamburger.classList.remove('open');
        navDrawer.classList.remove('open');
        navDrawerOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    }

    function toggleNavDrawer() {
        if (navDrawer.classList.contains('open')) {
            closeNavDrawer();
        } else {
            openNavDrawer();
        }
    }

    navHamburger.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleNavDrawer();
    });

    navDrawerOverlay.addEventListener('click', closeNavDrawer);

    // Close drawer on nav link click
    document.querySelectorAll('.nav-drawer a').forEach(link => {
        link.addEventListener('click', () => {
            closeNavDrawer();
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeNavDrawer();
            closeAuthModal();
        }
    });
</script>

<script src="search.js"></script>

<?php include_once '../includes/order_status_overlay.php'; ?>

</body>
</html>