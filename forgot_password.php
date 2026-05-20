<?php
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if ($email) {
        try {
            $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
            $stmt = $pdo->prepare('SELECT id, full_name, student_id FROM students WHERE email = :e LIMIT 1');
            $stmt->execute(['e' => $email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token
                $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :uid AND token_hash LIKE "reset_%"')->execute(['uid' => $user['id']]);
                $stmt = $pdo->prepare('INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (:uid, :th, :exp)');
                $stmt->execute(['uid' => $user['id'], 'th' => 'reset_' . $tokenHash, 'exp' => $expires]);
                
                // In production, send email. For now, show the reset link
                $resetLink = "http://localhost:8080/reset_password.php?token=$token&id=" . $user['id'];
                $message = "Password reset link generated. In production, this would be emailed.<br><br><strong>Reset Link:</strong><br><a href=\"$resetLink\">$resetLink</a>";
            } else {
                // Don't reveal if email exists
                $message = 'If this email is registered, a reset link has been sent.';
            }
        } catch (PDOException $e) {
            $error = 'Error processing request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .auth-container { width: 100%; max-width: 420px; }
        .auth-card { background: #fff; border-radius: 16px; padding: 2.5rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-logo { width: 44px; height: 44px; background: #1a1a1a; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; color: white; margin-bottom: 1rem; }
        .auth-title { font-size: 1.5rem; font-weight: 700; }
        .auth-subtitle { font-size: 0.9rem; color: #6b7280; margin-top: 0.3rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.4rem; }
        .form-input { width: 100%; padding: 0.8rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 8px; color: #1a1a1a; font-size: 0.9rem; font-family: inherit; }
        .form-input:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .btn { width: 100%; padding: 0.85rem; background: #1a1a1a; color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #333; }
        .alert { padding: 0.8rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; word-break: break-all; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #6b7280; }
        .auth-footer a { color: #1a1a1a; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">SP</div>
                <h1 class="auth-title">Forgot Password</h1>
                <p class="auth-subtitle">Enter your email to receive a reset link</p>
            </div>
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="you@school.edu" required>
                </div>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
            <div class="auth-footer"><a href="index.php">Back to Sign In</a></div>
        </div>
    </div>
</body>
</html>
