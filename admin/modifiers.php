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
    $stmt = mysqli_prepare($conn, "DELETE FROM modifier_groups WHERE modifier_group_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
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
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --crimson: #210303;
    --gold: #e8d191;
    --ink: #130301;
    --surface: #fff8eb;
    --bg: #f4f2ea;
    --line: rgba(33,3,3,.1);
    --radius: 14px;
    --shadow: 0 2px 18px rgba(33,3,3,.08);
    --sidebar-w: 220px;
    --header-h: 64px;
}
body { margin: 0; font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; display: flex; }
.sidebar { width: var(--sidebar-w); background: var(--crimson); min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; }
.sidebar-logo { padding: 22px 20px 18px; border-bottom: 1px solid rgba(255,255,255,.12); }
.sidebar-logo .brand { font-family: 'Cinzel Decorative', serif; font-size: 17px; color: #fff; letter-spacing: .08em; text-transform: uppercase; }
.nav-list { list-style: none; padding: 16px 12px; margin: 0; flex: 1; }
.nav-list li { margin-bottom: 4px; }
.nav-list a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: rgba(255,255,255,.75); font-size: 14px; font-weight: 500; }
.nav-list a.active, .nav-list a:hover { background: rgba(255,255,255,.14); color: #fff; }
.nav-list a .icon { width: 20px; text-align: center; }
.sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,.12); }
.sidebar-footer a { color: rgba(255,255,255,.65); text-decoration: none; display: flex; align-items: center; gap: 10px; }
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { height: var(--header-h); background: var(--surface); border-bottom: 1px solid var(--line); display: flex; align-items: center; padding: 0 28px; gap: 16px; position: sticky; top: 0; z-index: 5; }
.topbar-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--crimson); }
.topbar-spacer { flex: 1; }
.topbar-user { display: flex; align-items: center; gap: 10px; color: var(--ink); font-size: 14px; }
.avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; background: var(--crimson); font-weight: 700; }
.content { padding: 24px 28px; display: flex; flex-direction: column; gap: 20px; }
.card { background: var(--surface); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
.top-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; border: none; padding: 12px 18px; font-weight: 700; text-decoration: none; cursor: pointer; }
.btn-blue { background: #3498db; color: #fff; }
.btn-green { background: #27ae60; color: #fff; }
.btn-gray { background: #6b7280; color: #fff; }
.btn-red { background: #e74c3c; color: #fff; }
.input-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.input-group input { padding: 12px 14px; border-radius: 12px; border: 1px solid #d6d2d9; min-width: 240px; }
.folder-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap:18px; }
.folder-card { background:#fff; border-radius:20px; padding:22px; box-shadow:var(--shadow); display:flex; flex-direction:column; gap:16px; }
.folder-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.folder-name { font-size:18px; font-weight:700; }
.folder-meta { color:#6b7280; font-size:14px; }
.folder-actions { display:flex; gap:10px; flex-wrap:wrap; }
.small-btn { padding:8px 12px; border-radius:10px; border:none; cursor:pointer; font-size:13px; }
.small-btn-blue { background:#3498db; color:#fff; }
.small-btn-red { background:#e74c3c; color:#fff; }
.small-btn-gray { background:#f4f4f4; color:#333; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; padding:20px; z-index:20; }
.modal-overlay.active { display:flex; }
.modal { width:100%; max-width:520px; background:#fff; border-radius:18px; padding:24px; position:relative; }
.modal h2 { margin:0 0 18px; font-family:'Cinzel Decorative',serif; font-size:1.75rem; }
.modal .form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.modal label { font-weight:600; }
.modal input, .modal select { width:100%; border:1px solid #d6d2d9; border-radius:12px; padding:12px 14px; }
.modal-close { position:absolute; top:18px; right:18px; background:#f4f4f4; border:none; width:34px; height:34px; border-radius:50%; cursor:pointer; }
.alert { padding:14px 16px; border-radius:14px; }
.alert-error { background:#fee2e2; color:#981b1b; }
.alert-success { background:#d1fae5; color:#065f46; }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="brand">Casa Gunita</div>
    </div>
    <ul class="nav-list">
        <li><a href="index.php"><span class="icon">🏠</span> Dashboard</a></li>
        <li><a href="orders.php"><span class="icon">📋</span> Orders</a></li>
        <li><a href="products.php"><span class="icon">🍽️</span> Menu</a></li>
        <li><a href="modifiers.php" class="active"><span class="icon">🧂</span> Modifiers</a></li>
        <li><a href="customers.php"><span class="icon">🧑‍🤝‍🧑</span> Customers</a></li>
        <li><a href="audit.php"><span class="icon">📜</span> Audit</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php"><span class="icon">🚪</span> Logout</a>
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
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <div class="card top-bar">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;"><div style="font-weight:700;font-size:1.05rem;">Manage Modifiers</div></div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;"><button type="button" class="btn btn-green" onclick="openModifierModal('add')">+ Add Modifier</button></div>
        </div>
        <div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <form method="GET" class="input-group" style="margin:0;"><input type="search" name="search" placeholder="Search modifiers" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-blue">Search</button></form>
            <?php if ($search !== ''): ?><a href="modifiers.php" class="btn btn-gray">Clear</a><?php endif; ?>
        </div>
        <?php if (count($modifiers) === 0): ?>
            <div class="card" style="text-align:center;color:#777;padding:50px 0;">No modifiers found. Use Add Modifier to create one.</div>
        <?php else: ?>
            <div class="card folder-grid">
                <?php foreach ($modifiers as $modifier): ?>
                    <div class="folder-card">
                        <div class="folder-heading">
                            <div>
                                <div class="folder-name"><?= htmlspecialchars($modifier['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="folder-meta"><?= $modifier['pricing_type'] === 'extra_charge' ? 'Extra charge' : 'Set price' ?> · <?= $modifier['select_option'] === 'multiple' ? 'Multiple' : 'Single' ?> choice</div>
                            </div>
                        </div>
                        <div class="folder-actions">
                            <button type="button" class="small-btn small-btn-blue" onclick='openModifierModal("edit", <?= $modifier['modifier_group_id'] ?>, <?= json_encode($modifier['name']) ?>, <?= json_encode($modifier['pricing_type']) ?>, <?= json_encode($modifier['select_option']) ?>)'>Edit</button>
                            <a href="modifiers.php?delete=<?= $modifier['modifier_group_id'] ?>" class="small-btn small-btn-red" onclick="return confirm('Delete this modifier?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal-overlay" id="modifierModal">
    <div class="modal">
        <button type="button" class="modal-close" onclick="closeModifierModal()">✕</button>
        <h2 id="modalTitle">Add Modifier</h2>
        <form method="POST">
            <input type="hidden" name="action" id="modifierAction" value="add">
            <input type="hidden" name="modifier_group_id" id="modifierGroupId" value="0">
            <div class="form-group"><label for="modifierName">Modifier Name</label><input type="text" name="name" id="modifierName" required></div>
            <div class="form-group"><label for="modifierPricing">Pricing Type</label><select name="pricing_type" id="modifierPricing"><option value="set_price">Set price</option><option value="extra_charge">Extra charge</option></select></div>
            <div class="form-group"><label for="modifierSelect">Select Option</label><select name="select_option" id="modifierSelect"><option value="single">Single</option><option value="multiple">Multiple</option></select></div>
            <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-top:10px;"><button type="button" class="btn btn-gray" onclick="closeModifierModal()">Cancel</button><button type="submit" class="btn btn-green">Save Modifier</button></div>
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
function closeModifierModal() { document.getElementById('modifierModal').classList.remove('active'); }
window.addEventListener('keydown', function(event) { if (event.key === 'Escape') closeModifierModal(); });
</script>
</body>
</html>
