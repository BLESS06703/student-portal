<?php
include 'db_config.php';

session_start();
$error = '';

// Remember me auto-login
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
        $stmt = $pdo->prepare('SELECT s.id, s.full_name, s.student_id FROM students s JOIN user_tokens t ON s.id = t.user_id WHERE t.token_hash = :token AND t.expires_at > NOW()');
        $stmt->execute(['token' => hash('sha256', $_COOKIE['remember_token'])]);
        $user = $stmt->fetch();
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['student_id'] = $user['student_id'];
            header('Location: dashboard.php'); exit;
        }
    } catch (PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if ($student_id && $password) {
        try {
            $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
            $stmt = $pdo->prepare('SELECT id, full_name, student_id, password_hash, status, role FROM students WHERE student_id = :sid OR email = :sid LIMIT 1');
            $stmt->execute(['sid' => $student_id]);
            $user = $stmt->fetch();
            
            if ($user && $user['status'] !== 'approved') {
                $error = 'Your account is pending approval.';
            } elseif ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['student_id'] = $user['student_id'];
                
                $stmt = $pdo->prepare('UPDATE students SET last_login = NOW() WHERE id = :id');
                $stmt->execute(['id' => $user['id']]);
                
                if ($remember) {
                    try {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);
                        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $stmt = $pdo->prepare('INSERT INTO user_tokens (user_id, token_hash, expires_at) VALUES (:uid, :th, :exp)');
                        $stmt->execute(['uid' => $user['id'], 'th' => $tokenHash, 'exp' => $expires]);
                        setcookie('remember_token', $token, ['expires' => time() + (30*24*60*60), 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                    } catch (PDOException $e) {}
                }
                
                if ($user['role'] === 'admin' || $user['role'] === 'class_rep') {
                    header('Location: teacher.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid student ID or password.';
            }
        } catch (PDOException $e) {
            $error = 'System error. Please try later.';
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Student Portal</title>
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
        .auth-subtitle { font-size: 0.9rem; color: #6b7280; margin-top: 0.3rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.4rem; }
        .password-wrapper { position: relative; }
        .form-input { width: 100%; padding: 0.8rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 8px; color: #1a1a1a; font-size: 0.9rem; font-family: inherit; transition: all 0.2s; }
        .password-wrapper .form-input { padding-right: 2.75rem; }
        .form-input:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .toggle-password { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0.25rem; font-size: 0.9rem; }
        .toggle-password:hover { color: #1a1a1a; }
        .form-options { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; border: 2px solid #d1d5db; border-radius: 4px; cursor: pointer; accent-color: #1a1a1a; }
        .checkbox-label { font-size: 0.85rem; color: #6b7280; }
        .forgot-link { font-size: 0.85rem; color: #3b82f6; text-decoration: none; font-weight: 500; }
        .forgot-link:hover { text-decoration: underline; }
        .btn { width: 100%; padding: 0.85rem; background: #1a1a1a; color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; }
        .btn:hover { background: #333; }
        .alert { padding: 0.8rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #6b7280; }
        .auth-footer a { color: #1a1a1a; text-decoration: none; font-weight: 600; }
        @media (max-width: 480px) { .auth-card { padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">SP</div>
                <h1 class="auth-title">Student Portal</h1>
                <p class="auth-subtitle">Sign in with your student credentials</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Account created! Please sign in after admin approval.</div><?php endif; ?>
            <?php if (isset($_GET['reset'])): ?><div class="alert alert-success">Password reset link sent to your email.</div><?php endif; ?>
            <?php if (isset($_GET['password_reset'])): ?><div class="alert alert-success">Password has been reset successfully. Please sign in.</div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label class="form-label" for="student_id">Student ID or Email</label>
                    <input type="text" id="student_id" name="student_id" class="form-input" placeholder="STU2024001 or student@school.edu" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePass('password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkbox-label">Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <div class="auth-footer">Don't have an account? <a href="register.php">Register</a></div>
                <a href="teacher_register.php" style="color:#f15a24;font-size:0.85rem;text-decoration:none;font-weight:500;">Register as Teacher</a><br>
        </div>
    </div>
    <script>
        function togglePass(id, btn) {
            var p = document.getElementById(id);
            var icon = btn.querySelector('i');
            if (p.type === 'password') { p.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { p.type = 'password'; icon.className = 'fas fa-eye'; }
        }
    </script>
</body>
</html>
