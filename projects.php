<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

if (is_admin()) {
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.description, p.created_at,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS done_count
         FROM projects p
         WHERE p.deleted_at IS NULL
         ORDER BY p.created_at DESC"
    );
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.description, p.created_at,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS done_count
         FROM projects p
         INNER JOIN project_members pm ON pm.project_id = p.id
         WHERE pm.user_id = ? AND p.deleted_at IS NULL
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
$projects = $stmt->get_result();

if (is_api_request()) {
    echo json_encode([
        'success' => true,
        'projects' => $projects->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}


$page_title = 'Projects';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Your Projects</h1>
    <a href="create_project.php" class="btn btn-primary">+ New Project</a>
</div>

<?php if ($projects->num_rows === 0): ?>
    <div class="card empty-state">
        <svg width="200" height="150" viewBox="0 0 200 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="25" y="25" width="150" height="100" rx="12" fill="var(--column-bg)" stroke="var(--border-color)" stroke-width="2"/>
            <rect x="45" y="45" width="60" height="8" rx="4" fill="var(--border-color)"/>
            <rect x="45" y="65" width="110" height="8" rx="4" fill="var(--border-color)"/>
            <rect x="45" y="85" width="80" height="8" rx="4" fill="var(--border-color)"/>
            <circle cx="155" cy="49" r="10" fill="var(--primary)" opacity="0.8"/>
            <circle cx="155" cy="49" r="4" fill="#fff"/>
        </svg>
        <h3>No Projects Found</h3>
        <p>You haven't created or joined any projects. Create a new one to get started.</p>
        <a href="create_project.php" class="btn btn-primary">Create your first project</a>
    </div>
<?php else: ?>
    <div class="project-grid">
        <?php while ($project = $projects->fetch_assoc()): ?>
            <div class="project-card" style="position: relative;">
                <h3><a href="project.php?id=<?php echo (int)$project['id']; ?>"><?php echo h($project['name']); ?></a></h3>
                <p><?php echo h($project['description'] ?: 'No description provided.'); ?></p>
                <div class="project-meta" style="margin-bottom: 16px;">
                    <?php echo (int)$project['done_count']; ?> / <?php echo (int)$project['task_count']; ?> tasks done
                    &middot; created <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                </div>
                <a href="task_add.php?project_id=<?php echo (int)$project['id']; ?>" class="btn btn-outline" style="width:100%; justify-content:center;">+ Create Task</a>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

