<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $scheduled_at = $_POST['scheduled_at'] ?? '';

    if ($project_id === 0 || $title === '' || $scheduled_at === '') {
        $errors[] = 'Please fill out all fields.';
    } elseif (!is_project_member($conn, $project_id, $user_id)) {
        $errors[] = 'You are not a member of this project.';
    } else {
        $stmt = $conn->prepare("INSERT INTO meetings (project_id, title, scheduled_at, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('issi', $project_id, $title, $scheduled_at, $user_id);
        if ($stmt->execute()) {
            $success = 'Meeting scheduled successfully!';
            
            // Notify all project members and admins
            $actor_name = $_SESSION['user_name'];
            $notif_msg = "{$actor_name} scheduled a new meeting: " . mb_substr($title, 0, 40);
            $notif_link = "/task_collab_system/meetings.php";
            log_activity_notification($conn, $project_id, $user_id, $notif_msg, $notif_link);
        } else {
            $errors[] = 'Failed to schedule meeting.';
        }
    }
}

// Fetch user's projects for the dropdown
if (is_admin()) {
    $stmt = $conn->prepare("SELECT id, name FROM projects ORDER BY name ASC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT p.id, p.name FROM projects p JOIN project_members pm ON p.id = pm.project_id WHERE pm.user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
$my_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch upcoming meetings
if (is_admin()) {
    $stmt = $conn->prepare(
        "SELECT m.*, p.name AS project_name, u.name AS creator_name 
         FROM meetings m 
         JOIN projects p ON m.project_id = p.id
         JOIN users u ON m.created_by = u.id
         WHERE m.scheduled_at >= NOW()
         ORDER BY m.scheduled_at ASC"
    );
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "SELECT m.*, p.name AS project_name, u.name AS creator_name 
         FROM meetings m 
         JOIN projects p ON m.project_id = p.id
         JOIN project_members pm ON p.id = pm.project_id
         JOIN users u ON m.created_by = u.id
         WHERE pm.user_id = ? AND m.scheduled_at >= NOW()
         ORDER BY m.scheduled_at ASC"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}
$meetings = $stmt->get_result();

$page_title = 'Meetings';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Meetings</h1>
</div>

<div style="display:flex; gap:32px; align-items:flex-start;">
    
    <!-- Left: Meeting List -->
    <div style="flex:2;">
        <h2 style="font-size:1.2rem; margin-bottom:16px;">Upcoming Meetings</h2>
        <?php if ($meetings->num_rows === 0): ?>
            <div class="card empty-state">
                <svg width="150" height="150" viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="25" y="35" width="100" height="90" rx="12" fill="var(--column-bg)" stroke="var(--border-color)" stroke-width="2"/>
                    <path d="M25 65H125" stroke="var(--border-color)" stroke-width="2"/>
                    <rect x="40" y="20" width="16" height="30" rx="8" fill="var(--primary)"/>
                    <rect x="94" y="20" width="16" height="30" rx="8" fill="var(--primary)"/>
                    <circle cx="55" cy="95" r="8" fill="var(--text-muted)" opacity="0.5"/>
                    <circle cx="95" cy="95" r="8" fill="var(--text-muted)" opacity="0.5"/>
                </svg>
                <h3>Clear Schedule</h3>
                <p>No upcoming meetings. Use the form to schedule a new sync with your team.</p>
            </div>
        <?php else: ?>
            <div class="task-list">
                <?php while ($m = $meetings->fetch_assoc()): ?>
                    <div class="card" style="padding:20px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div class="tag tag-normal" style="margin-bottom:8px; display:inline-block;"><?php echo h($m['project_name']); ?></div>
                            <h3 style="font-size:1.1rem; margin-bottom:4px;"><?php echo h($m['title']); ?></h3>
                            <div style="color:var(--text-muted); font-size:0.85rem;">Organized by <?php echo h($m['creator_name']); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:600; font-size:1.1rem; color:var(--primary);"><?php echo date('M j, Y', strtotime($m['scheduled_at'])); ?></div>
                            <div style="color:var(--text-muted); font-size:0.9rem;"><?php echo date('g:i A', strtotime($m['scheduled_at'])); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Schedule Form -->
    <div class="card" style="flex:1;">
        <h2 style="font-size:1.2rem; margin-bottom:16px;">Schedule Meeting</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endforeach; ?>

        <form method="POST" action="meetings.php">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="project_id">Project</label>
                <select id="project_id" name="project_id" style="width:100%; padding:10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);" required>
                    <option value="">Select Project</option>
                    <?php foreach ($my_projects as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">All members of the selected project will be invited.</p>
            </div>
            <div class="form-group">
                <label for="title">Meeting Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="scheduled_at">Date & Time</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Schedule Meeting</button>
        </form>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
