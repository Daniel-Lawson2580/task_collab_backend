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
$mock_email_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                // Expire in 1 hour
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Update DB
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                $update_stmt->bind_param("sss", $token, $expires, $email);
                
                if ($update_stmt->execute()) {
                    $success_message = "If an account with that email exists, we have sent a password reset link.";
                    // MOCK EMAIL: For local development, we print the link. In production, this would be an email.
                    $reset_url = "http://localhost/task_collab_system/auth/reset_password.php?token=" . $token;
                    $mock_email_link = $reset_url;
                } else {
                    $errors[] = "A database error occurred. Please try again.";
                }
            } else {
                // For security, don't reveal if the email exists or not. Show the same success message.
                $success_message = "If an account with that email exists, we have sent a password reset link.";
            }
        }
}

$page_title = 'Forgot Password';
require __DIR__ . '/../includes/auth_header.php';
?>

    <h2>Reset Password</h2>
    <p style="text-align:center; color:var(--text-muted); margin-bottom:24px;">Enter your email to receive a reset link.</p>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo h($success_message); ?>
        </div>
        
        <?php if ($mock_email_link): ?>
            <!-- MOCK EMAIL BOX FOR LOCAL DEVELOPMENT -->
            <div style="margin-top: 16px; padding: 16px; background-color: var(--tag-normal-bg); border: 1px solid var(--tag-normal-text); border-radius: var(--radius-sm); text-align: center;">
                <p style="color: var(--tag-normal-text); font-weight: 600; margin-bottom: 8px;">[MOCK EMAIL SENT]</p>
                <a href="<?php echo h($mock_email_link); ?>" style="color: var(--primary); font-weight: 600; text-decoration: underline; word-break: break-all;">
                    Click here to reset your password
                </a>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
    
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endforeach; ?>

        <form method="POST" action="forgot_password.php" style="display:flex; flex-direction:column; gap:16px;">
            <?php echo csrf_field(); ?>
            <div class="form-group" style="margin-bottom:0;">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com" style="padding:12px; border-radius:var(--radius-sm);">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; justify-content:center; font-size:1rem; margin-top:8px;">Send Reset Link</button>
        </form>
        
    <?php endif; ?>
    
    <p style="margin-top:24px; font-size:0.9rem; text-align:center; color:var(--text-muted);">
        Remember your password? <a href="login.php" style="color:var(--primary); font-weight:600; text-decoration:none;">Sign in</a>
    </p>

<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
