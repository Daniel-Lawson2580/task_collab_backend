<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

$project_id = (int)($_GET['project_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if ($project_id === 0) {
    echo json_encode(['error' => 'Project ID is required.']);
    exit;
}

if (!is_project_member($conn, $project_id, $user_id)) {
    echo json_encode(['error' => 'Permission denied.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT pm.id, pm.message, pm.created_at, pm.user_id, u.name 
    FROM project_messages pm 
    JOIN users u ON pm.user_id = u.id 
    WHERE pm.project_id = ? AND pm.id > ? 
    ORDER BY pm.id ASC
");
$stmt->bind_param('ii', $project_id, $last_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['is_mine'] = ((int)$row['user_id'] === $user_id);
    // Format time, e.g., '10:42 AM'
    $row['time'] = date('g:i A', strtotime($row['created_at']));
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages]);
