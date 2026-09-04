<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);

if (!is_project_member($conn, $project_id, $user_id)) {
    die('You do not have access to this project.');
}

$stmt = $conn->prepare('SELECT name FROM projects WHERE id = ?');
$stmt->bind_param('i', $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
if (!$project) { die('Project not found.'); }

$stmt = $conn->prepare(
    "SELECT u.id, u.name FROM users u JOIN project_members pm ON pm.user_id = u.id WHERE pm.project_id = ? ORDER BY u.name"
);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
    $due_date = $_POST['due_date'] !== '' ? $_POST['due_date'] : null;

    if ($title === '') {
        $errors[] = 'Task title is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO tasks (project_id, title, description, assigned_to, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issisi', $project_id, $title, $description, $assigned_to, $due_date, $user_id);
        $stmt->execute();
        $task_id = $conn->insert_id;

        if ($assigned_to && $assigned_to !== $user_id) {
            $notif_msg = "You were assigned a new task: " . mb_substr($title, 0, 50);
            $notif_link = "/task_collab_system/project.php?id=" . $project_id;
            $stmt_n = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            $stmt_n->bind_param('iss', $assigned_to, $notif_msg, $notif_link);
            $stmt_n->execute();
        }

        $actor_name = $_SESSION['user_name'];
        $activity_msg = "{$actor_name} created a new task: " . mb_substr($title, 0, 50);
        $activity_link = "/task_collab_system/project.php?id=" . $project_id;
        log_activity_notification($conn, $project_id, $user_id, $activity_msg, $activity_link);

        redirect('/task_collab_system/project.php?id=' . $project_id);
    }
}

$page_title = 'Add Task';
require __DIR__ . '/includes/header.php';
?>

<h1>Add Task to <?php echo h($project['name']); ?></h1>

<div class="card" style="max-width:560px;">
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endforeach; ?>

    <form method="POST" action="task_add.php?project_id=<?php echo $project_id; ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?php echo h($_POST['title'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?php echo h($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label for="assigned_to">Assign to</label>
            <select id="assigned_to" name="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?php echo (int)$m['id']; ?>"><?php echo h($m['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="due_date">Due date</label>
            <input type="date" id="due_date" name="due_date">
        </div>
        <button type="submit" class="btn btn-primary">Add Task</button>
        <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
