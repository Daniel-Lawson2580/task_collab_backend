<?php
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    if (is_api_request()) { echo json_encode(['success' => true, 'user' => $_SESSION['user_name']]); exit; } else { redirect('/task_collab_system/index.php'); }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (is_api_request()) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($input['email'])) $_POST['email'] = $input['email'];
        if (!empty($input['password'])) $_POST['password'] = $input['password'];
    }
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        if (is_api_request()) { echo json_encode(['error' => 'Please enter both email and password.']); exit; }
        $errors[] = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                if (is_api_request()) { echo json_encode(['success' => true, 'user' => $user]); exit; } else { redirect('/task_collab_system/index.php'); }
            } else {
                if (is_api_request()) { echo json_encode(['error' => 'Incorrect email or password.']); exit; }
                $errors[] = 'Incorrect email or password.';
            }
        } else {
            if (is_api_request()) { echo json_encode(['error' => 'Incorrect email or password.']); exit; }
            $errors[] = 'Incorrect email or password.';
        }
    }
}

if (is_api_request()) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$page_title = 'Login';
require __DIR__ . '/../includes/auth_header.php';
?>

    <h2>Welcome back</h2>
    <p style="text-align:center; color:var(--text-muted); margin-bottom:24px;">Please enter your details to sign in.</p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Account created successfully. You can now log in.</div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?php echo h($error); ?></div>
    <?php endforeach; ?>

    <form method="POST" action="login.php" style="display:flex; flex-direction:column; gap:16px;">
        <?php echo csrf_field(); ?>
        <div class="form-group" style="margin-bottom:0;">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?php echo h($_POST['email'] ?? ''); ?>" required placeholder="you@example.com" style="padding:12px; border-radius:var(--radius-sm);">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label for="password" style="margin-bottom:0;">Password</label>
                <a href="forgot_password.php" style="font-size:0.85rem; color:var(--primary); font-weight:500; text-decoration:none;">Forgot password?</a>
            </div>
            <div style="position:relative; margin-top:8px;">
                <input type="password" id="password" name="password" required placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" style="width:100%; padding:12px; padding-right:40px; border-radius:var(--radius-sm);">
                <i class="ph ph-eye" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-muted); font-size:1.2rem;" onclick="togglePasswordVisibility(this, 'password')"></i>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; justify-content:center; font-size:1rem; margin-top:8px;">Sign in</button>
        
        <div style="display:flex; align-items:center; gap:12px; margin: 4px 0; color:var(--text-muted); font-size:0.85rem;">
            <div style="flex:1; height:1px; background:var(--glass-border);"></div>
            <span>OR</span>
            <div style="flex:1; height:1px; background:var(--glass-border);"></div>
        </div>

        <button type="button" class="btn" style="width:100%; padding:12px; justify-content:center; font-size:1rem; background:var(--input-bg); color:var(--text-main); border:1px solid var(--border-color); display:flex; gap:8px; align-items:center; opacity:0.9; transition:opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Sign in with Google
        </button>
    </form>
    <p style="margin-top:24px; font-size:0.9rem; text-align:center; color:var(--text-muted);">
        Don't have an account? <a href="register.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Sign up</a>
    </p>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>

