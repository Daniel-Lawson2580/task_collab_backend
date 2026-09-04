<?php
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

$stmt_notif = $conn->prepare("SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt_notif->bind_param('i', $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_unread = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_unread->bind_param('i', $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['c'];

// Format dates
foreach ($notifications as &$n) {
    $n['formatted_date'] = date('M j, g:i A', strtotime($n['created_at']));
}
unset($n);

echo json_encode([
    'success' => true,
    'unread_count' => (int)$unread_count,
    'notifications' => $notifications
]);
