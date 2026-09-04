<?php
require_once 'C:\xampp\htdocs\task_collab_system\config\db.php';
$stmt = $conn->prepare('SELECT id, name, email, password FROM users WHERE email = ?');
$email = 'danny@gmail.com';
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify('Nutjob56', $user['password'])) {
        echo "SUCCESS: Valid credentials for " . $user['name'] . "\n";
    } else {
        echo "ERROR: Password does not match.\n";
    }
} else {
    echo "ERROR: User not found.\n";
}
