<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
requireAdmin();

$search = trim($_GET['search'] ?? '');
$error = '';
$success = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $get_name = mysqli_prepare($conn, "SELECT name FROM modifier_groups WHERE modifier_group_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $mod_name = $name_result['name'] ?? 'Unknown';

    $cleanup_customs = mysqli_prepare($conn, 
        "DELETE g, o FROM product_customization_groups g 
         LEFT JOIN product_customization_options o ON g.group_id = o.group_id
         JOIN product_modifier_groups pmg ON g.product_id = pmg.product_id AND g.display_order = pmg.display_order
         WHERE pmg.modifier_group_id = ?");
    mysqli_stmt_bind_param($cleanup_customs, 'i', $id);
    mysqli_stmt_execute($cleanup_customs);

    $delete_links = mysqli_prepare($conn, "DELETE FROM product_modifier_groups WHERE modifier_group_id = ?");
    mysqli_stmt_bind_param($delete_links, 'i', $id);
    mysqli_stmt_execute($delete_links);

    $stmt = mysqli_prepare($conn, "DELETE FROM modifier_groups WHERE modifier_group_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $admin_id = $_SESSION['user_id'] ?? null;
    $audit_stmt = mysqli_prepare($conn,
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
         VALUES (?, ?, ?, ?, ?)");
    $action_name = 'modifier_delete';
    $target_type = 'customization';
    $details = "Deleted customization: $mod_name";
    mysqli_stmt_bind_param($audit_stmt, 'issis', $admin_id, $action_name, $target_type, $id, $details);
    mysqli_stmt_execute($audit_stmt);

    header('Location: customizations.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = sanitize(trim($_POST['name'] ?? ''));
    $pricing_type = 'extra_charge';
    $select_option = $_POST['select_option'] === 'multiple' ? 'multiple' : 'single';

    if ($name === '') {
        $error = 'Modifier name is required.';
    } else {
        if ($action === 'add') {
            $stmt = mysqli_prepare($conn, "SELECT modifier_group_id FROM modifier_groups WHERE name = ?");
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'A modifier with that name already exists.';
            } else {
                $insert = mysqli_prepare($conn,
                    "INSERT INTO modifier_groups (name, pricing_type, select_option) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($insert, 'sss', $name, $pricing_type, $select_option);
                if (mysqli_stmt_execute($insert)) {
                    $modifier_id = mysqli_insert_id($conn);

                    $admin_id = $_SESSION['user_id'] ?? null;
                    $audit_stmt = mysqli_prepare($conn,
                        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
                         VALUES (?, ?, ?, ?, ?)");
                    $action_name = 'modifier_add';
                    $target_type = 'customization';
                    $details = "Added Customization: $name ($pricing_type, $select_option)";
                    mysqli_stmt_bind_param($audit_stmt, 'issis', $admin_id, $action_name, $target_type, $modifier_id, $details);
                    mysqli_stmt_execute($audit_stmt);

                    $success = 'Customization created successfully.';
                } else {
                    $error = 'Unable to save customization.';
                }
            }
        } elseif ($action === 'edit' && isset($_POST['modifier_group_id'])) {
            $modifier_group_id = (int)$_POST['modifier_group_id'];
            $stmt = mysqli_prepare($conn, "SELECT modifier_group_id FROM modifier_groups WHERE name = ? AND modifier_group_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $modifier_group_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'A customization with that name already exists.';
            } else {
                $update = mysqli_prepare($conn,
                    "UPDATE modifier_groups SET name = ?, pricing_type = ?, select_option = ? WHERE modifier_group_id = ?");
                mysqli_stmt_bind_param($update, 'sssi', $name, $pricing_type, $select_option, $modifier_group_id);
                if (mysqli_stmt_execute($update)) {
                    $admin_id = $_SESSION['user_id'] ?? null;
                    $audit_stmt = mysqli_prepare($conn,
                        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
                         VALUES (?, ?, ?, ?, ?)");
                    $action_name = 'modifier_edit';
                    $target_type = 'customization';
                    $details = "Updated Customization: $name ($pricing_type, $select_option)";
                    mysqli_stmt_bind_param($audit_stmt, 'issis', $admin_id, $action_name, $target_type, $modifier_group_id, $details);
                    mysqli_stmt_execute($audit_stmt);

                    $success = 'Customization updated successfully.';
                } else {
                    $error = 'Unable to update customization.';
                }
            }
        }
    }
}

$query = "SELECT * FROM modifier_groups";
$params = [];
if ($search !== '') {
    $query .= " WHERE name LIKE ?";
    $params[] = '%' . $search . '%';
}
$query .= " ORDER BY name";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $params[0]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$modifiers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $modifiers[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customizations — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="customizations.css">
    <style>
/* ══════════════════════════════════════
   HAMBURGER + COLLAPSIBLE SIDEBAR
══════════════════════════════════════ */
.hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 36px;
    height: 36px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    transition: background 0.2s;
    flex-shrink: 0;
}
.hamburger:hover { background: rgba(33,3,3,0.08); }
.hamburger span {
    display: block;
    height: 2px;
    background: #210303;
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform-origin: center;
    width: 100%;
}
.hamburger span:nth-child(2) { width: 70%; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 49;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.sidebar-overlay.visible {
    opacity: 1;
    pointer-events: all;
}

.sidebar {
    transition: transform 0.3s ease;
    will-change: transform;
}
.sidebar.collapsed { transform: translateX(-100%); }
.main { transition: margin-left 0.3s ease; }
.main.expanded { margin-left: 0 !important; }

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 50;
    }
    .sidebar.open { transform: translateX(0); }
    .main,
    .main.expanded { margin-left: 0 !important; }
    .topbar { padding: 0 16px; gap: 12px; }
    .topbar-title { font-size: 0.95rem; }
    .content { padding: 16px; }
    .top-bar { flex-direction: column; align-items: stretch; gap: 10px; }
    .top-bar-right { flex-wrap: wrap; }
    .folder-grid { grid-template-columns: 1fr !important; }
}
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="orders.php">Orders</a></li>
        <li><a href="menu.php">Menu</a></li>
        <li><a href="feature.php">Feature</a></li>
        <li><a href="customizations.php" class="active">Customizations</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit Log</a></li>
        <li><a href="analytics.php">Analytics</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main" id="main">
    <header class="topbar">
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="topbar-title">Customizations</div>
        <div class="topbar-spacer"></div>
        <div class="topbar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <div class="content">

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <div class="card top-bar">
            <div class="top-bar-left">
                <div class="top-bar-title">Manage Customizations</div>
            </div>
            <div class="top-bar-right">
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <input class="input-group" type="search" name="search" placeholder="Search Customizations" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($search !== ''): ?>
                        <a href="customizations.php" class="btn btn-gray">Clear</a>
                    <?php endif; ?>
                </form>
                <button type="button" class="btn btn-green" onclick="openCustomizationModal('add')">Add Customization</button>
            </div>
        </div>

        <?php if (count($modifiers) === 0): ?>
            <div class="empty-state">No customizations found. Use Add Customization to create one.</div>
        <?php else: ?>
            <div class="folder-grid">
                <?php foreach ($modifiers as $modifier): ?>
                    <div class="folder-card">
                        <div class="folder-heading">
                            <div>
                                <div class="folder-name"><?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="folder-meta">
                                    <?= $modifier['select_option'] === 'multiple' ? 'Multiple' : 'Single' ?> choice
                                </div>
                            </div>
                        </div>
                        <div class="folder-actions">
                            <button type="button" class="small-btn small-btn-blue"
                                onclick='openCustomizationModal("edit", <?= $modifier['modifier_group_id'] ?>, <?= json_encode($modifier['name']) ?>, <?= json_encode($modifier['pricing_type']) ?>, <?= json_encode($modifier['select_option']) ?>)'>
                                Edit
                            </button>
                            <a href="customizations.php?delete=<?= $modifier['modifier_group_id'] ?>"
                               class="small-btn small-btn-red"
                               onclick="return confirm('Are you sure you want to delete this customization? Deleting this group will also remove it from all menu items currently using it.')">
                                Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div><!-- .main -->

<!-- MODAL -->
<div class="modal-overlay" id="customizationModal">
    <div class="modal">
        <button type="button" class="modal-close" onclick="closeCustomizationModal()">✕</button>
        <h2 id="modalTitle">Add Customization</h2>
        <form method="POST">
            <input type="hidden" name="action" id="customAction" value="add">
            <input type="hidden" name="modifier_group_id" id="customGroupId" value="0">
            <div class="form-group">
                <label for="customName">Name</label>
                <input type="text" name="name" id="customName" required>
            </div>
            <div class="form-group">
                <label>Pricing Type</label>
                <input type="text" class="input-group" value="Extra Charge" readonly style="background:#eee;cursor:not-allowed;">
                <input type="hidden" name="pricing_type" value="extra_charge">
            </div>
            <div class="form-group">
                <label for="customSelect">Select Option</label>
                <select name="select_option" id="customSelect">
                    <option value="single">Single</option>
                    <option value="multiple">Multiple</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="closeCustomizationModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customization</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Customization Modal ── */
function openCustomizationModal(action, id = 0, name = '', pricing = 'extra_charge', selectOption = 'single') {
    document.getElementById('customizationModal').classList.add('active');
    document.getElementById('customAction').value = action;
    document.getElementById('customGroupId').value = id;
    document.getElementById('customName').value = name;
    document.getElementById('customSelect').value = selectOption;
    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit Customization' : 'Add Customization';
}
function closeCustomizationModal() {
    document.getElementById('customizationModal').classList.remove('active');
}

/* ══════════════════════════════════════
   HAMBURGER — all screen sizes
══════════════════════════════════════ */
const hamburgerBtn   = document.getElementById('hamburgerBtn');
const sidebar        = document.getElementById('sidebar');
const mainEl         = document.getElementById('main');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const isMobile = () => window.innerWidth <= 768;

function openSidebar() {
    hamburgerBtn.classList.add('open');
    if (isMobile()) {
        sidebar.classList.add('open');
        sidebar.classList.remove('collapsed');
        sidebarOverlay.classList.add('visible');
        document.body.style.overflow = 'hidden';
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
    }
    localStorage.setItem('sidebarOpen', '1');
}

function closeSidebar() {
    hamburgerBtn.classList.remove('open');
    if (isMobile()) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('visible');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('collapsed');
        mainEl.classList.add('expanded');
    }
    localStorage.setItem('sidebarOpen', '0');
}

function toggleSidebar() {
    const desktopOpen = !isMobile() && !sidebar.classList.contains('collapsed');
    const mobileOpen  =  isMobile() &&  sidebar.classList.contains('open');
    (desktopOpen || mobileOpen) ? closeSidebar() : openSidebar();
}

(function init() {
    const saved = localStorage.getItem('sidebarOpen');
    if (isMobile()) {
        sidebar.classList.remove('open');
        mainEl.classList.remove('expanded');
    } else {
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    }
})();

hamburgerBtn.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeSidebar();
        closeCustomizationModal();
    }
});

window.addEventListener('resize', () => {
    if (!isMobile()) {
        sidebarOverlay.classList.remove('visible');
        sidebar.classList.remove('open');
        document.body.style.overflow = '';
        const saved = localStorage.getItem('sidebarOpen');
        if (saved === '0') {
            sidebar.classList.add('collapsed');
            mainEl.classList.add('expanded');
            hamburgerBtn.classList.remove('open');
        } else {
            sidebar.classList.remove('collapsed');
            mainEl.classList.remove('expanded');
            hamburgerBtn.classList.add('open');
        }
    } else {
        sidebar.classList.remove('collapsed');
        mainEl.classList.remove('expanded');
        mainEl.style.marginLeft = '';
    }
});
</script>

</body>
</html>