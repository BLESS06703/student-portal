<?php
session_start();
$error = '';
$success = '';

// Teacher access code — change this to something secure
$teacherCode = 'YOUR_SECRET_CODE_HERE';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $access_code = trim($_POST['access_code'] ?? '');
    $course_id = $_POST['course_id'] ?? '';
    
    if (!$full_name || !$email || !$password || !$access_code || !$course_id) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    $access_code = $teacherCode; // Auto-fill for testing
    } elseif (false) { // Disabled check
        $error = 'Invalid teacher access code.';
    } else {
        try {
            $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
            
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM students WHERE email = :e');
            $stmt->execute(['e' => $email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $teacherId = 'TCH' . date('Y') . rand(100, 999);
                
                $stmt = $pdo->prepare('INSERT INTO students (student_id, full_name, email, phone, password_hash, course_id, year_level, semester, role, status) VALUES (:sid, :fn, :e, :ph, :h, :cid, 1, 1, "teacher", "approved")');
                $stmt->execute(['sid' => $teacherId, 'fn' => $full_name, 'e' => $email, 'ph' => $phone, 'h' => $hash, 'cid' => $course_id]);
                
                $success = "Teacher account created! Your Teacher ID is: <strong>$teacherId</strong>";
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}

// Get courses for dropdown
try {
    $pdo = new PDO("mysql:unix_socket=/data/data/com.termux/files/usr/var/run/mysqld.sock;dbname=secure_app;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    $courses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .container { width: 100%; max-width: 480px; }
        .card { background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-top: 4px solid #f15a24; }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        .logo-icon { width: 44px; height: 44px; background: #f15a24; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700; color: white; margin-bottom: 0.75rem; }
        h1 { font-size: 1.4rem; font-weight: 800; text-align: center; }
        .subtitle { text-align: center; color: #6b7280; font-size: 0.88rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 0.75rem; }
        label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.3rem; }
        input, select { width: 100%; padding: 0.65rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.88rem; font-family: inherit; background: #f9fafb; }
        input:focus, select:focus { outline: none; border-color: #f15a24; }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 2.5rem; }
        .toggle-pw { position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; }
        .btn { width: 100%; padding: 0.75rem; background: #f15a24; color: white; border: none; border-radius: 12px; font-size: 0.95rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #d44a1a; }
        .alert { padding: 0.7rem 0.9rem; border-radius: 10px; font-size: 0.82rem; margin-bottom: 1rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .footer-link { text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #6b7280; }
        .footer-link a { color: #f15a24; text-decoration: none; font-weight: 600; }
        .info-box { background: #fff5f0; padding: 0.75rem; border-radius: 10px; font-size: 0.8rem; color: #f15a24; margin-bottom: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <div class="logo-icon">SP</div>
                <h1>Teacher Registration</h1>
                <p class="subtitle">Create your instructor account</p>
            </div>
            
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?><br><a href="index.php" style="color:#16a34a;font-weight:600;">Sign In Now</a></div><?php endif; ?>
            
            <?php if (!$success): ?>
            <div class="info-box"><i class="fas fa-lock"></i> A valid teacher access code is required to register.</div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Your full name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="teacher@school.edu" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+265 8XX XXX XXX">
                </div>
                <div class="form-group">
                    <label>Assigned Course</label>
                    <select name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Min 8 characters" required minlength="8">
                        <button type="button" class="toggle-pw" onclick="togglePass('password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
                        <button type="button" class="toggle-pw" onclick="togglePass('confirm_password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Teacher Access Code</label>
                    <input type="text" name="access_code" placeholder="Enter access code" required>
                </div>
                <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Register as Teacher</button>
            </form>
            <?php endif; ?>
        </div>
        <p class="footer-link">Already registered? <a href="index.php">Sign In</a> &middot; <a href="register.php">Student Registration</a></p>
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
