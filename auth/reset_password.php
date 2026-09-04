<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

// If already logged in, redirect
if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$success_message = '';
$token = $_GET['token'] ?? '';
$is_valid_token = false;
$user_id = null;

if (empty($token)) {
    $errors[] = "Invalid or missing reset token.";
} else {
    // Verify token
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $expires = strtotime($user['reset_expires']);
        if (time() > $expires) {
            $errors[] = "This password reset link has expired. Please request a new one.";
        } else {
            $is_valid_token = true;
            $user_id = $user['id'];
        }
    } else {
        $errors[] = "Invalid or expired reset token.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
            $errors[] = "Both password fields are required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Your password has been successfully reset. You can now log in.";
                $is_valid_token = false; // hide the form
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
        }
}

$page_title = 'Reset Password';
require __DIR__ . '/../includes/auth_header.php';
?>

    <h2>Create New Password</h2>
    <p style="text-align:center; color:var(--text-muted); margin-bottom:24px;">Please enter your new password below.</p>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo h($success_message); ?>
        </div>
        <a href="login.php" class="btn btn-primary" style="display:flex; justify-content:center; padding:12px; margin-top: 16px;">Go to Login</a>
    <?php else: ?>
    
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endforeach; ?>

        <?php if ($is_valid_token): ?>
            <form method="POST" action="reset_password.php?token=<?php echo urlencode($token); ?>" style="display:flex; flex-direction:column; gap:16px;">
                <?php echo csrf_field(); ?>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label for="password">New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" required placeholder="At least 6 characters" style="width:100%; padding:12px; padding-right:40px; border-radius:var(--radius-sm);">
                        <i class="ph ph-eye" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-muted); font-size:1.2rem;" onclick="togglePasswordVisibility(this, 'password')"></i>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label for="confirm_password">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••" style="width:100%; padding:12px; padding-right:40px; border-radius:var(--radius-sm);">
                        <i class="ph ph-eye" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-muted); font-size:1.2rem;" onclick="togglePasswordVisibility(this, 'confirm_password')"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; justify-content:center; font-size:1rem; margin-top:8px;">Reset Password</button>
            </form>
        <?php endif; ?>
        
    <?php endif; ?>
    
    <?php if (!$success_message && !$is_valid_token): ?>
        <div style="text-align: center; margin-top: 24px;">
            <a href="forgot_password.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Request a new link</a>
        </div>
    <?php endif; ?>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
