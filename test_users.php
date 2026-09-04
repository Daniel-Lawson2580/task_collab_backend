<?php
require_once 'C:\xampp\htdocs\task_collab_system\config\db.php';
$result = $conn->query('SELECT id, name, email FROM users');
while ($row = $result->fetch_assoc()) {
    echo "USER: " . $row['email'] . "\n";
}
