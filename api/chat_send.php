<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Decode JSON POST payload
$data = json_decode(file_get_contents('php://input'), true);
if (is_array($data)) {
    $_POST = array_merge($_POST, $data);
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$project_id = (int)($_POST['project_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($project_id === 0 || $message === '') {
    echo json_encode(['error' => 'Project ID and message are required.']);
    exit;
}

if (!is_project_member($conn, $project_id, $user_id)) {
    echo json_encode(['error' => 'Permission denied.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO project_messages (project_id, user_id, message) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $project_id, $user_id, $message);

if ($stmt->execute()) {
    $msg_id = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $msg_id]);
} else {
    echo json_encode(['error' => 'Database error.']);
}
