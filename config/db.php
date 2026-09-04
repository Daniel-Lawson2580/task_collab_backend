<?php
/**
 * Database connection settings.
 * Uses environment variables for live hosting (like Render/Aiven), falls back to local XAMPP.
 */
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$name = getenv('DB_NAME') ?: 'task_collab_system';
$port = getenv('DB_PORT') ?: 3306;

define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $name);

$conn = mysqli_init();

// TiDB requires a secure TLS connection
if (getenv('DB_HOST')) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->real_connect($host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect($host, $user, $pass, $name, $port);
}

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
