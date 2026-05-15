<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireCustomer();

$user_id = $_SESSION['user_id'];

/* ── Fetch user info ── */
$stmt = mysqli_prepare($conn,
    "SELECT full_name, email, password FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

$full_name  = htmlspecialchars($user['full_name'] ?? 'User',     ENT_QUOTES, 'UTF-8');
$email      = htmlspecialchars($user['email']     ?? '',          ENT_QUOTES, 'UTF-8');
$password   = htmlspecialchars($user['password']  ?? '',          ENT_QUOTES, 'UTF-8');
$first_name = htmlspecialchars(explode(' ', trim($user['full_name'] ?? 'User'))[0], ENT_QUOTES, 'UTF-8');
$initial    = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Information — Casa Gunita</title>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Cinzel:wght@400;600&family=EB+Garamond:wght@400;500&family=Public+Sans:wght@300;400;500;600&family=Noto+Sans+Tagalog&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="landingpage.css">
    <link rel="stylesheet" href="account.css">
</head>
<body>

<!-- ═══════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════ -->
<nav class="navbar">
    <a href="index.php" class="nav-logo">
        <img src="casalogo.png" alt="Casa Gunita Logo">
    </a>

    <div class="nav-search-wrap">
        <input type="text" class="nav-search" placeholder="Search menu…" id="navSearch">
        <div class="search-results-dropdown" id="searchResults"></div>
    </div>

    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="index.php#about">About</a>
    </div>

    <div class="nav-icons">
        <a href="cart.php" class="nav-icon-btn" aria-label="Cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <?php if (isset($_SESSION['cart']) && getCartItemCount($_SESSION['cart']) > 0): ?>
                <span class="cart-badge"><?= getCartItemCount($_SESSION['cart']) ?></span>
            <?php endif; ?>
        </a>

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

<!-- ═══════════════════════════════════════════
     PAGE WRAPPER
════════════════════════════════════════════ -->
<main class="acct-page">
    <div class="acct-layout">

        <!-- ── SIDEBAR ── -->
        <aside class="acct-sidebar">
            <div class="acct-sidebar-top">
                <div class="acct-avatar"><?= $initial ?></div>
                <div class="acct-avatar-info">
                    <strong><?= $first_name ?></strong>
                </div>
            </div>

           <nav class="acct-sidebar-nav">
    <a href="account.php" class="acct-nav-link active">
        <svg viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        Account Information
    </a>
</nav>
        </aside>

        <!-- ── CONTENT ── -->
        <section class="acct-content">

            <div class="acct-content-header">
                <h1>Account Information</h1>
                <p>Your personal details are shown below.</p>
            </div>

            <!-- Personal Info Card -->
            <div class="acct-card">
                <div class="acct-card-head">
                    <h2>Personal Information</h2>
                </div>
                <div class="acct-card-body">

                    <!-- Full Name (read-only) -->
                    <div class="acct-field-group">
                        <label>Full Name</label>
                        <input type="text" value="<?= $full_name ?>" readonly>
                    </div>

                    <div class="acct-field-divider"></div>

                    <!-- Email (read-only) -->
                    <div class="acct-field-group">
                        <label>Email Address</label>
                        <input type="email" value="<?= $email ?>" readonly>
                    </div>

                    <div class="acct-field-divider"></div>

                    <!-- Current Password with show/hide -->
                    <div class="acct-field-group">
                        <label>Password</label>
                        <div class="acct-pw-wrap">
                            <input type="password" id="pwField"
                                   value="<?= $password ?>" readonly
                                   autocomplete="off">
                            <button type="button" class="acct-pw-toggle"
                                    id="pwToggle" aria-label="Toggle password visibility">
                                <svg id="eyeOpen" viewBox="0 0 24 24">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg id="eyeClosed" viewBox="0 0 24 24" style="display:none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                                             a18.45 18.45 0 0 1 5.06-5.94"/>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                                             a18.5 18.5 0 0 1-2.16 3.19"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Change Password Button -->
                    <div class="acct-field-divider"></div>
                    <div class="acct-field-group">
                        <button type="button" class="acct-change-pw-btn" id="changePwBtn">
                            Change Password
                        </button>
                    </div>

                    <!-- ── Hidden: New + Retype fields ── -->
                    <!-- FIX: Added display:flex + flex-direction:column + gap:18px -->
                    <!-- so inner field-groups and dividers space out correctly,     -->
                    <!-- matching the same rhythm as the rest of acct-card-body.     -->
                    <div id="changePwSection"
                         style="display:none; flex-direction:column; gap:18px;">

                        <div class="acct-field-divider"></div>

                        <!-- New Password -->
                        <div class="acct-field-group">
                            <label>New Password</label>
                            <div class="acct-pw-wrap">
                                <input type="password" id="newPwField"
                                       placeholder="Enter new password"
                                       autocomplete="new-password">
                                <button type="button" class="acct-pw-toggle"
                                        id="newPwToggle" aria-label="Toggle">
                                    <svg id="eyeOpenNew" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg id="eyeClosedNew" viewBox="0 0 24 24" style="display:none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                                                 a18.45 18.45 0 0 1 5.06-5.94"/>
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                                                 a18.5 18.5 0 0 1-2.16 3.19"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="acct-field-divider"></div>

                        <!-- Retype New Password -->
                        <div class="acct-field-group">
                            <label>Retype New Password</label>
                            <div class="acct-pw-wrap">
                                <input type="password" id="retypePwField"
                                       placeholder="Retype new password"
                                       autocomplete="new-password">
                                <button type="button" class="acct-pw-toggle"
                                        id="retypePwToggle" aria-label="Toggle">
                                    <svg id="eyeOpenRetype" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg id="eyeClosedRetype" viewBox="0 0 24 24" style="display:none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                                                 a18.45 18.45 0 0 1 5.06-5.94"/>
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                                                 a18.5 18.5 0 0 1-2.16 3.19"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                            <span id="pwMatchMsg"
                                  style="font-size:0.8rem; margin-top:4px; display:none;"></span>
                        </div>

                        <div class="acct-field-divider"></div>

                        <!-- Save / Cancel -->
                        <div class="acct-pw-actions">
                            <button type="button" class="acct-save-pw-btn" id="savePwBtn">
                                Save Password
                            </button>
                            <button type="button" class="acct-cancel-pw-btn" id="cancelPwBtn">
                                Cancel
                            </button>
                        </div>

                    </div><!-- /changePwSection -->

                </div>
            </div>

        </section>
    </div>
</main>

<script src="search.js"></script>
<script>
/* ── Account dropdown ── */
const accountBtn      = document.getElementById('accountBtn');
const accountDropdown = document.getElementById('accountDropdown');
if (accountBtn && accountDropdown) {
    accountBtn.addEventListener('click', e => {
        e.stopPropagation();
        accountDropdown.classList.toggle('open');
    });
    document.addEventListener('click', () => accountDropdown.classList.remove('open'));
}

/* ── Current password show/hide ── */
const pwField   = document.getElementById('pwField');
const pwToggle  = document.getElementById('pwToggle');
const eyeOpen   = document.getElementById('eyeOpen');
const eyeClosed = document.getElementById('eyeClosed');

if (pwToggle) {
    pwToggle.addEventListener('click', () => {
        const isHidden = pwField.type === 'password';
        pwField.type    = isHidden ? 'text' : 'password';
        eyeOpen.style.display   = isHidden ? 'none' : '';
        eyeClosed.style.display = isHidden ? '' : 'none';
    });
}

/* ── Change Password section ── */
const changePwBtn     = document.getElementById('changePwBtn');
const changePwSection = document.getElementById('changePwSection');
const cancelPwBtn     = document.getElementById('cancelPwBtn');
const savePwBtn       = document.getElementById('savePwBtn');
const newPwField      = document.getElementById('newPwField');
const retypePwField   = document.getElementById('retypePwField');
const pwMatchMsg      = document.getElementById('pwMatchMsg');

// Show change section — use 'flex' so gap applies correctly
changePwBtn.addEventListener('click', () => {
    changePwSection.style.display = 'flex';
    changePwBtn.style.display     = 'none';
    newPwField.focus();
});

// Cancel — reset everything
cancelPwBtn.addEventListener('click', () => {
    changePwSection.style.display = 'none';
    changePwBtn.style.display     = '';
    newPwField.value              = '';
    retypePwField.value           = '';
    pwMatchMsg.style.display      = 'none';
});

// Live match check
retypePwField.addEventListener('input', () => {
    if (retypePwField.value === '') {
        pwMatchMsg.style.display = 'none';
        return;
    }
    if (newPwField.value === retypePwField.value) {
        pwMatchMsg.textContent = '✓ Passwords match';
        pwMatchMsg.style.color = '#4caf70';
    } else {
        pwMatchMsg.textContent = '✗ Passwords do not match';
        pwMatchMsg.style.color = '#e05c5c';
    }
    pwMatchMsg.style.display = 'block';
});

// Save password via AJAX
savePwBtn.addEventListener('click', () => {
    if (!newPwField.value) {
        alert('Please enter a new password.');
        return;
    }
    if (newPwField.value !== retypePwField.value) {
        alert('Passwords do not match. Please try again.');
        return;
    }

    fetch('update_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ new_password: newPwField.value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Password updated successfully!');
            pwField.value = newPwField.value;
            cancelPwBtn.click();
        } else {
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(() => alert('Error connecting to server.'));
});

// New password show/hide
document.getElementById('newPwToggle').addEventListener('click', () => {
    const isHidden = newPwField.type === 'password';
    newPwField.type = isHidden ? 'text' : 'password';
    document.getElementById('eyeOpenNew').style.display   = isHidden ? 'none' : '';
    document.getElementById('eyeClosedNew').style.display = isHidden ? '' : 'none';
});

// Retype password show/hide
document.getElementById('retypePwToggle').addEventListener('click', () => {
    const isHidden = retypePwField.type === 'password';
    retypePwField.type = isHidden ? 'text' : 'password';
    document.getElementById('eyeOpenRetype').style.display   = isHidden ? 'none' : '';
    document.getElementById('eyeClosedRetype').style.display = isHidden ? '' : 'none';
});
</script>

</body>
</html>