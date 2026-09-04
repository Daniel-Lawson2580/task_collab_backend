<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

if (is_admin()) {
    $stmt = $conn->prepare(
        "SELECT t.*, p.name AS project_name, u.name AS assigned_name
         FROM tasks t
         JOIN projects p ON t.project_id = p.id
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE t.deleted_at IS NULL AND p.deleted_at IS NULL
         ORDER BY t.created_at DESC"
    );
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "SELECT t.*, p.name AS project_name, u.name AS assigned_name
         FROM tasks t
         JOIN projects p ON t.project_id = p.id
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE (t.assigned_to = ? OR t.created_by = ?) AND t.deleted_at IS NULL AND p.deleted_at IS NULL
         ORDER BY t.created_at DESC"
    );
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
}
$tasks = $stmt->get_result();

// Get projects for the dropdown
if (is_admin()) {
    $stmt = $conn->prepare("SELECT id, name FROM projects WHERE deleted_at IS NULL ORDER BY name ASC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id = p.id WHERE pm.user_id = ? AND p.deleted_at IS NULL ORDER BY p.name ASC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
$my_projects = $stmt->get_result();

$page_title = 'My Tasks';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>All Tasks</h1>
    <form action="task_add.php" method="GET" style="display:flex; gap:12px; align-items:center;">
        <select name="project_id" required style="padding:10px 16px; border-radius:var(--radius-md); border:1px solid var(--border-color); background:var(--card-bg); font-family:inherit; min-width:200px; color:var(--text-main);">
            <option value="">Choose Project...</option>
            <?php while ($p = $my_projects->fetch_assoc()): ?>
                <option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['name']); ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary">+ Create Task</button>
    </form>
</div>

<?php if ($tasks->num_rows === 0): ?>
    <div class="card empty-state">
        <svg width="200" height="150" viewBox="0 0 200 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="25" y="25" width="150" height="100" rx="12" fill="var(--column-bg)" stroke="var(--border-color)" stroke-width="2"/>
            <path d="M70 75L90 95L130 55" stroke="var(--primary)" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3>All Caught Up!</h3>
        <p>You don't have any tasks assigned to you right now. Enjoy the free time or create a new task.</p>
    </div>
<?php else: ?>
    <div class="project-grid">
        <?php while ($task = $tasks->fetch_assoc()): ?>
            <div class="task-card">
                <div class="task-tags">
                    <span class="tag tag-normal"><?php echo h($task['project_name']); ?></span>
                </div>
                <h4><a href="project.php?id=<?php echo (int)$task['project_id']; ?>"><?php echo h($task['title']); ?></a></h4>
                <div class="task-dates">
                    <div class="date-row">Status: <span><?php echo status_label($task['status']); ?></span></div>
                    <div class="date-row">Due: <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No date'; ?></span></div>
                </div>
                <div class="task-footer" style="border-top:none; padding-top:0;">
                    <div class="task-avatars">
                        <?php if ($task['assigned_name']): ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($task['assigned_name']); ?>&background=random" class="avatar" title="Assigned to <?php echo h($task['assigned_name']); ?>">
                        <?php else: ?>
                            <span style="font-size:0.8rem; color:var(--text-muted);">Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
