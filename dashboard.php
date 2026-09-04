<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

// Get overall stats

if (is_admin()) {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL) AS total_projects,
            (SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL) AS total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE status = 'done' AND deleted_at IS NULL) AS total_completed
    ");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = ? AND p.deleted_at IS NULL) AS total_projects,
            (SELECT COUNT(*) FROM tasks WHERE (assigned_to = ? OR created_by = ?) AND deleted_at IS NULL) AS total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE (assigned_to = ? OR created_by = ?) AND status = 'done' AND deleted_at IS NULL) AS total_completed
    ");
    $stmt->bind_param('iiiii', $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
}
$stats = $stmt->get_result()->fetch_assoc();

// Fetch projects based on role
if (is_admin()) {
    // Admins see all projects
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
    // Users see their assigned projects
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.description, p.created_at,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done' AND t.deleted_at IS NULL) AS done_count
         FROM projects p
         JOIN project_members pm ON pm.project_id = p.id
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
        'stats' => $stats,
        'projects' => $projects->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}


$page_title = 'Dashboard Overview';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard Overview</h1>
</div>

<div class="dashboard-layout">
    <div class="dashboard-main">
        <div class="board-controls" style="border:none; margin-bottom: 24px;">
            <div class="stats-grid" style="display:flex; gap:24px; width:100%;">
                <div class="card" style="flex:1; padding:24px;">
                    <div style="font-size:0.9rem; color:var(--text-muted); font-weight:600; margin-bottom:8px;">Total Projects</div>
                    <div style="font-size:2rem; font-weight:700; color:var(--primary);"><?php echo (int)$stats['total_projects']; ?></div>
                </div>
                <div class="card" style="flex:1; padding:24px;">
                    <div style="font-size:0.9rem; color:var(--text-muted); font-weight:600; margin-bottom:8px;">Tasks Involved</div>
                    <div style="font-size:2rem; font-weight:700; color:#FF9F43;"><?php echo (int)$stats['total_tasks']; ?></div>
                </div>
                <div class="card" style="flex:1; padding:24px;">
                    <div style="font-size:0.9rem; color:var(--text-muted); font-weight:600; margin-bottom:8px;">Tasks Completed</div>
                    <div style="font-size:2rem; font-weight:700; color:#249F6B;"><?php echo (int)$stats['total_completed']; ?></div>
                </div>
            </div>
        </div>

        <h2 style="margin-bottom: 16px; font-size: 1.25rem;">Your Recent Projects</h2>

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
                <h3>No Projects Yet</h3>
                <p>You're not part of any projects. Start collaborating by creating a new project space for your team.</p>
                <a href="create_project.php" class="btn btn-primary">Create your first project</a>
            </div>
        <?php else: ?>
            <div class="project-grid">
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
    </div>

    <div class="dashboard-sidebar">
        <!-- Calendar Widget -->
        <div class="calendar-widget">
            <div class="calendar-header">
                <?php echo date('F Y'); ?>
                <div class="calendar-header-nav">
                    <i class="ph ph-caret-left"></i>
                    <i class="ph ph-caret-right"></i>
                </div>
            </div>
            
            <div class="calendar-grid">
                <div class="calendar-day-name">Mo</div>
                <div class="calendar-day-name">Tu</div>
                <div class="calendar-day-name">We</div>
                <div class="calendar-day-name">Th</div>
                <div class="calendar-day-name">Fr</div>
                <div class="calendar-day-name">Sa</div>
                <div class="calendar-day-name">Su</div>
                
                <?php
                $first_day = date('N', strtotime(date('Y-m-01'))); // 1 (Mon) to 7 (Sun)
                $days_in_month = date('t');
                $current_day = date('j');
                $prev_month_days = date('t', strtotime('-1 month'));

                // Render previous month trailing days
                for ($i = 1; $i < $first_day; $i++) {
                    $day = $prev_month_days - ($first_day - 1 - $i);
                    echo "<div class='calendar-date other-month'>$day</div>";
                }

                // Render current month days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $active_class = ($day == $current_day) ? 'active' : '';
                    echo "<div class='calendar-date $active_class'>$day</div>";
                }

                // Render next month leading days to fill grid
                $total_cells = ($first_day - 1) + $days_in_month;
                $remaining_cells = 42 - $total_cells; // up to 6 rows of 7
                if ($remaining_cells >= 7) $remaining_cells -= 7; // remove empty bottom row if not needed
                
                for ($i = 1; $i <= $remaining_cells; $i++) {
                    echo "<div class='calendar-date other-month'>$i</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

