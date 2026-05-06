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

    $stmt = mysqli_prepare($conn, "DELETE FROM modifier_groups WHERE modifier_group_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $admin_id = $_SESSION['user_id'] ?? null;
    $audit_stmt = mysqli_prepare($conn,
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
         VALUES (?, 'modifier_delete', 'modifier', ?, ?)");
    $details = "Deleted modifier: $mod_name";
    mysqli_stmt_bind_param($audit_stmt, 'iis', $admin_id, $id, $details);
    mysqli_stmt_execute($audit_stmt);

    header('Location: modifiers.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $name = sanitize(trim($_POST['name'] ?? ''));
    $pricing_type = $_POST['pricing_type'] === 'extra_charge' ? 'extra_charge' : 'set_price';
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
                         VALUES (?, 'modifier_add', 'modifier', ?, ?)");
                    $details = "Added modifier: $name ($pricing_type, $select_option)";
                    mysqli_stmt_bind_param($audit_stmt, 'iis', $admin_id, $modifier_id, $details);
                    mysqli_stmt_execute($audit_stmt);

                    $success = 'Modifier created successfully.';
                } else {
                    $error = 'Unable to save modifier.';
                }
            }
        } elseif ($action === 'edit' && isset($_POST['modifier_group_id'])) {
            $modifier_group_id = (int)$_POST['modifier_group_id'];
            $stmt = mysqli_prepare($conn, "SELECT modifier_group_id FROM modifier_groups WHERE name = ? AND modifier_group_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $modifier_group_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = 'A modifier with that name already exists.';
            } else {
                $update = mysqli_prepare($conn,
                    "UPDATE modifier_groups SET name = ?, pricing_type = ?, select_option = ? WHERE modifier_group_id = ?");
                mysqli_stmt_bind_param($update, 'sssi', $name, $pricing_type, $select_option, $modifier_group_id);
                if (mysqli_stmt_execute($update)) {
                    $admin_id = $_SESSION['user_id'] ?? null;
                    $audit_stmt = mysqli_prepare($conn,
                        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, details)
                         VALUES (?, 'modifier_edit', 'modifier', ?, ?)");
                    $details = "Updated modifier: $name ($pricing_type, $select_option)";
                    mysqli_stmt_bind_param($audit_stmt, 'iis', $admin_id, $modifier_group_id, $details);
                    mysqli_stmt_execute($audit_stmt);

                    $success = 'Modifier updated successfully.';
                } else {
                    $error = 'Unable to update modifier.';
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
    <title>Modifiers — Casa Gunita Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="modifiers.css">
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
        <li><a href="modifiers.php" class="active">Modifiers</a></li>
        <li><a href="customers.php">Customers</a></li>
        <li><a href="audit.php">Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php">Logout</a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div class="topbar-title">Modifiers</div>
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
                <div class="top-bar-title">Manage Modifiers</div>
            </div>
            <div class="top-bar-right">
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <input class="input-group" type="search" name="search" placeholder="Search modifiers…" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="modifiers.php" class="btn btn-gray">Clear</a>
                    <?php endif; ?>
                </form>
                <button type="button" class="btn btn-green" onclick="openModifierModal('add')">+ Add Modifier</button>
            </div>
        </div>

        <?php if (count($modifiers) === 0): ?>
            <div class="empty-state">No modifiers found. Use Add Modifier to create one.</div>
        <?php else: ?>
            <div class="folder-grid">
                <?php foreach ($modifiers as $modifier): ?>
                    <div class="folder-card">
                        <div class="folder-heading">
                            <div>
                                <div class="folder-name"><?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="folder-meta">
                                    <?= $modifier['pricing_type'] === 'extra_charge' ? 'Extra charge' : 'Set price' ?>
                                    &middot;
                                    <?= $modifier['select_option'] === 'multiple' ? 'Multiple' : 'Single' ?> choice
                                </div>
                            </div>
                        </div>
                        <div class="folder-actions">
                            <button type="button" class="small-btn small-btn-blue"
                                onclick='openModifierModal("edit", <?= $modifier['modifier_group_id'] ?>, <?= json_encode($modifier['name']) ?>, <?= json_encode($modifier['pricing_type']) ?>, <?= json_encode($modifier['select_option']) ?>)'>
                                Edit
                            </button>
                            <a href="modifiers.php?delete=<?= $modifier['modifier_group_id'] ?>"
                               class="small-btn small-btn-red"
                               onclick="return confirm('Delete this modifier?')">
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
<div class="modal-overlay" id="modifierModal">
    <div class="modal">
        <button type="button" class="modal-close" onclick="closeModifierModal()">✕</button>
        <h2 id="modalTitle">Add Modifier</h2>
        <form method="POST">
            <input type="hidden" name="action" id="modifierAction" value="add">
            <input type="hidden" name="modifier_group_id" id="modifierGroupId" value="0">
            <div class="form-group">
                <label for="modifierName">Modifier Name</label>
                <input type="text" name="name" id="modifierName" required>
            </div>
            <div class="form-group">
                <label for="modifierPricing">Pricing Type</label>
                <select name="pricing_type" id="modifierPricing">
                    <option value="set_price">Set price</option>
                    <option value="extra_charge">Extra charge</option>
                </select>
            </div>
            <div class="form-group">
                <label for="modifierSelect">Select Option</label>
                <select name="select_option" id="modifierSelect">
                    <option value="single">Single</option>
                    <option value="multiple">Multiple</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="closeModifierModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Modifier</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModifierModal(action, id = 0, name = '', pricing = 'set_price', selectOption = 'single') {
    document.getElementById('modifierModal').classList.add('active');
    document.getElementById('modifierAction').value = action;
    document.getElementById('modifierGroupId').value = id;
    document.getElementById('modifierName').value = name;
    document.getElementById('modifierPricing').value = pricing;
    document.getElementById('modifierSelect').value = selectOption;
    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit Modifier' : 'Add Modifier';
}
function closeModifierModal() {
    document.getElementById('modifierModal').classList.remove('active');
}
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModifierModal();
});
</script>
</body>
</html>