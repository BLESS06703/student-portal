<?php
$message = '';
$error = '';
$validToken = false;
$userId = 0;

if (isset($_GET['token']) && isset($_GET['id'])) {
    $token = $_GET['token'];
    $userId = intval($_GET['id']);
    $tokenHash = 'reset_' . hash('sha256', $token);
    
    try {
        $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'root', 'lpBBXfReELFhpzVsXbKvsUVjAmTJhDCs');
        $stmt = $pdo->prepare('SELECT id FROM user_tokens WHERE user_id = :uid AND token_hash = :th AND expires_at > NOW()');
        $stmt->execute(['uid' => $userId, 'th' => $tokenHash]);
        if ($stmt->fetch()) { $validToken = true; }
    } catch (PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) { $error = 'Password must be 8+ characters.'; }
    elseif ($password !== $confirm) { $error = 'Passwords do not match.'; }
    else {
        try {
            $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'root', 'lpBBXfReELFhpzVsXbKvsUVjAmTJhDCs');
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE students SET password_hash = :h WHERE id = :id')->execute(['h' => $hash, 'id' => $userId]);
            $pdo->prepare('DELETE FROM user_tokens WHERE user_id = :uid AND token_hash = :th')->execute(['uid' => $userId, 'th' => $tokenHash]);
            header('Location: index.php?password_reset=1'); exit;
        } catch (PDOException $e) { $error = 'Error resetting password.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .auth-container { width: 100%; max-width: 420px; }
        .auth-card { background: #fff; border-radius: 16px; padding: 2.5rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-logo { width: 44px; height: 44px; background: #1a1a1a; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; color: white; margin-bottom: 1rem; }
        .auth-title { font-size: 1.5rem; font-weight: 700; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.4rem; }
        .password-wrapper { position: relative; }
        .form-input { width: 100%; padding: 0.8rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 8px; color: #1a1a1a; font-size: 0.9rem; font-family: inherit; }
        .password-wrapper .form-input { padding-right: 2.75rem; }
        .form-input:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .toggle-password { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 0.9rem; }
        .btn { width: 100%; padding: 0.85rem; background: #1a1a1a; color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #333; }
        .alert { padding: 0.8rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">SP</div>
                <h1 class="auth-title">Reset Password</h1>
            </div>
            <?php if (!$validToken): ?>
                <div class="alert alert-error">Invalid or expired reset link. Please request a new one.</div>
                <a href="forgot_password.php" class="btn" style="display:block;text-align:center;">Request New Link</a>
            <?php else: ?>
                <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 8 characters" required minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePass('password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Re-enter password" required minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePass('confirm_password', this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function togglePass(id, btn) { var p = document.getElementById(id); var icon = btn.querySelector('i'); if (p.type === 'password') { p.type = 'text'; icon.className = 'fas fa-eye-slash'; } else { p.type = 'password'; icon.className = 'fas fa-eye'; } }
    </script>
</body>
</html>
