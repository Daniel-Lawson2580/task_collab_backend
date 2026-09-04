<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$errors = [];

// Get all other users so the owner can add teammates to the project
$stmt_users = $conn->prepare("SELECT id, name, email FROM users WHERE id != ? ORDER BY name");
$stmt_users->bind_param('i', $user_id);
$stmt_users->execute();
$all_users = $stmt_users->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $member_ids = $_POST['members'] ?? [];

    if ($name === '') {
        $errors[] = 'Project name is required.';
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO projects (name, description, owner_id) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $name, $description, $user_id);
            $stmt->execute();
            $project_id = $conn->insert_id;

            // Add the owner as a member
            $stmt = $conn->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $project_id, $user_id);
            $stmt->execute();

            // Add selected teammates
            foreach ($member_ids as $member_id) {
                $member_id = (int)$member_id;
                if ($member_id > 0) {
                    $stmt = $conn->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
                    $stmt->bind_param('ii', $project_id, $member_id);
                    $stmt->execute();
                }
            }

            $conn->commit();

            $actor_name = $_SESSION['user_name'];
            $notif_msg = "{$actor_name} created a new project: " . mb_substr($name, 0, 40);
            $notif_link = "/task_collab_system/project.php?id=" . $project_id;
            log_activity_notification($conn, $project_id, $user_id, $notif_msg, $notif_link);

            redirect('/task_collab_system/project.php?id=' . $project_id);
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Could not create project. Please try again.';
        }
    }
}

$page_title = 'New Project';
require __DIR__ . '/includes/header.php';
?>

<h1>Create a New Project</h1>

<div class="card" style="max-width:560px;">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endforeach; ?>

    <form method="POST" action="create_project.php">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="name">Project name</label>
            <input type="text" id="name" name="name" value="<?php echo h($_POST['name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?php echo h($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="members">Add teammates (optional)</label>
            <select id="members" name="members[]" multiple size="5">
                <?php while ($u = $all_users->fetch_assoc()): ?>
                    <option value="<?php echo (int)$u['id']; ?>"><?php echo h($u['name']) . ' (' . h($u['email']) . ')'; ?></option>
                <?php endwhile; ?>
            </select>
            <small style="color:#6B6D80;">Hold Ctrl (or Cmd) to select multiple people.</small>
        </div>
        <button type="submit" class="btn btn-primary">Create Project</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
