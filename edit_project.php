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
    die('Unauthorized to edit this project.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error_msg = 'Project name is required.';
    } else {
        $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $project_id);
        if ($stmt->execute()) {
            $success_msg = 'Project updated successfully!';
            $project['name'] = $name;
            $project['description'] = $description;
        } else {
            $error_msg = 'Failed to update project.';
        }
    }
}

$page_title = 'Edit Project - ' . $project['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Edit Project</h1>
    <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-outline">Back to Board</a>
</div>

<div class="card" style="max-width:600px; margin: 0 auto; margin-top:24px;">
    <?php if ($error_msg): ?>
        <div style="color:#E53E5D; margin-bottom:16px;"><i class="ph ph-warning-circle"></i> <?php echo h($error_msg); ?></div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
        <div style="color:var(--primary); margin-bottom:16px;"><i class="ph ph-check-circle"></i> <?php echo h($success_msg); ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_project.php?id=<?php echo $project_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
        
        <div class="form-group">
            <label>Project Name</label>
            <input type="text" name="name" value="<?php echo h($project['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Project Description</label>
            <textarea name="description" rows="4"><?php echo h($project['description']); ?></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
            <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
