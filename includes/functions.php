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
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
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

// Initialize CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/db.php';

/** Generate a hidden CSRF input field for forms */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

function is_api_request() {
    return isset($_GET['api']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/** Return JSON error and stop execution. */
function api_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/** Verify CSRF token from POST requests */
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
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        // Fallback for API client that sends submitter
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['submitter'])) {
            $_SESSION['user_id'] = $input['submitter'];
        } else {
            if (is_api_request()) {
                api_error('Not logged in', 401);
            }
            redirect('/task_collab_system/auth/login.php');
        }
    }

    // Check inactivity timeout (20 minutes = 1200 seconds)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
        // Remove from db to revoke
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $session_id = session_id();
        $stmt->bind_param("s", $session_id);
        $stmt->execute();

        session_unset();
        session_destroy();
        if (is_api_request()) api_error('Session timeout', 401);
        redirect('/task_collab_system/auth/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();

    // Check if session is still valid in DB (not revoked)
    $stmt = $conn->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $session_id = session_id();
    $stmt->bind_param("si", $session_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        // Session was revoked
        session_unset();
        session_destroy();
        if (is_api_request()) api_error('Session revoked', 401);
        redirect('/task_collab_system/auth/login.php?revoked=1');
    } else {
        // Update last activity in DB
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?");
        $stmt->bind_param("s", $session_id);
        $stmt->execute();
    }
}

/** Check if the user is logged in. */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/** Escape output safely for HTML. */
function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if the current user is an admin.
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin access, redirect if not admin.
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        redirect('/task_collab_system/dashboard.php');
    }
}

/**
 * Check if user is a member of the project (or is an admin).
 */
function is_project_member($conn, $project_id, $user_id) {
    if (is_admin()) {
        return true;
    }
    $stmt = $conn->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $project_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/** Friendly label + CSS class for a task status. */
function status_label($status) {
    $map = [
        'todo'        => 'To Do',
        'in_progress' => 'In Progress',
        'in_review'   => 'In Review',
        'done'        => 'Done',
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

