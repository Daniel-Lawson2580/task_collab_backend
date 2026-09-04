<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
verify_csrf();

$project_id = (int)($_POST['project_id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Verify ownership or admin
$stmt = $conn->prepare("SELECT owner_id FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project || ($project['owner_id'] != $user_id && !is_admin())) {
    die("Unauthorized to delete this project.");
}

// Hard delete the project (Cascading takes care of tasks, members, etc.)
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();

header("Location: ../dashboard.php");
exit;
?>
