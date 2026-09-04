<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$project_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$error_msg = '';
$success_msg = '';

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project || ($project['owner_id'] != $user_id && !is_admin())) {
    die('Unauthorized to manage members for this project.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $new_user_id = (int)($_POST['user_id'] ?? 0);
        if ($new_user_id) {
            $stmt = $conn->prepare("INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $project_id, $new_user_id);
            if ($stmt->execute()) {
                $success_msg = 'Member added successfully.';
            } else {
                $error_msg = 'Failed to add member.';
            }
        }
    } elseif ($action === 'remove') {
        $remove_user_id = (int)($_POST['user_id'] ?? 0);
        if ($remove_user_id && $remove_user_id != $project['owner_id']) {
            $stmt = $conn->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $project_id, $remove_user_id);
            if ($stmt->execute()) {
                $success_msg = 'Member removed successfully.';
            } else {
                $error_msg = 'Failed to remove member.';
            }
        } else {
            $error_msg = 'Cannot remove the project owner.';
        }
    }
}

// Fetch current members
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, pm.joined_at 
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all users NOT in this project for the add dropdown
$stmt = $conn->prepare("
    SELECT id, name, email FROM users 
    WHERE id NOT IN (SELECT user_id FROM project_members WHERE project_id = ?)
    ORDER BY name
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$non_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Manage Members - ' . $project['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1>Manage Members</h1>
        <p>Manage who has access to <strong><?php echo h($project['name']); ?></strong>.</p>
    </div>
    <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-outline">Back to Board</a>
</div>

<div class="card" style="margin-top:24px;">
    <?php if ($error_msg): ?>
        <div style="background:var(--tag-high-bg); color:var(--tag-high-text); padding:16px; border-radius:var(--radius-md); margin-bottom:16px;">
            <i class="ph ph-warning-circle"></i> <?php echo h($error_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
        <div style="background:var(--tag-normal-bg); color:var(--tag-normal-text); padding:16px; border-radius:var(--radius-md); margin-bottom:16px;">
            <i class="ph ph-check-circle"></i> <?php echo h($success_msg); ?>
        </div>
    <?php endif; ?>

    <h2 style="font-size:1.1rem; margin-bottom:16px;">Add New Member</h2>
    <form method="POST" action="manage_members.php?id=<?php echo $project_id; ?>" style="display:flex; gap:12px; align-items:flex-end; margin-bottom:32px;">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="action" value="add">
        
        <div class="form-group" style="flex:1; margin:0;">
            <label>Select User</label>
            <select name="user_id" required style="width:100%; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-main); font-family:inherit;">
                <option value="">-- Choose a user --</option>
                <?php foreach ($non_members as $nm): ?>
                    <option value="<?php echo $nm['id']; ?>"><?php echo h($nm['name']); ?> (<?php echo h($nm['email']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Add Member</button>
    </form>

    <h2 style="font-size:1.1rem; margin-bottom:16px; padding-top:24px; border-top:1px solid var(--border-color);">Current Members (<?php echo count($members); ?>)</h2>
    <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($members as $mem): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:var(--bg-color); border-radius:var(--radius-sm);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($mem['name']); ?>&background=random" style="width:40px; height:40px; border-radius:50%;">
                    <div>
                        <div style="font-weight:600;"><?php echo h($mem['name']); ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo h($mem['email']); ?></div>
                    </div>
                </div>
                <div>
                    <?php if ($mem['id'] == $project['owner_id']): ?>
                        <span style="font-size:0.85rem; padding:4px 8px; background:var(--tag-normal-bg); color:var(--tag-normal-text); border-radius:4px; font-weight:600;">Project Owner</span>
                    <?php else: ?>
                        <form method="POST" action="manage_members.php?id=<?php echo $project_id; ?>" onsubmit="return confirm('Remove this user from the project?');">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="user_id" value="<?php echo $mem['id']; ?>">
                            <button type="submit" class="btn btn-outline" style="padding:6px 12px; font-size:0.85rem; border-color:#E53E5D; color:#E53E5D;">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
