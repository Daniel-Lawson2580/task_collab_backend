<?php
// CORS Headers for API access
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}, Authorization");
    else
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}

// Secure session configuration
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => true, 
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

// Custom Token Auth for cross-domain APIs
if (isset($_SERVER['HTTP_AUTHORIZATION']) && is_api_request()) {
    $token = trim(str_replace('Bearer', '', $_SERVER['HTTP_AUTHORIZATION']));
    if (!empty($token)) {
        require_once __DIR__ . '/../config/db.php';
        $stmt = $conn->prepare("SELECT user_id FROM user_sessions WHERE session_id = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $_SESSION['user_id'] = $res->fetch_assoc()['user_id'];
        }
    }
}

// Initialize CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/db.php';

/** HTML escaping */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/** CSRF field generation */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

/** CSRF validation */
function verify_csrf() {
    if (is_api_request()) return; // Skip CSRF for API requests (since they use custom headers or CORS)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

/** Redirect to a given page and stop execution. */
function redirect($page) {
    header('Location: ' . $page);
    exit;
}

/** Block access unless the user is logged in. */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        if (is_api_request()) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        redirect('/task_collab_system/auth/login.php');
    }
}

/** Check if current request is an API call */
function is_api_request() {
    return isset($_GET['api']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/** Return JSON error and stop execution. */
function api_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/** Block access unless the user is an admin. */
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        if (is_api_request()) api_error('Unauthorized access.', 403);
        die('Unauthorized access.');
    }
}

/** Utility to format dates consistently */
function format_date($datetime) {
    return date('M j, Y', strtotime($datetime));
}

/** Return a CSS class or color based on task status */
function get_status_badge_class($status) {
    $map = [
        'pending' => 'bg-warning',
        'in_progress' => 'bg-info',
        'completed' => 'bg-success'
    ];
    return $map[$status] ?? 'bg-secondary';
}

function get_status_label($status) {
    $map = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed'
    ];
    return $map[$status] ?? ucfirst($status);
}

/**
 * Log activity notification for project members and admins.
 * Excludes the actor (user performing the action).
 */
function log_activity_notification($conn, $project_id, $actor_id, $message, $link) {
    // Get all members of the project + admins (excluding the actor)
    $stmt = $conn->prepare("
        SELECT id FROM users 
        WHERE role = 'admin' AND id != ?
        UNION 
        SELECT user_id AS id FROM project_members 
        WHERE project_id = ? AND user_id != ?
        UNION
        SELECT owner_id AS id FROM projects
        WHERE id = ? AND owner_id != ?
    ");
    $stmt->bind_param('iiiii', $actor_id, $project_id, $actor_id, $project_id, $actor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $insert = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    while ($row = $result->fetch_assoc()) {
        $insert->bind_param('iss', $row['id'], $message, $link);
        $insert->execute();
    }
}
