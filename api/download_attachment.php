<?php
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    die("Not logged in.");
}

$att_id = (int)($_GET['id'] ?? 0);
if ($att_id <= 0) {
    die("Invalid attachment ID.");
}

$stmt = $conn->prepare("
    SELECT a.*, t.project_id 
    FROM task_attachments a
    JOIN tasks t ON a.task_id = t.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $att_id);
$stmt->execute();
$att = $stmt->get_result()->fetch_assoc();

if (!$att || !is_project_member($conn, $att['project_id'], $_SESSION['user_id'])) {
    die("Access denied or file not found.");
}

$filepath = __DIR__ . '/../uploads/attachments/' . $att['stored_filename'];

if (!file_exists($filepath)) {
    die("File no longer exists on server.");
}

// Serve the file
header('Content-Description: File Transfer');
header('Content-Type: ' . $att['file_type']);
header('Content-Disposition: attachment; filename="' . basename($att['original_filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
