<?php
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    die(json_encode(['success' => false, 'error' => 'Not logged in']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Invalid request method']));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die(json_encode(['success' => false, 'error' => 'Invalid CSRF token']));
}

$task_id = (int)($_POST['task_id'] ?? 0);
if ($task_id <= 0) {
    die(json_encode(['success' => false, 'error' => 'Invalid task ID']));
}

// Check project access
$stmt = $conn->prepare("SELECT project_id FROM tasks WHERE id = ?");
$stmt->bind_param('i', $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task || !is_project_member($conn, $task['project_id'], $_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Access denied']));
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'error' => 'File upload error (Code: ' . ($_FILES['attachment']['error'] ?? 'Unknown') . ')']));
}

$file = $_FILES['attachment'];
$original_filename = basename($file['name']);
$file_size = $file['size'];
$file_type = $file['type'];

// Allow list for security
$allowed_types = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'application/zip',
    'application/x-zip-compressed'
];

if (!in_array($file_type, $allowed_types)) {
    die(json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: Images, PDF, Word, Excel, Text, Zip.']));
}

// 5MB max size limit
if ($file_size > 5 * 1024 * 1024) {
    die(json_encode(['success' => false, 'error' => 'File is too large (max 5MB).']));
}

$upload_dir = __DIR__ . '/../uploads/attachments/';
$stored_filename = uniqid('att_') . '_' . bin2hex(random_bytes(4)) . '.' . pathinfo($original_filename, PATHINFO_EXTENSION);
$destination = $upload_dir . $stored_filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $stmt = $conn->prepare("INSERT INTO task_attachments (task_id, user_id, original_filename, stored_filename, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisssi', $task_id, $_SESSION['user_id'], $original_filename, $stored_filename, $file_type, $file_size);
    if ($stmt->execute()) {
        // Log activity notification
        $task_stmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
        $task_stmt->bind_param('i', $task_id);
        $task_stmt->execute();
        $task_title = $task_stmt->get_result()->fetch_assoc()['title'];
        
        $msg = $_SESSION['user_name'] . " attached '$original_filename' to task '$task_title'";
        log_activity_notification($conn, $task['project_id'], $_SESSION['user_id'], $msg, '/task_collab_system/task_view.php?id=' . $task_id);
        
        echo json_encode(['success' => true]);
    } else {
        unlink($destination); // rollback
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
