<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$is_ajax) {
    $is_ajax = isset($_POST['ajax']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false);
}

if ($is_ajax && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (is_array($data)) {
        $_POST = array_merge($_POST, $data);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request method.']);
        exit;
    }
    redirect('/task_collab_system/index.php');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }
    die('Invalid CSRF token.');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$project_id = (int)($_POST['project_id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed_statuses = ['todo', 'in_progress', 'in_review', 'done'];

if (!is_project_member($conn, $project_id, $user_id) || !in_array($status, $allowed_statuses, true)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request or permission denied.']);
        exit;
    }
    die('Invalid request.');
}

// Make sure the task actually belongs to this project
$stmt = $conn->prepare('SELECT id, title FROM tasks WHERE id = ? AND project_id = ? AND deleted_at IS NULL');
$stmt->bind_param('ii', $task_id, $project_id);
$stmt->execute();
$task_result = $stmt->get_result();
if ($task_result->num_rows === 0) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Task not found.']);
        exit;
    }
    die('Task not found.');
}
$task_data = $task_result->fetch_assoc();
$title = $task_data['title'];

$stmt = $conn->prepare('UPDATE tasks SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $task_id);
$stmt->execute();

$actor_name = $_SESSION['user_name'];
$status_nice = status_label($status);
$activity_msg = "{$actor_name} moved task '" . mb_substr($title, 0, 30) . "' to {$status_nice}";
$activity_link = "/task_collab_system/project.php?id=" . $project_id;
log_activity_notification($conn, $project_id, $user_id, $activity_msg, $activity_link);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'new_status' => $status]);
    exit;
}

redirect('/task_collab_system/project.php?id=' . $project_id);
