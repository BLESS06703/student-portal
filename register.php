<?php
session_start();
$error = '';
$success = '';

try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    $error = 'System error.';
    $courses = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $course_id = $_POST['course_id'] ?? '';
    $year_level = $_POST['year_level'] ?? '1';
    $semester = $_POST['semester'] ?? '1';
    
    if (!$student_id || !$full_name || !$email || !$password || !$course_id) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare('INSERT INTO students (student_id, full_name, email, phone, password_hash, course_id, year_level, semester) VALUES (:sid, :fn, :e, :ph, :h, :cid, :yl, :sm)');
            $stmt->execute([
                'sid' => $student_id, 'fn' => $full_name, 'e' => $email,
                'ph' => $phone, 'h' => $hash, 'cid' => $course_id,
                'yl' => $year_level, 'sm' => $semester
            ]);
            $success = 'Account created successfully!';
            header('refresh:2;url=index.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Student ID or email already exists.';
            } else {
                $error = 'Registration failed.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .auth-container { width: 100%; max-width: 520px; }
        .auth-card { background: #fff; border-radius: 16px; padding: 2.5rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.04); }
        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-logo { width: 44px; height: 44px; background: #1a1a1a; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; color: white; margin-bottom: 1rem; }
        .auth-title { font-size: 1.5rem; font-weight: 700; }
        .auth-subtitle { font-size: 0.9rem; color: #6b7280; margin-top: 0.3rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.4rem; }
        .form-input, .form-select {
            width: 100%; padding: 0.8rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb;
            border-radius: 8px; color: #1a1a1a; font-size: 0.9rem; font-family: inherit; transition: all 0.2s; -webkit-appearance: none;
        }
        .form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 1rem center; padding-right: 2.5rem;
        }
        .form-input:focus, .form-select:focus { outline: none; border-color: #1a1a1a; background: #fff; }
.password-wrapper { position: relative; } .password-wrapper .form-input { padding-right: 2.75rem; } .toggle-password { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 0.9rem; }
        .btn { width: 100%; padding: 0.85rem; background: #1a1a1a; color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; font-family: inherit; cursor: pointer; }
        .btn:hover { background: #333; }
        .alert { padding: 0.8rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.25rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #6b7280; }
        .auth-footer a { color: #1a1a1a; text-decoration: none; font-weight: 600; }
        @media (max-width: 480px) { .auth-card { padding: 1.5rem; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">SP</div>
                <h1 class="auth-title">Create Student Account</h1>
                <p class="auth-subtitle">Join your school's learning platform</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Student ID *</label>
                        <input type="text" id="student_id" name="student_id" class="form-input" placeholder="e.g. STU2024001" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" placeholder="John Doe" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="student@school.edu" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder="+255 7XX XXX XXX">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="course_id">Course *</label>
                        <select id="course_id" name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="year_level">Year Level</label>
                        <select id="year_level" name="year_level" class="form-select">
                            <option value="1">Year 1</option><option value="2">Year 2</option>
                            <option value="3">Year 3</option><option value="4">Year 4</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="semester">Semester</label>
                        <select id="semester" name="semester" class="form-select">
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                            <option value="3">Semester 3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password *</label>
                        <div class="password-wrapper"><input type="password" id="password" name="password" class="form-input" placeholder="Minimum 8 characters" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePass("password", this)"><i class="fas fa-eye"></i></button></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <div class="password-wrapper"><input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Re-enter your password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePass("confirm_password", this)"><i class="fas fa-eye"></i></button></div>
                </div>
                <button type="submit" class="btn">Create Account</button>
            </form>
            <div class="auth-footer">Already registered? <a href="index.php">Sign in</a></div>
        </div>
    </div>
    <script>function togglePass(id, btn) { var p = document.getElementById(id); var icon = btn.querySelector("i"); if (p.type === "password") { p.type = "text"; icon.className = "fas fa-eye-slash"; } else { p.type = "password"; icon.className = "fas fa-eye"; } }</script>
</body>
</html>
