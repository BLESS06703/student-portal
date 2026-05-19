<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=yamabiko.proxy.rlwy.net;port=27745;dbname=railway;charset=utf8mb4", 'appuser', 'AppP@ssw0rd!');
    
    // Get all courses for edit modal
    $allCourses = $pdo->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
    
    $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $student = $stmt->fetch();
    
    $totalUploads = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE uploaded_by = :uid');
    $totalUploads->execute(['uid' => $_SESSION['user_id']]);
    $uploadCount = $totalUploads->fetchColumn();
    
    $statusBadge = '';
    $statusClass = '';
    if ($uploadCount >= 10) { $statusBadge = 'Top Contributor'; $statusClass = 'status-top'; }
    elseif ($uploadCount >= 5) { $statusBadge = 'Rising Star'; $statusClass = 'status-rising'; }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $course_id = $_POST['course_id'] ?? $student['course_id'];
        $year_level = $_POST['year_level'] ?? $student['year_level'];
        $semester = $_POST['semester'] ?? $student['semester'];
        
        if ($full_name && $email) {
            $stmt = $pdo->prepare('UPDATE students SET full_name = :fn, email = :e, phone = :ph, course_id = :cid, year_level = :yl, semester = :sm WHERE id = :id');
            $stmt->execute(['fn' => $full_name, 'e' => $email, 'ph' => $phone, 'cid' => $course_id, 'yl' => $year_level, 'sm' => $semester, 'id' => $_SESSION['user_id']]);
            $_SESSION['full_name'] = $full_name;
            $message = 'Profile updated successfully.';
            $stmt = $pdo->prepare('SELECT s.*, c.name AS course_name, c.code AS course_code FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = :id');
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $student = $stmt->fetch();
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowed) && $file['size'] < 2 * 1024 * 1024) {
            $uploadDir = '/data/data/com.termux/files/home/web_uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $pdo->prepare('UPDATE students SET profile_pic = :pp WHERE id = :id');
                $stmt->execute(['pp' => $filename, 'id' => $_SESSION['user_id']]);
                $student['profile_pic'] = $filename;
                $message = 'Profile picture updated.';
            }
        } else { $error = 'Invalid image. Use JPG, PNG, or GIF under 2MB.'; }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (password_verify($current, $student['password_hash'])) {
            if (strlen($new) >= 8 && $new === $confirm) {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('UPDATE students SET password_hash = :h WHERE id = :id');
                $stmt->execute(['h' => $hash, 'id' => $_SESSION['user_id']]);
                $message = 'Password changed successfully.';
            } else { $error = 'New password must be 8+ characters and match.'; }
        } else { $error = 'Current password is incorrect.'; }
    }
    
    $picPath = '/data/data/com.termux/files/home/web_uploads/profiles/' . ($student['profile_pic'] ?? '');
    $hasPhoto = $student['profile_pic'] && $student['profile_pic'] !== 'default.png' && file_exists($picPath);
    $initials = strtoupper(substr($student['full_name'] ?? 'U', 0, 2));
    $lastUpdated = date('F j, Y \a\t g:i A', strtotime($student['last_login'] ?? $student['created_at'] ?? 'now'));
    
} catch (PDOException $e) {
    $error = 'Error loading profile.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        .mobile-header { display: none; background: #fff; padding: 1rem 1.25rem; border-bottom: 1px solid #e5e7eb; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .hamburger { background: none; border: none; cursor: pointer; padding: 0.5rem; display: flex; flex-direction: column; gap: 5px; }
        .hamburger span { display: block; width: 24px; height: 2px; background: #1a1a1a; border-radius: 2px; }
        .mobile-logo { font-weight: 700; font-size: 1.1rem; }
        .sidebar { width: 260px; background: #fff; border-right: 1px solid #e5e7eb; padding: 1.5rem; position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; z-index: 200; transition: transform 0.3s ease; }
        .sidebar-logo { font-size: 1.5rem; font-weight: 800; margin-bottom: 2rem; letter-spacing: -0.5px; }
        .sidebar-nav { list-style: none; flex: 1; }
        .sidebar-nav li { margin-bottom: 0.35rem; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem; border-radius: 12px; color: #4b5563; text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: all 0.15s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { background: #f3f4f6; color: #1a1a1a; }
        .sidebar-nav a.active { background: #f3f4f6; color: #1a1a1a; font-weight: 600; border-left-color: #1a1a1a; }
        .sidebar-nav .nav-icon { width: 20px; text-align: center; font-size: 0.8rem; opacity: 0.5; }
        .sidebar-footer { border-top: 1px solid #e5e7eb; padding-top: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-footer .user-avatar-mini { width: 34px; height: 34px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; overflow: hidden; color: #6b7280; }
        .sidebar-footer .user-avatar-mini img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-footer .user-info { flex: 1; }
        .sidebar-footer .user-name { font-size: 0.85rem; font-weight: 600; }
        .sidebar-footer .user-role { font-size: 0.75rem; color: #6b7280; }
        .sidebar-footer a.signout-link { color: #6b7280; text-decoration: none; font-size: 0.8rem; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 150; }
        .sidebar-overlay.active { display: block; }
        .main-content { margin-left: 260px; padding: 2rem 2.5rem; max-width: 1000px; }
        .toast { position: fixed; top: 1.5rem; right: 1.5rem; background: #1a1a1a; color: white; padding: 1rem 1.5rem; border-radius: 12px; font-size: 0.9rem; font-weight: 500; z-index: 999; opacity: 0; transform: translateY(-20px); transition: all 0.4s ease; pointer-events: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }
        .profile-hero { background: #fff; border-radius: 20px; padding: 2.5rem; margin-bottom: 1.5rem; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: relative; overflow: hidden; }
        .profile-hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #e5e7eb 0%, #d1d5db 50%, #e5e7eb 100%); }
        .hero-content { display: flex; align-items: center; gap: 2rem; position: relative; z-index: 1; flex-wrap: wrap; }
        .hero-avatar-wrapper { position: relative; flex-shrink: 0; }
        .hero-avatar { width: 100px; height: 100px; border-radius: 50%; border: 3px solid #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #6b7280; background: #f9fafb; overflow: hidden; }
        .hero-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .hero-avatar-edit { position: absolute; bottom: 0; right: 0; width: 34px; height: 34px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.85rem; border: 2px solid #e5e7eb; transition: all 0.2s; color: #6b7280; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
        .hero-avatar-edit:hover { transform: scale(1.1); border-color: #1a1a1a; color: #1a1a1a; }
        .hero-avatar-edit input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .hero-info { flex: 1; min-width: 200px; }
        .hero-name { font-size: 1.3rem; font-weight: 700; color: #1a1a1a; letter-spacing: -0.3px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .status-active { background: #f3f4f6; color: #4b5563; }
        .status-rising { background: #fef3c7; color: #b45309; }
        .status-top { background: #dbeafe; color: #1d4ed8; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; display: inline-block; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .hero-meta { display: flex; gap: 1.5rem; flex-wrap: wrap; color: #6b7280; font-size: 0.85rem; margin-top: 0.5rem; }
        .hero-actions { display: flex; gap: 0.75rem; margin-top: 1rem; }
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .card { background: #fff; border-radius: 16px; border: none; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
        .card-title { font-size: 1rem; font-weight: 700; }
        .card-badge { font-size: 0.72rem; color: #6b7280; background: #f3f4f6; padding: 0.25rem 0.65rem; border-radius: 20px; font-weight: 500; }
        .info-row { display: flex; align-items: center; padding: 0.85rem 0; border-bottom: 1px solid #f3f4f6; gap: 1rem; flex-wrap: wrap; }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 100px; flex-shrink: 0; font-size: 0.72rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
        .info-value { font-size: 0.95rem; font-weight: 600; color: #1a1a1a; flex: 1; }
        .btn { padding: 0.7rem 1.25rem; background: #1a1a1a; color: white; border: none; border-radius: 14px; font-size: 0.875rem; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .btn:hover { background: #333; transform: translateY(-1px); }
        .btn-outline { background: transparent; color: #1a1a1a; border: 1.5px solid #d1d5db; }
        .btn-outline:hover { background: #f3f4f6; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.78rem; border-radius: 12px; }
        .form-input { width: 100%; padding: 0.75rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 14px; font-size: 0.9rem; font-family: inherit; color: #1a1a1a; margin-bottom: 0.75rem; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: #1a1a1a; background: #fff; }
        .form-select { width: 100%; padding: 0.75rem 0.9rem; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 14px; font-size: 0.9rem; font-family: inherit; color: #1a1a1a; margin-bottom: 0.75rem; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; align-items: center; justify-content: center; padding: 1rem; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; border-radius: 16px; padding: 2rem; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .modal-close:hover { color: #1a1a1a; }
        .modal-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; }
        @media (max-width: 1024px) {
            .mobile-header { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .profile-grid { grid-template-columns: 1fr; }
            .profile-hero { padding: 1.5rem; }
            .hero-content { flex-direction: column; text-align: center; }
            .hero-meta { justify-content: center; }
            .hero-name { justify-content: center; }
            .hero-actions { justify-content: center; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .info-label { width: auto; }
        }
        body.dark { background: #0f172a; color: #e2e8f0; }
        body.dark .sidebar, body.dark .card, body.dark .mobile-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        body.dark .sidebar-nav a { color: #94a3b8; }
        body.dark .sidebar-nav a:hover, body.dark .sidebar-nav a.active { background: #334155; color: #fff; }
        body.dark .sidebar-logo, body.dark .page-title { color: #f1f5f9; }
        body.dark .sidebar { background: #1e293b; border-color: #334155; }
        body.dark .mobile-header { border-color: #334155; }
        body.dark .hamburger span { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="toast" id="toast"></div>
    
    <!-- Edit Profile Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal" style="position:relative;">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 class="modal-title">Edit Profile</h2>
            <form method="POST" id="editProfileForm">
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Full Name</label>
                <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" required>
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Email Address</label>
                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Phone Number</label>
                <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                <label style="font-size:0.85rem;font-weight:500;color:#374151;">Course</label>
                <select name="course_id" class="form-select">
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $student['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div>
                        <label style="font-size:0.85rem;font-weight:500;color:#374151;">Year Level</label>
                        <select name="year_level" class="form-select">
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $student['year_level']) ? 'selected' : ''; ?>>Year <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.85rem;font-weight:500;color:#374151;">Semester</label>
                        <select name="semester" class="form-select">
                            <?php for ($s = 1; $s <= 3; $s++): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($s == ($student['semester'] ?? 1)) ? 'selected' : ''; ?>>Semester <?php echo $s; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn" style="width:100%;margin-top:0.75rem;">Save Changes</button>
            </form>
        </div>
    </div>
    
    <header class="mobile-header">
        <button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
        <div class="mobile-logo">Student Portal</div>
            <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;" id="themeBtn"><i class="fas fa-moon"></i></button>
        <div></div>
    </header>
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">Student Portal</div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><span class="nav-icon"><i class="fas fa-th-large"></i></span> Dashboard</a></li>
<li><a href="assignments.php"><i class="fas fa-clipboard-list"></i> Assignments</a></li>
            <li><a href="notes.php"><span class="nav-icon"><i class="fas fa-file-alt"></i></span> Course Notes</a></li>
            <li><a href="profile.php" class="active"><span class="nav-icon"><i class="fas fa-user"></i></span> My Profile</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-avatar-mini">
                <?php if ($hasPhoto): ?><img src="data:image/jpeg;base64,<?php echo base64_encode(file_get_contents($picPath)); ?>" alt="User">
                <?php else: echo substr($initials, 0, 1); endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars(explode(' ', $student['full_name'] ?? 'User')[0]); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($student['course_code'] ?? 'N/A'); ?></div>
            </div>
            <a href="logout.php" class="signout-link">Sign out</a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="profile-hero">
            <div class="hero-content">
                <div class="hero-avatar-wrapper">
                    <div class="hero-avatar" id="heroAvatar">
                        <?php if ($hasPhoto): ?><img src="data:image/jpeg;base64,<?php echo base64_encode(file_get_contents($picPath)); ?>" alt="Profile" id="heroImg">
                        <?php else: ?><span id="heroInitials"><?php echo $initials; ?></span><?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="photoForm">
                        <label class="hero-avatar-edit" title="Change photo"><i class="fas fa-camera"></i>
                            <input type="file" name="profile_pic" accept="image/*" onchange="previewImage(this); document.getElementById('photoForm').submit();">
                        </label>
                    </form>
                </div>
                <div class="hero-info">
                    <div class="hero-name">
                        <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>
                        <span class="status-dot" title="Active"></span>
                        <span class="hero-badge <?php echo $statusClass; ?>"><?php echo $statusBadge; ?></span>
                    </div>
                    <div class="hero-meta">
                        <span><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></span>
                        <span>Year <?php echo $student['year_level']; ?>, Semester <?php echo $student['semester'] ?? '1'; ?></span>
                        <span><?php echo $uploadCount; ?> uploads</span>
                    </div>
                    <div class="hero-actions">
                        <button onclick="openModal()" class="btn btn-sm"><i class="fas fa-edit"></i> Edit Profile</button>
                        <a href="dashboard.php" class="btn btn-sm btn-outline">Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?><script>showToast('<?php echo addslashes($message); ?>', 'success');</script><?php endif; ?>
        <?php if ($error): ?><script>showToast('<?php echo addslashes($error); ?>', 'error');</script><?php endif; ?>
        
        <div class="profile-grid">
            <div class="card">
                <div class="card-header"><span class="card-title">Student Information</span><span class="card-badge">Last active: <?php echo $lastUpdated; ?></span></div>
                <div class="info-row"><span class="info-label">Student ID</span><span class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? ''); ?></span></div>
                <div class="info-row"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?php echo htmlspecialchars($student['email'] ?? ''); ?></span></div>
                <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?php echo htmlspecialchars($student['phone'] ?: '--'); ?></span></div>
                <div class="info-row"><span class="info-label">Course</span><span class="info-value"><?php echo htmlspecialchars(($student['course_name'] ?? 'N/A') . ' (' . ($student['course_code'] ?? 'N/A') . ')'); ?></span></div>
                <div class="info-row"><span class="info-label">Level</span><span class="info-value">Year <?php echo $student['year_level']; ?> &middot; Semester <?php echo $student['semester'] ?? '1'; ?></span></div>
            </div>
            
            <div class="card">
                <div class="card-header"><span class="card-title">Change Password</span></div>
                <form method="POST">
                    <input type="password" name="current_password" class="form-input" placeholder="Current Password" required>
                    <input type="password" name="new_password" class="form-input" placeholder="New Password (8+ chars)" required minlength="8">
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm Password" required minlength="8">
                    <button type="submit" name="change_password" class="btn" style="width:100%;">Change Password</button>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('active'); }
        function openModal() { document.getElementById('editModal').classList.add('active'); }
        function closeModal() { document.getElementById('editModal').classList.remove('active'); }
        document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
        function previewImage(i) { if (i.files && i.files[0]) { var r = new FileReader(); r.onload = function(e) { document.getElementById('heroAvatar').innerHTML = '<img src="'+e.target.result+'" alt="Profile" style="width:100%;height:100%;object-fit:cover;">'; }; r.readAsDataURL(i.files[0]); } }
        function showToast(m, t) { var toast = document.getElementById('toast'); toast.textContent = m; toast.className = 'toast ' + t + ' show'; setTimeout(function() { toast.classList.remove('show'); }, 3000); }
    </script>
<script>
document.querySelectorAll("form").forEach(function(f) {
    f.addEventListener("submit", function() {
        var btn = f.querySelector("button[type=submit]");
        if (btn && !f.id.includes("photo")) { btn.disabled = true; btn.innerHTML = "<i class="fas fa-spinner fa-spin"></i> Processing..."; }
    });
});
</script>
</body>
</html>
<script>
function toggleTheme() {
    document.body.classList.toggle("dark");
    var icon = document.querySelector("#themeBtn i");
    if (document.body.classList.contains("dark")) {
        if(icon) icon.className = "fas fa-sun";
        localStorage.setItem("theme", "dark");
    } else {
        if(icon) icon.className = "fas fa-moon";
        localStorage.setItem("theme", "light");
    }
}
if (localStorage.getItem("theme") === "dark") { document.body.classList.add("dark"); }
</script>
