<?php
require_once __DIR__ . '/includes/functions.php';
require_admin(); // Only admins can access this page

// Handle actions (promote/demote/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $target_id = (int)($_POST['user_id'] ?? 0);
    
    // Prevent self-demotion or self-deletion just in case
    if ($target_id === $_SESSION['user_id']) {
        $error = "You cannot modify your own administrator account.";
    } else {
        if ($action === 'promote') {
            $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->bind_param('i', $target_id);
            $stmt->execute();
            $success = "User promoted to Admin successfully.";
        } elseif ($action === 'demote') {
            $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmt->bind_param('i', $target_id);
            $stmt->execute();
            $success = "User demoted to regular User.";
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('i', $target_id);
            $stmt->execute();
            $success = "User account deleted.";
        }
    }
}

// Fetch all users
$result = $conn->query("SELECT id, name, email, role, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);

$page_title = 'User Management';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="project-title-area">
        <h1>User Management</h1>
        <p>Manage roles and access for all users in the system.</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead style="background: var(--bg-color); border-bottom: 1px solid var(--border-color);">
            <tr>
                <th style="padding: 16px; font-weight: 600; color: var(--text-muted);">Name</th>
                <th style="padding: 16px; font-weight: 600; color: var(--text-muted);">Email</th>
                <th style="padding: 16px; font-weight: 600; color: var(--text-muted);">Role</th>
                <th style="padding: 16px; font-weight: 600; color: var(--text-muted);">Joined</th>
                <th style="padding: 16px; font-weight: 600; color: var(--text-muted); text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 16px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['name']); ?>&background=random" style="width: 32px; height: 32px; border-radius: 50%;">
                            <span style="font-weight: 600; color: var(--text-main);"><?php echo h($u['name']); ?></span>
                            <?php if ((int)$u['id'] === $_SESSION['user_id']): ?>
                                <span class="tag tag-normal" style="font-size:0.65rem;">You</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding: 16px; color: var(--text-muted);"><?php echo h($u['email']); ?></td>
                    <td style="padding: 16px;">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="tag tag-premium">Admin</span>
                        <?php else: ?>
                            <span class="tag tag-website">User</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 16px; color: var(--text-muted);">
                        <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                    </td>
                    <td style="padding: 16px; text-align: right;">
                        <?php if ((int)$u['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline-block;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <button type="submit" name="action" value="demote" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 8px;">Demote</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="promote" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem; margin-right: 8px;">Make Admin</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: #E53E5D; border-color: #FDE8ED;" onclick="return confirm('Are you sure you want to delete this user completely?');">Delete</button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem; padding-right:12px;">No Actions</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
