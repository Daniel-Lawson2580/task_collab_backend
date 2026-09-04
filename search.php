<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');
$like_query = '%' . $query . '%';

// Search Projects
if (is_admin()) {
    $stmt_proj = $conn->prepare(
        "SELECT p.id, p.name, p.description, p.created_at,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
         FROM projects p
         WHERE p.name LIKE ? OR p.description LIKE ?
         ORDER BY p.created_at DESC"
    );
    $stmt_proj->bind_param('ss', $like_query, $like_query);
    $stmt_proj->execute();
} else {
    $stmt_proj = $conn->prepare(
        "SELECT p.id, p.name, p.description, p.created_at,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
         FROM projects p
         JOIN project_members pm ON pm.project_id = p.id
         WHERE pm.user_id = ? 
           AND (p.name LIKE ? OR p.description LIKE ?)
         ORDER BY p.created_at DESC"
    );
    $stmt_proj->bind_param('iss', $user_id, $like_query, $like_query);
    $stmt_proj->execute();
}
$projects = $stmt_proj->get_result();

// Search Tasks
if (is_admin()) {
    $stmt_task = $conn->prepare(
        "SELECT t.*, p.name AS project_name, u.name AS assigned_name
         FROM tasks t
         JOIN projects p ON t.project_id = p.id
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE (t.title LIKE ? OR t.description LIKE ?)
         ORDER BY t.created_at DESC"
    );
    $stmt_task->bind_param('ss', $like_query, $like_query);
    $stmt_task->execute();
} else {
    $stmt_task = $conn->prepare(
        "SELECT t.*, p.name AS project_name, u.name AS assigned_name
         FROM tasks t
         JOIN projects p ON t.project_id = p.id
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE (t.assigned_to = ? OR t.created_by = ?) 
           AND (t.title LIKE ? OR t.description LIKE ?)
         ORDER BY t.created_at DESC"
    );
    $stmt_task->bind_param('iiss', $user_id, $user_id, $like_query, $like_query);
    $stmt_task->execute();
}
$tasks = $stmt_task->get_result();

$page_title = 'Search Results';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Search Results for "<?php echo h($query); ?>"</h1>
</div>

<?php if ($query === ''): ?>
    <div class="card empty-state">
        <svg width="150" height="150" viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="65" cy="65" r="40" stroke="var(--primary)" stroke-width="12"/>
            <path d="M95 95L130 130" stroke="var(--primary)" stroke-width="12" stroke-linecap="round"/>
        </svg>
        <h3>Start Searching</h3>
        <p>Please enter a search term in the search bar above to find projects and tasks.</p>
    </div>
<?php elseif ($projects->num_rows === 0 && $tasks->num_rows === 0): ?>
    <div class="card empty-state">
        <svg width="150" height="150" viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="65" cy="65" r="40" stroke="var(--border-color)" stroke-width="12" stroke-dasharray="8 8"/>
            <path d="M95 95L130 130" stroke="var(--border-color)" stroke-width="12" stroke-linecap="round"/>
            <path d="M50 50L80 80M80 50L50 80" stroke="var(--primary)" stroke-width="8" stroke-linecap="round"/>
        </svg>
        <h3>No Results Found</h3>
        <p>We couldn't find anything matching "<?php echo h($query); ?>". Try adjusting your search terms.</p>
    </div>
<?php else: ?>

    <!-- Projects Results -->
    <?php if ($projects->num_rows > 0): ?>
        <h2 style="margin-bottom:16px; font-size:1.25rem;">Projects (<?php echo $projects->num_rows; ?>)</h2>
        <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:32px;">
            <?php while ($project = $projects->fetch_assoc()): ?>
                <div class="project-card">
                    <h3><a href="project.php?id=<?php echo (int)$project['id']; ?>"><?php echo h($project['name']); ?></a></h3>
                    <p><?php echo h($project['description'] ?: 'No description provided.'); ?></p>
                    <div class="project-meta">
                        <?php echo (int)$project['done_count']; ?> / <?php echo (int)$project['task_count']; ?> tasks done
                        &middot; created <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <!-- Tasks Results -->
    <?php if ($tasks->num_rows > 0): ?>
        <h2 style="margin-bottom:16px; font-size:1.25rem;">Tasks (<?php echo $tasks->num_rows; ?>)</h2>
        <div style="display:flex; flex-direction:column; gap:16px;">
            <?php while ($task = $tasks->fetch_assoc()): ?>
                <div class="task-card">
                    <div class="task-tags">
                        <span class="tag tag-normal"><?php echo h($task['project_name']); ?></span>
                    </div>
                    <h4><a href="task_view.php?id=<?php echo (int)$task['id']; ?>"><?php echo h($task['title']); ?></a></h4>
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

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
