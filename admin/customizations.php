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
    $get_name = mysqli_prepare($conn, "SELECT name FROM customization_templates WHERE template_id = ?");
    mysqli_stmt_bind_param($get_name, 'i', $id);
    mysqli_stmt_execute($get_name);
    $name_result = mysqli_fetch_assoc(mysqli_stmt_get_result($get_name));
    $mod_name = $name_result['name'] ?? 'Unknown';

    // Remove the links in the intermediate table (renamed to product_template_links)
    $delete_links = mysqli_prepare($conn, "DELETE FROM product_template_links WHERE template_id = ?");
    mysqli_stmt_bind_param($delete_links, 'i', $id);
    mysqli_stmt_execute($delete_links);

    $stmt = mysqli_prepare($conn, "DELETE FROM customization_templates WHERE template_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    logAudit($conn, 'customization_delete', 'customization', $id, "Deleted customization: $mod_name");

    header('Location: customizations.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = sanitize(trim($_POST['name'] ?? ''));
    $select_option = $_POST['select_option'] === 'multiple' ? 'multiple' : 'single';

    if ($name === '') {
        $error = 'Modifier name is required.';
    } else {
        if ($action === 'add') {
            $stmt = mysqli_prepare($conn, "SELECT template_id FROM customization_templates WHERE name = ?");
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'A modifier with that name already exists.';
            } else {
                $insert = mysqli_prepare($conn, "INSERT INTO customization_templates (name, select_option) VALUES (?, ?)");
                mysqli_stmt_bind_param($insert, 'ss', $name, $select_option);
                if (mysqli_stmt_execute($insert)) {
                    $id = mysqli_insert_id($conn);
                    logAudit($conn, 'customization_add', 'customization', $id, "Added Customization: $name ($select_option)");
                    $success = 'Customization created successfully.';
                } else {
                    $error = 'Unable to save customization.';
                }
            }
        } elseif ($action === 'edit' && isset($_POST['template_id'])) {
            $template_id = (int)$_POST['template_id'];
            $stmt = mysqli_prepare($conn, "SELECT template_id FROM customization_templates WHERE name = ? AND template_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $template_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'A customization with that name already exists.';
            } else {
                $update = mysqli_prepare($conn, "UPDATE customization_templates SET name = ?, select_option = ? WHERE template_id = ?");
                mysqli_stmt_bind_param($update, 'ssi', $name, $select_option, $template_id);
                if (mysqli_stmt_execute($update)) {
                    logAudit($conn, 'customization_edit', 'customization', $template_id, "Updated Customization: $name ($select_option)");
                    $success = 'Customization updated successfully.';
                } else {
                    $error = 'Unable to update customization.';
                }
            }
        }
    }
}

$query = "SELECT * FROM customization_templates";
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
</head>
<body>

<aside class="sidebar">
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
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
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
</div>

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
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCustomizationModal();
});
</script>
</body>
</html>